<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithAttendance;
use Tests\TestCase;

/**
 * テストケース一覧 No.19：★ Sanctum 認証
 */
class SanctumAuthTest extends TestCase
{
    use InteractsWithAttendance;
    use RefreshDatabase;

    /** 未認証時に書き込み系 API で 401 が返る */
    public function test_write_apis_return_401_without_authentication(): void
    {
        $record = $this->createRecord(User::factory()->create(), '2026-10-10');
        $url = '/api/v1/attendance-records';

        $this->postJson($url, ['date' => '2026-10-12', 'clock_in' => '09:00:00'])
            ->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $this->putJson("$url/{$record->id}", ['comment' => 'x'])
            ->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);
        $this->deleteJson("$url/{$record->id}")
            ->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.']);

        // 何も変更されていない
        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'comment' => null]);
    }

    /** 認証済みユーザーは自分の勤怠を更新・削除できる */
    public function test_user_can_update_and_delete_own_record(): void
    {
        $user = User::factory()->create();
        $record = $this->createRecord($user, '2026-10-10');
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/attendance-records/{$record->id}", ['comment' => '自分で更新'])
            ->assertOk();
        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'comment' => '自分で更新']);

        $this->deleteJson("/api/v1/attendance-records/{$record->id}")->assertNoContent();
        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
    }

    /** 他ユーザーの勤怠を更新・削除しようとすると 403 が返る */
    public function test_user_cannot_update_or_delete_others_record(): void
    {
        $owner = User::factory()->create();
        $record = $this->createRecord($owner, '2026-10-10');
        Sanctum::actingAs(User::factory()->create());
        $forbidden = ['error' => 'この操作を実行する権限がありません。'];

        $this->putJson("/api/v1/attendance-records/{$record->id}", ['comment' => '他人が更新'])
            ->assertForbidden()->assertExactJson($forbidden);
        $this->deleteJson("/api/v1/attendance-records/{$record->id}")
            ->assertForbidden()->assertExactJson($forbidden);

        // 勤怠は残り、内容も変わらない
        $this->assertDatabaseHas('attendance_records', ['id' => $record->id, 'comment' => null]);
    }

    /** 補足：管理者は他ユーザーの勤怠も更新・削除できる（Policy の before） */
    public function test_admin_can_update_and_delete_others_record(): void
    {
        $record = $this->createRecord(User::factory()->create(), '2026-10-10');
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson("/api/v1/attendance-records/{$record->id}", ['comment' => '管理者が更新'])->assertOk();
        $this->deleteJson("/api/v1/attendance-records/{$record->id}")->assertNoContent();
        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);
    }
}
