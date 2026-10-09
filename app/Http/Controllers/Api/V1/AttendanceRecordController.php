<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * 公開API v1：勤怠（attendance-records）
 *
 * 画面用の App\Http\Controllers\AttendanceRecordController とは別物。
 * 画面用は Blade（HTML）を返し、こちらは JSON を返す。
 */
class AttendanceRecordController extends Controller
{
    /** 1ページあたりの件数の既定値（最大値の100は IndexAttendanceRecordRequest で強制） */
    private const DEFAULT_PER_PAGE = 20;

    /**
     * 勤怠一覧（AP01）。user_id / date / month での絞り込みと、page / per_page でのページネーションに対応する。
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        // breaks は total_time / total_break_time の計算に使うため eager load する（N+1 防止）
        $attendanceRecords = AttendanceRecord::with(['user', 'breaks'])
            ->when(
                $validated['user_id'] ?? null,
                fn (Builder $query, string $userId) => $query->where('user_id', $userId)
            )
            ->when(
                $validated['date'] ?? null,
                fn (Builder $query, string $date) => $query->where('date', $date)
            )
            ->when(
                $validated['month'] ?? null,
                fn (Builder $query, string $month) => $this->whereInMonth($query, $month)
            )
            ->latest('date')
            // 同じ日付の勤怠が複数ある（ユーザーが違う）ため、id で順序を固定し、ページ間の重複・抜けを防ぐ
            ->orderByDesc('id')
            ->paginate($perPage)
            // links に user_id などの絞り込み条件を引き継ぐ
            ->withQueryString();

        $resources = AttendanceRecordResource::collection($attendanceRecords);

        // ただし一覧のレスポンスには breaks を出さない（詳細APIのみ）
        $resources->collection->each(
            fn (AttendanceRecordResource $resource) => $resource->withoutBreaks()
        );

        return $resources;
    }

    /**
     * 'YYYY-MM' の月の1日〜末日で date を絞り込む。
     * 列に関数（YEAR() など）をかけず範囲で比較するので、date の索引を使える。
     *
     * @param  Builder<AttendanceRecord>  $query
     * @return Builder<AttendanceRecord>
     */
    private function whereInMonth(Builder $query, string $month): Builder
    {
        // '!' を付けないと、日が「今日の日」になり、月末に 2月31日 のようなずれが起きる
        $firstDay = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();

        return $query->whereBetween('date', [
            $firstDay->toDateString(),
            $firstDay->copy()->endOfMonth()->toDateString(),
        ]);
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
     * 勤怠登録（AP03）。user_id はリクエストボディではなく、認証ユーザーから自動で付与する。
     */
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        // $request->user()->attendanceRecords()->create() なら user_id が自動で入る
        $attendanceRecord = $request->user()->attendanceRecords()->create($request->validated());

        $attendanceRecord->load(['user', 'breaks']);

        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 勤怠更新（AP04）。送られた項目だけを更新する（部分更新）。
     * 認可（本人または管理者のみ）は [API-04] で追加する。
     */
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $attendanceRecord->update($request->validated());

        $attendanceRecord->load(['user', 'breaks']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 勤怠削除（AP05）。休憩（attendance_breaks）と修正申請（applications）は
     * 外部キーの ON DELETE CASCADE で、DB 側が一緒に削除する。
     * 認可（本人または管理者のみ）は [API-04] で追加する。
     */
    public function destroy(AttendanceRecord $attendanceRecord): Response
    {
        $attendanceRecord->delete();

        return response()->noContent();
    }
}
