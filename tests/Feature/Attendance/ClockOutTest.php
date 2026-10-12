<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.8：退勤機能
 */
class ClockOutTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    /** 退勤ボタンが正しく機能する */
    public function test_clock_out_button_works(): void
    {
        $this->setNow('2026-10-12 18:00:00');
        $user = $this->loginAsUser();
        $this->createTodayRecord($user, 'clock_in'); // 出勤中（出勤 09:00）

        // 画面に「退勤」ボタンが表示されている
        $this->get('/attendance')
            ->assertSee($this->buttonHtml('clock_out', '退勤'), false);

        // 退勤の処理を行うと、退勤時刻が保存され、ステータスが「退勤済」になる
        $this->stamp('clock_out')->assertRedirect('/attendance');

        $this->assertTime('18:00', AttendanceRecord::where('user_id', $user->id)->sole()->clock_out);
        $this->get('/attendance')
            ->assertSee($this->statusHtml('退勤済'), false);
    }

    /** 退勤時刻が勤怠一覧画面で確認できる */
    public function test_clock_out_time_is_shown_on_attendance_list(): void
    {
        $this->loginAsUser(); // 勤務外

        // 出勤（09:00）と退勤（18:00）の処理を行う
        $this->setNow('2026-10-12 09:00:00');
        $this->stamp('clock_in');
        $this->setNow('2026-10-12 18:00:00');
        $this->stamp('clock_out');

        // 勤怠一覧画面に、今日の行として退勤時刻が記録されている
        $this->get('/attendance/list')
            ->assertOk()
            ->assertSeeInOrder(['10/12(月)', '09:00', '18:00'], false);
    }
}
