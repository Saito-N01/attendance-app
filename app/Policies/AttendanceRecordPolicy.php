<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

/**
 * 勤怠（AttendanceRecord）の更新・削除の認可（公開API用）。
 *
 * 本人、または管理者（admin_status = true）だけが更新・削除できる。
 * 「ログインしているか」の確認（認証）は auth:sanctum が担当し、
 * ここでは「その人にその勤怠を操作する権限があるか」（認可）だけを見る。
 */
class AttendanceRecordPolicy
{
    /**
     * 他のメソッド（update / delete）より先に必ず呼ばれる。
     * 戻り値の意味: true = 無条件で許可 / false = 無条件で拒否 / null = 各メソッドに判断を任せる
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->admin_status ? true : null;
    }

    /**
     * 勤怠の更新: 本人のみ（管理者は before() で許可済み）
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * 勤怠の削除: 本人のみ（管理者は before() で許可済み）
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }
}
