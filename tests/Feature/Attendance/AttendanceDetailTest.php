<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.10：勤怠詳細情報取得機能（一般ユーザー）
 */
class AttendanceDetailTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    private AttendanceRecord $record;

    /** 手順1・2：勤怠情報が登録されたユーザーにログインし、その日の勤怠詳細ページを開ける状態にする */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setNow('2026-10-15 10:00:00');
        $user = $this->loginAsUser();
        $user->update(['name' => '山田 花子']);

        $this->record = $this->createRecord($user, '2026-10-10', '09:12', '18:34', [
            ['12:00', '12:45'],
            ['15:00', '15:10'],
        ]);
    }

    /** 勤怠詳細画面の「名前」がログインユーザーの氏名になっている */
    public function test_name_is_the_logged_in_users_name(): void
    {
        $this->get('/attendance/'.$this->record->id)
            ->assertOk()
            ->assertSee('name="name" value="山田 花子"', false);
    }

    /** 勤怠詳細画面の「日付」が選択した日付になっている */
    public function test_date_is_the_selected_date(): void
    {
        $this->get('/attendance/'.$this->record->id)
            ->assertOk()
            ->assertSee('value="2026年"', false)
            ->assertSee('value="10月10日"', false);
    }

    /** 「出勤・退勤」にて記されている時間がログインユーザーの打刻と一致している */
    public function test_clock_in_and_out_match_the_stamps(): void
    {
        $this->get('/attendance/'.$this->record->id)
            ->assertOk()
            ->assertSee('name="new_clock_in" value="09:12"', false)
            ->assertSee('name="new_clock_out" value="18:34"', false);
    }

    /** 「休憩」にて記されている時間がログインユーザーの打刻と一致している */
    public function test_breaks_match_the_stamps(): void
    {
        $this->get('/attendance/'.$this->record->id)
            ->assertOk()
            // 1回目の休憩（ラベルは「休憩」）
            ->assertSee('name="new_break_in[0]" value="12:00"', false)
            ->assertSee('name="new_break_out[0]" value="12:45"', false)
            // 2回目の休憩（ラベルは「休憩2」）
            ->assertSee('休憩2')
            ->assertSee('name="new_break_in[1]" value="15:00"', false)
            ->assertSee('name="new_break_out[1]" value="15:10"', false);
    }

    /**
     * 【追加】他のユーザーの勤怠詳細は開けない（404）。
     * 勤怠詳細のURLは連番のIDなので、IDを書き換えられても他人の勤怠が見えないことの確認。
     */
    public function test_other_users_attendance_detail_is_not_accessible(): void
    {
        $other = $this->createRecord(User::factory()->create(), '2026-10-10', '07:07', '16:16');

        $this->get('/attendance/'.$other->id)->assertNotFound();
    }
}
