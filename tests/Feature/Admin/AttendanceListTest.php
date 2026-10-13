<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.12：勤怠一覧情報取得機能（管理者）
 */
class AttendanceListTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setNow('2026-10-15 10:00:00'); // 当日は 2026年10月15日
        $this->loginAsAdmin(); // 手順1：管理者ユーザーにログインする
    }

    /** その日になされた全ユーザーの勤怠情報が正確に確認できる */
    public function test_all_users_attendance_of_the_day_are_shown(): void
    {
        $yamada = User::factory()->create(['name' => '山田 花子']);
        $suzuki = User::factory()->create(['name' => '鈴木 次郎']);
        $sato = User::factory()->create(['name' => '佐藤 三郎']);

        $this->createRecord($yamada, '2026-10-15', '09:01', '18:01', [['12:00', '13:00']]);
        $this->createRecord($suzuki, '2026-10-15', '10:02', '19:02');
        $this->createRecord($sato, '2026-10-14', '07:07', '16:16'); // 前日の勤怠（表示されてはいけない）

        $response = $this->get('/admin/attendance/list');

        $response->assertOk();
        // 当日の2人が、名前・出勤・退勤・休憩・合計とともに表示される
        // （山田：9:00 − 休憩1:00 = 8:00／鈴木：休憩なし 0:00、勤務 9:00）
        $response->assertSeeInOrder(array_map($this->cellHtml(...), [
            '山田 花子', '09:01', '18:01', '1:00', '8:00',
            '鈴木 次郎', '10:02', '19:02', '0:00', '9:00',
        ]), false);
        // 前日だけ勤怠があるユーザーは表示されない
        $response->assertDontSee('佐藤 三郎');
        $response->assertDontSee('07:07');
        $response->assertDontSee('16:16');
    }

    /** 遷移した際に現在の日付が表示される */
    public function test_current_date_is_shown(): void
    {
        $this->get('/admin/attendance/list')
            ->assertOk()
            ->assertSee('2026年10月15日の勤怠')
            ->assertSee('<p class="current-day">2026/10/15</p>', false);
    }

    /** 「前日」を押下した時に前の日の勤怠情報が表示される */
    public function test_previous_day_button_shows_previous_day(): void
    {
        $today = User::factory()->create(['name' => '山田 花子']);
        $yesterday = User::factory()->create(['name' => '佐藤 三郎']);
        $this->createRecord($today, '2026-10-15', '09:01', '18:01');
        $this->createRecord($yesterday, '2026-10-14', '07:07', '16:16');

        // 「前日」ボタンのリンク先を確認し、そのリンクを開く
        $this->get('/admin/attendance/list')
            ->assertSee('<a class="previous-day" href="?date=2026-10-14">前日</a>', false);
        $response = $this->get('/admin/attendance/list?date=2026-10-14');

        $response->assertOk();
        $response->assertSee('<p class="current-day">2026/10/14</p>', false);
        $response->assertSeeInOrder(array_map($this->cellHtml(...), ['佐藤 三郎', '07:07', '16:16']), false);
        $response->assertDontSee('山田 花子'); // 当日の勤怠は出ない
    }

    /** 「翌日」を押下した時に次の日の勤怠情報が表示される */
    public function test_next_day_button_shows_next_day(): void
    {
        $today = User::factory()->create(['name' => '山田 花子']);
        $tomorrow = User::factory()->create(['name' => '鈴木 次郎']);
        $this->createRecord($today, '2026-10-15', '09:01', '18:01');
        $this->createRecord($tomorrow, '2026-10-16', '10:16', '19:16');

        $this->get('/admin/attendance/list')
            ->assertSee('<a class="next-day" href="?date=2026-10-16">翌日</a>', false);
        $response = $this->get('/admin/attendance/list?date=2026-10-16');

        $response->assertOk();
        $response->assertSee('<p class="current-day">2026/10/16</p>', false);
        $response->assertSeeInOrder(array_map($this->cellHtml(...), ['鈴木 次郎', '10:16', '19:16']), false);
        $response->assertDontSee('山田 花子');
    }

    /**
     * 【追加】一般ユーザーは管理者の勤怠一覧を開けない（管理者ログインへ戻される）。
     * 全ユーザーの勤怠が見える画面なので、権限の確認として入れている。
     */
    public function test_general_user_cannot_open_admin_list(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/attendance/list')->assertRedirect('/admin/login');
    }
}
