<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.7：休憩機能
 */
class BreakTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    /** 休憩ボタンが正しく機能する */
    public function test_break_in_button_works(): void
    {
        $this->setNow('2026-10-12 12:00:00');
        $user = $this->loginAsUser();
        $record = $this->createTodayRecord($user, 'clock_in'); // 出勤中

        // 画面に「休憩入」ボタンが表示されている
        $this->get('/attendance')
            ->assertSee($this->buttonHtml('break_in', '休憩入'), false);

        // 休憩入の処理を行うと、休憩開始時刻が保存され、ステータスが「休憩中」になる
        $this->stamp('break_in')->assertRedirect('/attendance');

        $break = $record->breaks()->sole();
        $this->assertTime('12:00', $break->break_in);
        $this->assertNull($break->break_out);
        $this->get('/attendance')
            ->assertSee($this->statusHtml('休憩中'), false);
    }

    /** 休憩は一日に何回でもできる */
    public function test_break_in_can_be_done_many_times(): void
    {
        $this->setNow('2026-10-12 12:00:00');
        $user = $this->loginAsUser();
        $record = $this->createTodayRecord($user, 'clock_in'); // 出勤中

        // 休憩入 → 休憩戻 を1回行った後でも
        $this->stamp('break_in');
        $this->setNow('2026-10-12 12:45:00');
        $this->stamp('break_out');

        // 再び「休憩入」ボタンが表示される
        $this->get('/attendance')
            ->assertSee($this->buttonHtml('break_in', '休憩入'), false);

        // 2回目の休憩入も実際にできる（休憩が2件になる）
        $this->setNow('2026-10-12 15:00:00');
        $this->stamp('break_in');
        $this->assertCount(2, $record->breaks()->get());
    }

    /** 休憩戻ボタンが正しく機能する */
    public function test_break_out_button_works(): void
    {
        $this->setNow('2026-10-12 12:00:00');
        $user = $this->loginAsUser();
        $record = $this->createTodayRecord($user, 'clock_in'); // 出勤中
        $this->stamp('break_in');

        // 休憩中は「休憩戻」ボタンが表示されている
        $this->get('/attendance')
            ->assertSee($this->buttonHtml('break_out', '休憩戻'), false);

        // 休憩戻の処理を行うと、休憩終了時刻が保存され、ステータスが「出勤中」に戻る
        $this->setNow('2026-10-12 12:45:00');
        $this->stamp('break_out')->assertRedirect('/attendance');

        $break = $record->breaks()->sole();
        $this->assertTime('12:00', $break->break_in);
        $this->assertTime('12:45', $break->break_out);
        $this->get('/attendance')
            ->assertSee($this->statusHtml('出勤中'), false);
    }

    /** 休憩戻は一日に何回でもできる */
    public function test_break_out_can_be_done_many_times(): void
    {
        $this->setNow('2026-10-12 12:00:00');
        $user = $this->loginAsUser();
        $record = $this->createTodayRecord($user, 'clock_in'); // 出勤中

        // 休憩入 → 休憩戻 を行い、再度 休憩入 の処理を行う
        $this->stamp('break_in');
        $this->setNow('2026-10-12 12:45:00');
        $this->stamp('break_out');
        $this->setNow('2026-10-12 15:00:00');
        $this->stamp('break_in');

        // 「休憩戻」ボタンが表示される
        $this->get('/attendance')
            ->assertSee($this->buttonHtml('break_out', '休憩戻'), false);

        // 2回目の休憩戻も実際にできる（休憩2件とも終了時刻が入る）
        $this->setNow('2026-10-12 15:15:00');
        $this->stamp('break_out');
        $breaks = $record->breaks()->get();
        $this->assertCount(2, $breaks);
        $this->assertNull($breaks->firstWhere('break_out', null));
    }

    /** 休憩時刻が勤怠一覧画面で確認できる */
    public function test_break_time_is_shown_on_attendance_list(): void
    {
        $this->setNow('2026-10-12 12:00:00');
        $user = $this->loginAsUser();
        $this->createTodayRecord($user, 'clock_in'); // 出勤中

        // 休憩入（12:00）と休憩戻（12:45）の処理を行う
        $this->stamp('break_in');
        $this->setNow('2026-10-12 12:45:00');
        $this->stamp('break_out');

        // 勤怠一覧画面の「休憩」欄に、休憩時間（0:45）が記録されている
        $this->get('/attendance/list')
            ->assertOk()
            ->assertSee('<p class="table__description--item">0:45</p>', false);
    }
}
