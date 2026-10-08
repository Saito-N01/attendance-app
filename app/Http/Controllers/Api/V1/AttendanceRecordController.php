<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 公開API v1：勤怠（attendance-records）
 *
 * 画面用の App\Http\Controllers\AttendanceRecordController とは別物。
 * 画面用は Blade（HTML）を返し、こちらは JSON を返す。
 */
class AttendanceRecordController extends Controller
{
    /**
     * 勤怠一覧（AP01）。絞り込み・per_page・FormRequest は [API-02] で追加する。
     */
    public function index(): AnonymousResourceCollection
    {
        // breaks は total_time / total_break_time の計算に使うため eager load する（N+1 防止）
        $attendanceRecords = AttendanceRecord::with(['user', 'breaks'])
            ->latest('date')
            ->paginate(20);

        $resources = AttendanceRecordResource::collection($attendanceRecords);

        // ただし一覧のレスポンスには breaks を出さない（詳細APIのみ）
        $resources->collection->each(
            fn (AttendanceRecordResource $resource) => $resource->withoutBreaks()
        );

        return $resources;
    }

    /**
     * 勤怠詳細（AP02）。存在しない ID はルートモデルバインディングが失敗し、
     * Exception Handler が 404 の JSON に変換する。
     */
    public function show(AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $attendanceRecord->load(['user', 'breaks', 'applications']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 勤怠登録（AP03）。[API-03] で実装する。
     */
    public function store(Request $request): JsonResponse
    {
        abort(501, 'Not Implemented');
    }

    /**
     * 勤怠更新（AP04）。[API-03] で実装し、認可は [API-04] で追加する。
     */
    public function update(Request $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        abort(501, 'Not Implemented');
    }

    /**
     * 勤怠削除（AP05）。[API-03] で実装し、認可は [API-04] で追加する。
     */
    public function destroy(AttendanceRecord $attendanceRecord): JsonResponse
    {
        abort(501, 'Not Implemented');
    }
}
