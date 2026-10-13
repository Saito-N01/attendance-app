<?php

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.17：公開API 読み取り系（認証不要）
 */
class AttendanceRecordReadApiTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    /** GET /api/v1/attendance-records で勤怠一覧が JSON で取得できる */
    public function test_index_returns_records_with_pagination_meta(): void
    {
        $user = User::factory()->create(['name' => '山田 花子']);
        foreach (['2026-10-01', '2026-10-02', '2026-10-03'] as $date) {
            $this->createRecord($user, $date);
        }

        // 認証なし（ゲスト）で呼べる。per_page=2 にして「ページ分割」まで確かめる
        $response = $this->getJson('/api/v1/attendance-records?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            // 新しい日付が先頭。一覧にはユーザー名が含まれ、休憩の明細は含まれない
            ->assertJsonPath('data.0.date', '2026-10-03')
            ->assertJsonPath('data.0.user.name', '山田 花子')
            ->assertJsonMissingPath('data.0.breaks');
    }

    /** GET /api/v1/attendance-records/{id} で勤怠詳細（ユーザー・休憩・修正申請を含む）が取得できる */
    public function test_show_returns_detail_with_user_breaks_and_applications(): void
    {
        $user = User::factory()->create(['name' => '山田 花子']);
        $record = $this->createRecord($user, '2026-10-10', '09:00', '18:00', [['12:00', '13:00']]);
        $this->createCorrectionApplication($record, '電車遅延のため', Application::STATUS_PENDING);

        $response = $this->getJson('/api/v1/attendance-records/'.$record->id);

        // 休憩時刻は DB によって 12:00 / 12:00:00 と形式が違うため、先頭の「時:分」で比べる
        $this->assertSame('12:00', substr($response->json('data.breaks.0.break_in'), 0, 5));
        $this->assertSame('13:00', substr($response->json('data.breaks.0.break_out'), 0, 5));

        $response->assertOk()
            ->assertJsonPath('data.id', $record->id)
            ->assertJsonPath('data.date', '2026-10-10')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', '山田 花子')
            ->assertJsonPath('data.total_break_time', '01:00')
            ->assertJsonPath('data.total_time', '08:00')
            ->assertJsonCount(1, 'data.breaks')
            ->assertJsonCount(1, 'data.applications')
            ->assertJsonPath('data.applications.0.comment', '電車遅延のため');
    }

    /** 存在しない ID では 404 とエラー JSON が返る */
    public function test_show_returns_404_json_for_unknown_id(): void
    {
        $this->getJson('/api/v1/attendance-records/99999')
            ->assertNotFound()
            ->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }
}
