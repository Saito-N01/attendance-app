<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.5：ステータス確認機能
 *
 * ステータス名は FN019 に従う（勤務外／出勤中／休憩中／退勤済）。
 */
class StatusTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setNow();
    }

    /** 勤務外の場合、勤怠ステータスが正しく表示される */
    public function test_status_is_off_duty_when_not_clocked_in(): void
    {
        $this->loginAsUser(); // 今日の勤怠なし＝勤務外

        $this->get('/attendance')
            ->assertOk()
            ->assertSee($this->statusHtml('勤務外'), false);
    }

    /** 出勤中の場合、勤怠ステータスが正しく表示される */
    public function test_status_is_working_after_clock_in(): void
    {
        $this->createTodayRecord($this->loginAsUser(), 'clock_in');

        $this->get('/attendance')
            ->assertOk()
            ->assertSee($this->statusHtml('出勤中'), false);
    }

    /** 休憩中の場合、勤怠ステータスが正しく表示される */
    public function test_status_is_on_break_during_break(): void
    {
        $this->createTodayRecord($this->loginAsUser(), 'on_break');

        $this->get('/attendance')
            ->assertOk()
            ->assertSee($this->statusHtml('休憩中'), false);
    }

    /** 退勤済の場合、勤怠ステータスが正しく表示される */
    public function test_status_is_clocked_out_after_clock_out(): void
    {
        $this->createTodayRecord($this->loginAsUser(), 'clock_out');

        $this->get('/attendance')
            ->assertOk()
            ->assertSee($this->statusHtml('退勤済'), false);
    }
}
