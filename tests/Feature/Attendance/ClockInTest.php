<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.6：出勤機能
 */
class ClockInTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setNow('2026-10-12 09:05:00');
    }

    /** 出勤ボタンが正しく機能する */
    public function test_clock_in_button_works(): void
    {
        $user = $this->loginAsUser(); // 勤務外

        // 画面に「出勤」ボタンが表示されている
        $this->get('/attendance')
            ->assertSee($this->buttonHtml('clock_in', '出勤'), false);

        // 出勤の処理を行うと、出勤時刻が保存され、ステータスが「出勤中」になる
        $this->stamp('clock_in')->assertRedirect('/attendance');

        $this->assertTime('09:05', AttendanceRecord::where('user_id', $user->id)->sole()->clock_in);
        $this->get('/attendance')
            ->assertSee($this->statusHtml('出勤中'), false);
    }

    /** 出勤は一日一回のみできる */
    public function test_clock_in_button_is_hidden_after_clock_out(): void
    {
        $this->createTodayRecord($this->loginAsUser(), 'clock_out'); // 退勤済

        $this->get('/attendance')
            ->assertOk()
            ->assertDontSee($this->buttonHtml('clock_in', '出勤'), false);
    }

    /**
     * 【追加】ボタンが無くても、直接リクエストされて二重出勤にならない（サーバー側の防御）。
     */
    public function test_clock_in_request_is_ignored_after_clock_out(): void
    {
        $user = $this->loginAsUser();
        $this->createTodayRecord($user, 'clock_out'); // 出勤 09:00 / 退勤 18:00

        $this->stamp('clock_in');

        $record = AttendanceRecord::where('user_id', $user->id)->sole();
        $this->assertTime('09:00', $record->clock_in);
        $this->assertTime('18:00', $record->clock_out);
    }

    /** 出勤時刻が勤怠一覧画面で確認できる */
    public function test_clock_in_time_is_shown_on_attendance_list(): void
    {
        $this->loginAsUser(); // 勤務外

        $this->stamp('clock_in');

        // 勤怠一覧画面に、今日の行として出勤時刻が記録されている
        $this->get('/attendance/list')
            ->assertOk()
            ->assertSeeInOrder(['10/12(月)', '09:05'], false);
    }
}
