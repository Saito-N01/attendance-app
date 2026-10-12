<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.14：ユーザー情報取得機能（管理者）
 */
class StaffTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    private User $admin;

    private User $yamada;

    private User $suzuki;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setNow('2026-10-15 10:00:00'); // 当月は 2026年10月
        $this->admin = $this->loginAsAdmin();
        $this->yamada = User::factory()->create(['name' => '山田 花子', 'email' => 'yamada@example.com']);
        $this->suzuki = User::factory()->create(['name' => '鈴木 次郎', 'email' => 'suzuki@example.com']);
    }

    /** 管理者ユーザーが全一般ユーザーの「氏名」「メールアドレス」を確認できる */
    public function test_staff_list_shows_name_and_email_of_all_general_users(): void
    {
        $this->get('/admin/staff/list')
            ->assertOk()
            ->assertSeeInOrder(array_map($this->cellHtml(...), [
                '山田 花子', 'yamada@example.com',
                '鈴木 次郎', 'suzuki@example.com',
            ]), false)
            // 管理者自身は一般ユーザーではないので表示されない
            ->assertDontSee($this->admin->email);
    }

    /** ユーザーの勤怠情報が正しく表示される */
    public function test_staff_monthly_attendance_is_shown(): void
    {
        $this->createRecord($this->yamada, '2026-10-01', '09:01', '18:01');
        $this->createRecord($this->yamada, '2026-10-05', '09:05', '18:05', [['12:00', '13:00']]);
        $this->createRecord($this->suzuki, '2026-10-03', '07:07', '16:16'); // 他のユーザーの勤怠

        // スタッフ一覧のリンクから、選択したユーザーの勤怠一覧を開く
        $this->get('/admin/staff/list')
            ->assertSee('/admin/attendance/staff/'.$this->yamada->id.'">詳細</a>', false);
        $response = $this->get('/admin/attendance/staff/'.$this->yamada->id);

        $response->assertOk();
        $response->assertSee('山田 花子さんの勤怠');
        $response->assertSee('<p class="current-month">2026/10</p>', false);
        $response->assertSeeInOrder(array_map($this->cellHtml(...), [
            '10/01(木)', '09:01', '18:01',
            '10/05(月)', '09:05', '18:05', '1:00', '8:00', // 勤務 9:00 − 休憩 1:00 = 8:00
        ]), false);
        $response->assertDontSee('07:07'); // 他のユーザーの勤怠は出ない
    }

    /** 「前月」を押下した時に表示月の前月の情報が表示される */
    public function test_previous_month_button_shows_previous_month(): void
    {
        $this->createRecord($this->yamada, '2026-09-20', '09:20', '18:20');
        $this->createRecord($this->yamada, '2026-10-10', '08:10', '17:10');
        $url = '/admin/attendance/staff/'.$this->yamada->id;

        $this->get($url)->assertSee('<a class="previous-month" href="?date=2026-09">前月</a>', false);
        $response = $this->get($url.'?date=2026-09');

        $response->assertOk();
        $response->assertSee('<p class="current-month">2026/09</p>', false);
        $response->assertSeeInOrder(array_map($this->cellHtml(...), ['09/20(日)', '09:20', '18:20']), false);
        $response->assertDontSee('08:10');
    }

    /** 「翌月」を押下した時に表示月の翌月の情報が表示される（シートの「前月」は誤記） */
    public function test_next_month_button_shows_next_month(): void
    {
        $this->createRecord($this->yamada, '2026-11-02', '09:02', '18:02');
        $this->createRecord($this->yamada, '2026-10-10', '08:10', '17:10');
        $url = '/admin/attendance/staff/'.$this->yamada->id;

        $this->get($url)->assertSee('<a class="next-month" href="?date=2026-11">翌月</a>', false);
        $response = $this->get($url.'?date=2026-11');

        $response->assertOk();
        $response->assertSee('<p class="current-month">2026/11</p>', false);
        $response->assertSeeInOrder(array_map($this->cellHtml(...), ['11/02(月)', '09:02', '18:02']), false);
        $response->assertDontSee('08:10');
    }

    /** 「詳細」を押下すると、その日の勤怠詳細画面に遷移する */
    public function test_detail_link_opens_that_days_detail_page(): void
    {
        $record = $this->createRecord($this->yamada, '2026-10-10', '09:10', '18:10');

        $this->get('/admin/attendance/staff/'.$this->yamada->id)
            ->assertSee('/attendance/'.$record->id.'">詳細</a>', false);

        // 管理者が /attendance/{id} を開くと、管理者用の勤怠詳細画面へ遷移する
        $this->get('/attendance/'.$record->id)
            ->assertRedirect('/admin/attendance/'.$record->id);

        $this->get('/admin/attendance/'.$record->id)
            ->assertOk()
            ->assertSee('勤怠詳細')
            ->assertSee('value="10月10日"', false);
    }
}
