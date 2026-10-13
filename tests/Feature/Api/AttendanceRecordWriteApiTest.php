<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.18：★ 公開API 書き込み系（Sanctum 認証済みで実行）
 */
class AttendanceRecordWriteApiTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** POST /api/v1/attendance-records で勤怠が作成される */
    public function test_store_creates_a_record(): void
    {
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-10-12',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'API経由で登録',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.date', '2026-10-12')
            ->assertJsonPath('data.user_id', $this->user->id);

        // user_id はボディではなく認証ユーザーから付く
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $this->user->id,
            'clock_in' => '09:00:00',
            'comment' => 'API経由で登録',
        ]);
    }

    /** バリデーションエラー時に 422 と日本語エラーメッセージが返る */
    public function test_store_returns_422_with_japanese_errors(): void
    {
        // 必須項目（date / clock_in）が欠落
        $response = $this->postJson('/api/v1/attendance-records', ['comment' => '日付なし']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'clock_in'])
            ->assertJsonPath('errors.date.0', '勤怠日は必須です。')
            ->assertJsonPath('errors.clock_in.0', '出勤時刻は必須です。');
        $this->assertDatabaseCount('attendance_records', 0);
    }

    /** PUT /api/v1/attendance-records/{id} で勤怠が更新される */
    public function test_update_changes_the_record(): void
    {
        $record = $this->createRecord($this->user, '2026-10-10', '09:00', '18:00');

        $response = $this->putJson('/api/v1/attendance-records/'.$record->id, [
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
            'comment' => '修正しました',
        ]);

        $response->assertOk()->assertJsonPath('data.clock_in', '09:30:00');
        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'clock_in' => '09:30:00',
            'clock_out' => '18:30:00',
            'comment' => '修正しました',
        ]);
    }

    /** 存在しない ID に対する PUT は 404 */
    public function test_update_returns_404_for_unknown_id(): void
    {
        $this->putJson('/api/v1/attendance-records/99999', ['comment' => 'x'])
            ->assertNotFound()
            ->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }

    /** DELETE /api/v1/attendance-records/{id} で勤怠が削除される */
    public function test_destroy_deletes_the_record(): void
    {
        $record = $this->createRecord($this->user, '2026-10-10');

        $this->deleteJson('/api/v1/attendance-records/'.$record->id)->assertNoContent();

        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
    }

    /** 存在しない ID に対する DELETE は 404 */
    public function test_destroy_returns_404_for_unknown_id(): void
    {
        $this->deleteJson('/api/v1/attendance-records/99999')
            ->assertNotFound()
            ->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }
}
