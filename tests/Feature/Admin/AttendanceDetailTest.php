<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.13：勤怠詳細情報取得・修正機能（管理者）
 *
 * 管理者の修正フォームの送信先は一般ユーザーと共通の POST /attendance/{id} で、
 * 管理者のときだけ管理者用の更新処理（承認フローなし）に振り分けられる。
 */
class AttendanceDetailTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    private AttendanceRecord $record;

    private string $detailUrl;

    /** 管理者でログインし、スタッフの勤怠詳細ページを開ける状態にする */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setNow('2026-10-15 10:00:00');
        $this->loginAsAdmin();

        $staff = User::factory()->create(['name' => '山田 花子']);
        $this->record = $this->createRecord($staff, '2026-10-10', '09:12', '18:34', [
            ['12:00', '12:45'],
            ['15:00', '15:10'],
        ]);
        $this->record->update(['comment' => '電車遅延のため']);

        $this->detailUrl = '/admin/attendance/'.$this->record->id;
    }

    /** 勤怠詳細画面に表示されるデータが選択したものになっている */
    public function test_detail_shows_the_selected_record(): void
    {
        $this->get($this->detailUrl)
            ->assertOk()
            ->assertSee('name="name" value="山田 花子"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="10月10日"', false)
            ->assertSee('value="09:12"', false)
            ->assertSee('value="18:34"', false)
            // 休憩2回（ラベルは「休憩」「休憩2」）
            ->assertSee('value="12:00"', false)
            ->assertSee('value="12:45"', false)
            ->assertSee('休憩2')
            ->assertSee('value="15:00"', false)
            ->assertSee('value="15:10"', false)
            ->assertSee('電車遅延のため');
    }

    /**
     * 不正な値で保存処理をし、元の詳細画面に戻ってエラーが表示されること、
     * 勤怠は変更されないことを確認する。
     */
    private function assertErrorShown(array $override, string $key, string $message): void
    {
        $this->from($this->detailUrl)
            ->post('/attendance/'.$this->record->id, $this->correctionInput($override))
            ->assertRedirect($this->detailUrl)
            ->assertSessionHasErrors([$key => $message]);

        $this->get($this->detailUrl)->assertSee($message);

        $record = $this->record->fresh();
        $this->assertTime('09:12', $record->clock_in);
        $this->assertTime('18:34', $record->clock_out);
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

    /**
     * 【追加】正しい値なら、承認フローを通さず勤怠が直接修正される（休憩は入力内容で置き換わる）。
     */
    public function test_valid_edit_updates_the_record_directly(): void
    {
        $this->from($this->detailUrl)
            ->post('/attendance/'.$this->record->id, $this->correctionInput(['comment' => 'システム障害のため']))
            ->assertRedirect($this->detailUrl)
            ->assertSessionHas('message', '勤怠を修正しました。');

        $record = $this->record->fresh();
        $this->assertTime('09:30', $record->clock_in);
        $this->assertTime('18:30', $record->clock_out);
        $this->assertSame('システム障害のため', $record->comment);

        $break = $record->breaks()->sole(); // 2件あった休憩が、入力した1件に置き換わる
        $this->assertTime('12:15', $break->break_in);
        $this->assertTime('13:15', $break->break_out);

        $this->assertDatabaseCount('applications', 0); // 申請は作られない
    }
}
