<?php

namespace Tests\Concerns;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Testing\TestResponse;

/**
 * 打刻機能（No.4〜8）のテストで共通に使う準備・操作・表示確認のヘルパー。
 */
trait InteractsWithAttendance
{
    /** 「今」を固定する（日付・時刻の表示と打刻時刻を確定させるため） */
    protected function setNow(string $datetime = '2026-10-12 09:00:00'): void
    {
        $this->travelTo(Carbon::parse($datetime, config('app.timezone')));
    }

    /** メール認証済みの一般ユーザーを作成してログインする */
    protected function loginAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    /**
     * 今日の勤怠を、指定した状態で用意する（勤務外は「勤怠なし」なので何も作らない）。
     *
     * @param  'clock_in'|'on_break'|'clock_out'  $state  出勤中／休憩中／退勤済
     */
    protected function createTodayRecord(User $user, string $state): AttendanceRecord
    {
        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => $state === 'clock_out' ? '18:00:00' : null,
        ]);

        if ($state === 'on_break') {
            $record->breaks()->create(['break_in' => '12:00:00']);
        }

        return $record;
    }

    /** 打刻画面から、ボタン（action）を押した処理を行う */
    protected function stamp(string $action): TestResponse
    {
        return $this->post('/attendance', ['action' => $action]);
    }

    /**
     * DBに保存された時刻が「時:分」として期待どおりかを確認する。
     * TIME列は MySQL だと 09:05:00、SQLite だと書き込んだ 09:05 のまま返るため、先頭の HH:MM で比べる。
     */
    protected function assertTime(string $expected, ?string $actual): void
    {
        $this->assertNotNull($actual);
        $this->assertSame($expected, substr($actual, 0, 5));
    }

    /** 打刻画面に表示されているステータス表示のHTML */
    protected function statusHtml(string $status): string
    {
        return '<p class="attendance__status--item">'.$status.'</p>';
    }

    /** 打刻画面のボタンのHTML（action と表示名） */
    protected function buttonHtml(string $action, string $label): string
    {
        return 'type="submit" name="action" value="'.$action.'">'.$label.'</button>';
    }
}
