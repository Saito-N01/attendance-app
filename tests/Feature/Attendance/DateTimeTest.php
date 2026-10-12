<?php

namespace Tests\Feature\Attendance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.4：日時取得機能
 */
class DateTimeTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    /** 現在の日時情報がUIと同じ形式で出力されている */
    public function test_current_date_and_time_are_displayed_in_ui_format(): void
    {
        // 月曜日の朝9:05に固定し、画面の表示形式（YYYY年M月D日(曜日)／HH:mm）と突き合わせる
        $this->setNow('2026-10-12 09:05:00');
        $this->loginAsUser();

        $response = $this->get('/attendance');

        $response->assertOk();
        $response->assertSee('value="2026年10月12日(月)"', false);
        $response->assertSee('value="09:05"', false);
    }
}
