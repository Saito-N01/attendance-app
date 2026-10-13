<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.15：勤怠情報修正機能（管理者）
 */
class CorrectionRequestTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    private User $yamada;

    private User $suzuki;

    private AttendanceRecord $yamadaRecord;

    private AttendanceRecord $suzukiRecord;

    /** 手順1：管理者ユーザーにログインする（スタッフ2人の勤怠：山田 10/10、鈴木 10/11） */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setNow('2026-10-15 10:00:00');
        $this->loginAsAdmin();

        $this->yamada = User::factory()->create(['name' => '山田 花子']);
        $this->suzuki = User::factory()->create(['name' => '鈴木 次郎']);
        $this->yamadaRecord = $this->createRecord($this->yamada, '2026-10-10', '09:00', '18:00', [['12:00', '13:00']]);
        $this->suzukiRecord = $this->createRecord($this->suzuki, '2026-10-11', '09:00', '18:00');
    }

    /** 承認待ちの修正申請が全て表示されている */
    public function test_all_pending_applications_are_shown(): void
    {
        $this->createCorrectionApplication($this->yamadaRecord, '申請A：山田の打刻漏れ');
        $this->createCorrectionApplication($this->suzukiRecord, '申請B：鈴木の遅延');
        $this->createCorrectionApplication($this->yamadaRecord, '申請C：承認済み', Application::STATUS_APPROVED);

        [$pending, $approved] = $this->applicationTabs();

        // 「承認待ち」タブに、全ユーザーの未承認の申請が表示される
        $this->assertStringContainsString('山田 花子', $pending);
        $this->assertStringContainsString('申請A：山田の打刻漏れ', $pending);
        $this->assertStringContainsString('鈴木 次郎', $pending);
        $this->assertStringContainsString('申請B：鈴木の遅延', $pending);
        $this->assertSame(2, substr_count($pending, $this->cellHtml('承認待ち')));
        // 承認済みの申請は出ない
        $this->assertStringNotContainsString('申請C：承認済み', $pending);
    }

    /** 承認済みの修正申請が全て表示されている */
    public function test_all_approved_applications_are_shown(): void
    {
        $this->createCorrectionApplication($this->yamadaRecord, '申請A：山田の打刻漏れ', Application::STATUS_APPROVED);
        $this->createCorrectionApplication($this->suzukiRecord, '申請B：鈴木の遅延', Application::STATUS_APPROVED);
        $this->createCorrectionApplication($this->yamadaRecord, '申請C：未承認のまま');

        [$pending, $approved] = $this->applicationTabs();

        // 「承認済み」タブに、全ユーザーの承認済みの申請が表示される
        $this->assertStringContainsString('申請A：山田の打刻漏れ', $approved);
        $this->assertStringContainsString('申請B：鈴木の遅延', $approved);
        $this->assertSame(2, substr_count($approved, $this->cellHtml('承認済み')));
        // 未承認の申請は出ない
        $this->assertStringNotContainsString('申請C：未承認のまま', $approved);
    }

    /** 修正申請の詳細内容が正しく表示されている */
    public function test_application_detail_shows_the_requested_content(): void
    {
        $application = $this->createCorrectionApplication($this->yamadaRecord, '電車遅延のため');

        // 申請一覧の「詳細」リンクから、承認画面を開く
        $this->get('/stamp_correction_request/list')
            ->assertSee('/stamp_correction_request/approve/'.$application->id.'">詳細</a>', false);

        $this->get('/stamp_correction_request/approve/'.$application->id)
            ->assertOk()
            ->assertSee('value="山田 花子"', false)
            ->assertSee('value="2026年"', false)
            ->assertSee('value="10月10日"', false)
            ->assertSee('value="09:30"', false)
            ->assertSee('value="18:30"', false)
            ->assertSee('value="12:15"', false)
            ->assertSee('value="13:15"', false)
            ->assertSee('電車遅延のため')
            ->assertSee('type="submit">承認</button>', false);
    }

    /** 修正申請の承認処理が正しく行われる（修正申請が承認され、勤怠情報が更新される） */
    public function test_approval_updates_the_attendance_record(): void
    {
        $application = $this->createCorrectionApplication($this->yamadaRecord, '電車遅延のため');
        $approveUrl = '/stamp_correction_request/approve/'.$application->id;

        $this->post($approveUrl)->assertRedirect($approveUrl);

        // 申請が承認済みになる
        $application = $application->fresh();
        $this->assertSame(Application::STATUS_APPROVED, $application->status);
        $this->assertNotNull($application->approved_at);

        // 勤怠情報が申請内容に更新される（休憩も申請の内容に置き換わる）
        $record = $this->yamadaRecord->fresh();
        $this->assertTime('09:30', $record->clock_in);
        $this->assertTime('18:30', $record->clock_out);
        $this->assertSame('電車遅延のため', $record->comment);
        $break = $record->breaks()->sole();
        $this->assertTime('12:15', $break->break_in);
        $this->assertTime('13:15', $break->break_out);

        // 承認画面は「承認済み」の表示になり、承認ボタンは出ない
        $this->get($approveUrl)
            ->assertSee('承認済み')
            ->assertDontSee('type="submit">承認</button>', false);
    }

    /**
     * 【追加】一般ユーザーは承認できない（管理者ログインへ戻され、申請も勤怠も変わらない）。
     */
    public function test_general_user_cannot_approve(): void
    {
        $application = $this->createCorrectionApplication($this->yamadaRecord, '電車遅延のため');
        $this->actingAs($this->suzuki);

        $this->post('/stamp_correction_request/approve/'.$application->id)->assertRedirect('/admin/login');

        $this->assertSame(Application::STATUS_PENDING, $application->fresh()->status);
        $this->assertTime('09:00', $this->yamadaRecord->fresh()->clock_in);
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
