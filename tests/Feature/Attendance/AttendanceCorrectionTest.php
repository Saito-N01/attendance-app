<?php

namespace Tests\Feature\Attendance;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.11：勤怠詳細情報修正機能（一般ユーザー）
 */
class AttendanceCorrectionTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    private User $user;

    private AttendanceRecord $record;

    /** 勤怠情報が登録されたユーザーにログインする（勤務 09:00〜18:00、休憩 12:00〜13:00） */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setNow('2026-10-15 10:00:00');
        $this->user = $this->loginAsUser();
        $this->user->update(['name' => '山田 花子']);
        $this->record = $this->createRecord($this->user, '2026-10-10', '09:00', '18:00', [['12:00', '13:00']]);
    }

    /** 勤怠詳細ページで不正な値を保存し、エラーが画面に表示されることまで確認する */
    private function assertErrorShown(array $override, string $key, string $message): void
    {
        $url = '/attendance/'.$this->record->id;

        $this->from($url)
            ->post($url, $this->correctionInput($override))
            ->assertRedirect($url)
            ->assertSessionHasErrors([$key => $message]);

        $this->get($url)->assertSee($message);
        $this->assertDatabaseCount('applications', 0); // 申請は作られない
    }

    /** 出勤時間が退勤時間より後になっている場合、エラーメッセージが表示される */
    public function test_clock_in_after_clock_out_shows_error(): void
    {
        $this->assertErrorShown(
            ['new_clock_in' => '19:00', 'new_clock_out' => '18:00'],
            'new_clock_out',
            '出勤時間もしくは退勤時間が不適切な値です',
        );
    }

    /** 休憩開始時間が退勤時間より後になっている場合、エラーメッセージが表示される */
    public function test_break_in_after_clock_out_shows_error(): void
    {
        $this->assertErrorShown(
            ['new_break_in' => [0 => '19:00'], 'new_break_out' => [0 => '19:30']],
            'new_break_in.0',
            '休憩時間が不適切な値です',
        );
    }

    /** 休憩終了時間が退勤時間より後になっている場合、エラーメッセージが表示される */
    public function test_break_out_after_clock_out_shows_error(): void
    {
        $this->assertErrorShown(
            ['new_break_in' => [0 => '17:00'], 'new_break_out' => [0 => '19:00']],
            'new_break_out.0',
            '休憩時間もしくは退勤時間が不適切な値です',
        );
    }

    /** 備考欄が未入力の場合のエラーメッセージが表示される */
    public function test_comment_is_required(): void
    {
        $this->assertErrorShown(['comment' => ''], 'comment', '備考を記入してください');
    }

    /** 修正申請処理が実行される（管理者の承認画面と申請一覧画面に表示される） */
    public function test_correction_request_is_saved_and_shown_to_admin(): void
    {
        // 勤怠詳細を修正し、保存処理をする
        $this->submitCorrection($this->record)->assertRedirect('/attendance/detail/'.$this->record->id);

        $application = Application::sole();
        $this->assertSame($this->record->id, $application->attendance_record_id);
        $this->assertSame($this->user->id, $application->user_id);
        $this->assertSame(Application::STATUS_PENDING, $application->status);
        $this->assertTime('09:30', $application->new_clock_in);
        $this->assertTime('18:30', $application->new_clock_out);
        $this->assertSame('電車遅延のため', $application->comment);
        $this->assertTime('12:15', $application->breaks()->sole()->new_break_in);

        // 管理者ユーザーで、承認画面と申請一覧画面を確認する
        $this->loginAsAdmin();

        $this->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertSee('value="山田 花子"', false)
            ->assertSee('value="09:30"', false)
            ->assertSee('value="18:30"', false)
            ->assertSee('value="12:15"', false)
            ->assertSee('value="13:15"', false)
            ->assertSee('電車遅延のため');

        $this->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertSee('山田 花子')
            ->assertSee('電車遅延のため')
            ->assertSee('/stamp_correction_request/approve/'.$application->id.'">詳細</a>', false);
    }

    /** 「承認待ち」にログインユーザーが行った申請が全て表示されていること */
    public function test_my_pending_applications_are_all_shown(): void
    {
        $second = $this->createRecord($this->user, '2026-10-11');
        $this->submitCorrection($this->record, ['comment' => '申請A：打刻漏れ']);
        $this->submitCorrection($second, ['comment' => '申請B：遅延']);

        // 他のユーザーの申請（表示されてはいけない）
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);
        $this->submitCorrection($this->createRecord($otherUser, '2026-10-12'), ['comment' => '他人の申請']);

        $this->actingAs($this->user);
        [$pending, $approved] = $this->applicationTabs();

        // 「承認待ち」タブに自分の2件が、状態「承認待ち」で表示される
        $this->assertStringContainsString('申請A：打刻漏れ', $pending);
        $this->assertStringContainsString('申請B：遅延', $pending);
        $this->assertSame(2, substr_count($pending, '<p class="table__description--item">承認待ち</p>'));
        $this->assertStringNotContainsString('他人の申請', $pending);
        // 「承認済み」タブには出ない
        $this->assertStringNotContainsString('申請A：打刻漏れ', $approved);
    }

    /** 「承認済み」に管理者が承認した修正申請が全て表示されている */
    public function test_approved_applications_are_all_shown(): void
    {
        $second = $this->createRecord($this->user, '2026-10-11');
        $third = $this->createRecord($this->user, '2026-10-12');
        $this->submitCorrection($this->record, ['comment' => '申請A：打刻漏れ']);
        $this->submitCorrection($second, ['comment' => '申請B：遅延']);
        $this->submitCorrection($third, ['comment' => '申請C：未承認のまま']);

        // 管理者が、AとBを承認する（Cは承認待ちのまま）
        $this->loginAsAdmin();
        foreach (['申請A：打刻漏れ', '申請B：遅延'] as $comment) {
            $id = Application::where('comment', $comment)->value('id');
            $this->post('/stamp_correction_request/approve/'.$id);
        }

        $this->actingAs($this->user);
        [$pending, $approved] = $this->applicationTabs();

        // 「承認済み」タブに、承認された2件が、状態「承認済み」で表示される
        $this->assertStringContainsString('申請A：打刻漏れ', $approved);
        $this->assertStringContainsString('申請B：遅延', $approved);
        $this->assertSame(2, substr_count($approved, '<p class="table__description--item">承認済み</p>'));
        $this->assertStringNotContainsString('申請C：未承認のまま', $approved);
        // 「承認待ち」タブには未承認のCだけが残る
        $this->assertStringContainsString('申請C：未承認のまま', $pending);
        $this->assertStringNotContainsString('申請A：打刻漏れ', $pending);
    }

    /** 各申請の「詳細」を押下すると勤怠詳細画面に遷移する */
    public function test_application_detail_link_opens_attendance_detail(): void
    {
        $this->submitCorrection($this->record);
        $application = Application::sole();

        // 申請一覧に「詳細」リンクがある
        $this->get('/stamp_correction_request/list')
            ->assertSee('/application/'.$application->id.'">詳細</a>', false);

        // リンク先は、その勤怠の詳細画面へ遷移する
        $this->get('/application/'.$application->id)
            ->assertRedirect('/attendance/detail/'.$this->record->id);

        // 勤怠詳細画面が表示される（承認待ちなので閲覧のみ）
        $this->get('/attendance/detail/'.$this->record->id)
            ->assertOk()
            ->assertSee('勤怠詳細')
            ->assertSee('承認待ちのため修正できません。');
    }

    /**
     * 申請一覧を開き、「承認待ち」タブと「承認済み」タブの中身に分けて返す。
     * 2つのタブは同じHTMLに入っている（表示の切り替えはCSS）ので、id="content2" で前後に分ける。
     *
     * @return array{0: string, 1: string}
     */
    private function applicationTabs(): array
    {
        $html = $this->get('/stamp_correction_request/list')->assertOk()->getContent();

        [$pending, $approved] = explode('id="content2"', $html, 2);

        return [$pending, $approved];
    }
}
