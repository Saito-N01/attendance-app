<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.20：★ マイ勤怠レポート機能
 *
 * 画面（Blade）に渡される summary / monthlyTrend / anomalies の「値」を viewData で直接検証し、
 * 表示（○h ○m）は代表的なものだけ assertSee で確かめる。
 * 「今」を 2026-10-15 に固定するので、対象は 2026/05〜2026/10 の6ヶ月になる。
 */
class AttendanceReportTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setNow('2026-10-15 10:00:00');
    }

    /** ゲストはレポートページにアクセスできない */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/attendance/report')->assertRedirect('/login');
    }

    /** 認証ユーザーの統計情報（summary / monthly_trend / anomalies）が正しく計算される */
    public function test_statistics_are_calculated_correctly(): void
    {
        $user = $this->loginAsUser();
        $lunch = [['12:00', '13:00']];

        // ── 当月（2026/10） ──────────────────────────────
        $this->createRecord($user, '2026-10-01', '09:00', '18:00', $lunch); // 480分：所定どおり（遅刻・早退なし）
        $this->createRecord($user, '2026-10-02', '09:30', '19:30', $lunch); // 540分：残業60／遅刻
        $this->createRecord($user, '2026-10-05', '09:00', '20:30', $lunch); // 630分：残業150／長時間労働(10時間超)
        $this->createRecord($user, '2026-10-06', '09:00', '16:00', $lunch); // 360分：早退
        $this->createRecord($user, '2026-10-08', '10:00', '19:00', $lunch); // 480分：遅刻
        $this->createRecord($user, '2026-10-09', '09:00', '20:00', $lunch); // 600分：残業120／ちょうど10時間は「超」ではないので長時間労働にしない（境界）
        $this->createTodayRecord($user, 'clock_in');                         // 10/15 退勤前：労働時間が未確定なので集計外
        // ── 前月（2026/09） ──────────────────────────────
        $this->createRecord($user, '2026-09-10', '09:00', '18:00', $lunch); // 480分
        // ── 集計対象外 ──────────────────────────────────
        $this->createRecord($user, '2026-04-30', '09:00', '18:00', $lunch); // 6ヶ月より前
        $this->createRecord(User::factory()->create(), '2026-10-03', '09:00', '20:00', $lunch); // 他人の勤怠

        $response = $this->get('/attendance/report');

        $response->assertOk();

        // 基本サマリー：総 480+540+630+360+480+600+480=3570分、残業 60+150+120=330分、平均 3570÷7=510分
        $this->assertSame([
            'total_work_minutes' => 3570,
            'total_overtime_minutes' => 330,
            'avg_work_minutes' => 510,
        ], $response->viewData('summary'));
        $response->assertSeeInOrder(['59h 30m', '5h 30m', '8h 30m']);

        // 月次推移：データの無い月は 0。10月 = 3090分(残業330)、9月 = 480分(残業0)
        $this->assertSame([
            ['month' => '2026/05', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/06', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/07', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/08', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/09', 'work_minutes' => 480, 'overtime_minutes' => 0],
            ['month' => '2026/10', 'work_minutes' => 3090, 'overtime_minutes' => 330],
        ], $response->viewData('monthlyTrend')->all());

        // 異常検知（当月）：遅刻は 10/02・10/08 の2回、早退は 10/06 の1回、長時間労働は 10/05 の1日
        $this->assertSame([
            'late_count' => 2,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ], $response->viewData('anomalies'));
    }

    /** 勤怠記録がないユーザーでも、エラーにならず各統計が 0 / 空で返る */
    public function test_user_without_records_is_handled_safely(): void
    {
        $this->loginAsUser();

        $response = $this->get('/attendance/report');

        $response->assertOk();
        $this->assertSame([
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0, // 0件での割り算（ゼロ除算）にならない
        ], $response->viewData('summary'));
        $this->assertSame(
            [0],
            $response->viewData('monthlyTrend')->pluck('work_minutes')->unique()->all()
        );
        $this->assertCount(6, $response->viewData('monthlyTrend')); // 月の枠は6つとも出る
        $this->assertSame([
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ], $response->viewData('anomalies'));
    }
}
