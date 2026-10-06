<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AttendanceReportController extends Controller
{
    private const MONTHS = 6;             // 当月を含む直近6ヶ月

    private const STANDARD_MINUTES = 480; // 所定労働時間（8時間/日）

    private const LONG_WORK_MINUTES = 600; // 長時間労働の基準（10時間超）

    private const WORK_START = '09:00';

    private const WORK_END = '18:00';

    public function index(): View
    {
        $today = Carbon::today();
        $months = $this->targetMonths($today);

        $records = AttendanceRecord::with('breaks')
            ->where('user_id', auth()->id())
            ->whereBetween('date', [
                $months->first()->toDateString(),
                $today->copy()->endOfMonth()->toDateString(),
            ])
            ->get();

        return view('reports.index', [
            'summary' => $this->summary($records),
            'monthlyTrend' => $this->monthlyTrend($records, $months),
            'anomalies' => $this->anomalies($records, $today),
        ]);
    }

    /**
     * 対象月（古い順）の月初日
     *
     * @return Collection<int, Carbon>
     */
    private function targetMonths(Carbon $today): Collection
    {
        return collect(range(self::MONTHS - 1, 0))
            ->map(fn (int $ago): Carbon => $today->copy()->startOfMonth()->subMonthsNoOverflow($ago));
    }

    /**
     * 基本サマリー
     *
     * @param  Collection<int, AttendanceRecord>  $records
     * @return array{total_work_minutes: int, total_overtime_minutes: int, avg_work_minutes: int}
     */
    private function summary(Collection $records): array
    {
        $completed = $this->completed($records);
        $total = $completed->sum('work_minutes');

        return [
            'total_work_minutes' => $total,
            'total_overtime_minutes' => $completed->sum(fn (AttendanceRecord $r): int => $this->overtimeMinutes($r)),
            'avg_work_minutes' => $completed->isEmpty() ? 0 : (int) round($total / $completed->count()),
        ];
    }

    /**
     * 月次推移。データの無い月は 0
     *
     * @param  Collection<int, AttendanceRecord>  $records
     * @param  Collection<int, Carbon>  $months
     * @return Collection<int, array{month: string, work_minutes: int, overtime_minutes: int}>
     */
    private function monthlyTrend(Collection $records, Collection $months): Collection
    {
        $byMonth = $this->completed($records)
            ->groupBy(fn (AttendanceRecord $r): string => $r->date->format('Y-m'));

        return $months->map(function (Carbon $month) use ($byMonth): array {
            $monthRecords = $byMonth->get($month->format('Y-m'), collect());

            return [
                'month' => $month->format('Y/m'),
                'work_minutes' => $monthRecords->sum('work_minutes'),
                'overtime_minutes' => $monthRecords->sum(fn (AttendanceRecord $r): int => $this->overtimeMinutes($r)),
            ];
        });
    }

    /**
     * 当月の異常検知
     *
     * @param  Collection<int, AttendanceRecord>  $records
     * @return array{late_count: int, early_leave_count: int, long_work_count: int}
     */
    private function anomalies(Collection $records, Carbon $today): array
    {
        $thisMonth = $records->filter(fn (AttendanceRecord $r): bool => $r->date->format('Y-m') === $today->format('Y-m'));

        return [
            'late_count' => $thisMonth
                ->filter(fn (AttendanceRecord $r): bool => $r->clock_in && $this->hhmm($r->clock_in) > self::WORK_START)
                ->count(),
            'early_leave_count' => $thisMonth
                ->filter(fn (AttendanceRecord $r): bool => $r->clock_out && $this->hhmm($r->clock_out) < self::WORK_END)
                ->count(),
            'long_work_count' => $thisMonth
                ->filter(fn (AttendanceRecord $r): bool => $r->work_minutes > self::LONG_WORK_MINUTES)
                ->count(),
        ];
    }

    // 退勤済み（労働時間が確定している）勤怠だけに絞る
    private function completed(Collection $records): Collection
    {
        return $records->filter(fn (AttendanceRecord $r): bool => ! is_null($r->work_minutes));
    }

    // 1日の所定（8時間）を超えた分。超えなければ 0
    private function overtimeMinutes(AttendanceRecord $record): int
    {
        return max($record->work_minutes - self::STANDARD_MINUTES, 0);
    }

    // "09:30:00" → "09:30"（文字列のまま大小比較できる形に揃える）
    private function hhmm(string $time): string
    {
        return Carbon::parse($time)->format('H:i');
    }
}
