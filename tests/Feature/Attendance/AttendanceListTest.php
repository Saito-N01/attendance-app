<?php

namespace Tests\Feature\Attendance;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.9：勤怠一覧情報取得機能（一般ユーザー）
 */
class AttendanceListTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setNow('2026-10-15 10:00:00'); // 当月は 2026年10月
    }

    /** 自分が行った勤怠情報が全て表示されている */
    public function test_all_of_my_attendance_records_are_shown(): void
    {
        $user = $this->loginAsUser();
        $this->createRecord($user, '2026-10-01', '09:01', '18:01');
        $this->createRecord($user, '2026-10-05', '09:05', '18:05', [['12:00', '13:00']]);
        $this->createRecord($user, '2026-10-10', '09:10', '18:10');

        // 他のユーザーの勤怠（表示されてはいけない）
        $this->createRecord(User::factory()->create(), '2026-10-03', '07:07', '16:16');

        $response = $this->get('/attendance/list');

        $response->assertOk();
        // 自分の3件が、日付順に出勤・退勤とともに表示される
        $response->assertSeeInOrder([
            '10/01(木)', '09:01', '18:01',
            '10/05(月)', '09:05', '18:05',
            '10/10(土)', '09:10', '18:10',
        ], false);
        // 休憩・合計も表示される（10/05：休憩1:00、勤務 9:00 − 1:00 = 8:00）
        $response->assertSee('<p class="table__description--item">1:00</p>', false);
        $response->assertSee('<p class="table__description--item">8:00</p>', false);
        // 他のユーザーの勤怠は表示されない
        $response->assertDontSee('07:07');
        $response->assertDontSee('16:16');
    }

    /** 勤怠一覧画面に遷移した際に現在の月が表示される */
    public function test_current_month_is_shown(): void
    {
        $this->loginAsUser();

        $this->get('/attendance/list')
            ->assertOk()
            ->assertSee('<p class="current-month">2026/10</p>', false);
    }

    /** 「前月」を押下した時に表示月の前月の情報が表示される */
    public function test_previous_month_button_shows_previous_month(): void
    {
        $user = $this->loginAsUser();
        $this->createRecord($user, '2026-09-20', '09:20', '18:20'); // 前月の勤怠
        $this->createRecord($user, '2026-10-10', '08:10', '17:10'); // 当月の勤怠

        // 「前月」ボタンのリンク先を確認し、そのリンクを開く
        $this->get('/attendance/list')
            ->assertSee('<a class="previous-month" href="?date=2026-09">前月</a>', false);
        $response = $this->get('/attendance/list?date=2026-09');

        $response->assertOk();
        $response->assertSee('<p class="current-month">2026/09</p>', false);
        $response->assertSeeInOrder(['09/20(日)', '09:20', '18:20'], false);
        $response->assertDontSee('08:10'); // 当月の勤怠は出ない
    }

    /** 「翌月」を押下した時に表示月の翌月の情報が表示される（シートの「前月」は誤記） */
    public function test_next_month_button_shows_next_month(): void
    {
        $user = $this->loginAsUser();
        $this->createRecord($user, '2026-11-02', '09:02', '18:02'); // 翌月の勤怠
        $this->createRecord($user, '2026-10-10', '08:10', '17:10'); // 当月の勤怠

        $this->get('/attendance/list')
            ->assertSee('<a class="next-month" href="?date=2026-11">翌月</a>', false);
        $response = $this->get('/attendance/list?date=2026-11');

        $response->assertOk();
        $response->assertSee('<p class="current-month">2026/11</p>', false);
        $response->assertSeeInOrder(['11/02(月)', '09:02', '18:02'], false);
        $response->assertDontSee('08:10');
    }

    /** 「詳細」を押下すると、その日の勤怠詳細画面に遷移する */
    public function test_detail_link_opens_that_days_detail_page(): void
    {
        $user = $this->loginAsUser();
        $record = $this->createRecord($user, '2026-10-10', '09:10', '18:10');

        // その日の行に「詳細」リンクがある
        $this->get('/attendance/list')
            ->assertSee('/attendance/'.$record->id.'">詳細</a>', false);

        // リンク先を開くと、その日の勤怠詳細画面が表示される
        $this->get('/attendance/'.$record->id)
            ->assertOk()
            ->assertSee('勤怠詳細')
            ->assertSee('value="10月10日"', false);
    }
}
