<?php

namespace Tests\Concerns;

use App\Models\Application;
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

    /** 管理者ユーザー（admin_status = true）を作成してログインする */
    protected function loginAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        return $admin;
    }

    /**
     * 指定した日の勤怠を、出勤・退勤・休憩つきで用意する。
     *
     * @param  list<array{0: string, 1: string}>  $breaks  [休憩入, 休憩戻] の組
     */
    protected function createRecord(
        User $user,
        string $date,
        string $clockIn = '09:00',
        string $clockOut = '18:00',
        array $breaks = [],
    ): AttendanceRecord {
        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);

        foreach ($breaks as [$in, $out]) {
            $record->breaks()->create(['break_in' => $in, 'break_out' => $out]);
        }

        return $record;
    }

    /**
     * 修正申請を、休憩つきで直接用意する（申請の作成フローそのものではなく、一覧・承認の確認用）。
     *
     * @param  list<array{0: string, 1: string}>  $breaks  [休憩入, 休憩戻] の組
     */
    protected function createCorrectionApplication(
        AttendanceRecord $record,
        string $comment,
        int $status = Application::STATUS_PENDING,
        array $breaks = [['12:15', '13:15']],
        string $clockIn = '09:30',
        string $clockOut = '18:30',
    ): Application {
        $application = Application::create([
            'attendance_record_id' => $record->id,
            'user_id' => $record->user_id,
            'date' => Carbon::parse($record->date)->toDateString(),
            'new_clock_in' => $clockIn,
            'new_clock_out' => $clockOut,
            'comment' => $comment,
            'status' => $status,
            'approved_at' => $status === Application::STATUS_APPROVED ? now() : null,
        ]);

        foreach ($breaks as [$in, $out]) {
            $application->breaks()->create(['new_break_in' => $in, 'new_break_out' => $out]);
        }

        return $application;
    }

    /** 一覧表のセル（<p class="table__description--item">値</p>）のHTML */
    protected function cellHtml(string $value): string
    {
        return '<p class="table__description--item">'.$value.'</p>';
    }

    /**
     * 勤怠詳細の修正フォームに送る、正しい入力値一式。各テストで1項目だけ上書きして使う。
     *
     * @return array<string, mixed>
     */
    protected function correctionInput(array $override = []): array
    {
        return array_merge([
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => [0 => '12:15'],
            'new_break_out' => [0 => '13:15'],
            'comment' => '電車遅延のため',
        ], $override);
    }

    /** 勤怠詳細画面から修正申請（保存処理）を行う */
    protected function submitCorrection(AttendanceRecord $record, array $override = []): TestResponse
    {
        return $this->post('/attendance/'.$record->id, $this->correctionInput($override));
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
