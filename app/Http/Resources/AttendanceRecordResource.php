<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * 勤怠1件分のJSON表現。一覧API（index）と詳細API（show）で共用する。
 *
 * @mixin \App\Models\AttendanceRecord
 */
class AttendanceRecordResource extends JsonResource
{
    /**
     * breaks をレスポンスに含めるか。一覧API（index）では false にする。
     */
    private bool $includeBreaks = true;

    /**
     * breaks をレスポンスに出さない（一覧API用）。
     * total_time / total_break_time の計算には breaks が必要なので、
     * 呼び出し側では eager load しておき（N+1 防止）、出力だけをここで止める。
     */
    public function withoutBreaks(): static
    {
        $this->includeBreaks = false;

        return $this;
    }

    /**
     * 関連（user / breaks / applications）は eager load されているときだけ出力する。
     * whenLoaded() を使うことで、Resource の中から勝手に追加クエリ（N+1）が走らない。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'date' => $this->date->toDateString(),
            'clock_in' => $this->clock_in,
            'clock_out' => $this->clock_out,
            'total_time' => $this->toHourMinute($this->total_time),
            'total_break_time' => $this->toHourMinute($this->total_break_time),
            'comment' => $this->comment,
            'breaks' => $this->when(
                $this->includeBreaks,
                fn () => AttendanceBreakResource::collection($this->whenLoaded('breaks'))
            ),
            'applications' => ApplicationResource::collection($this->whenLoaded('applications')),
        ];
    }

    /**
     * "08:00:00" → "08:00"（API仕様書 AP01 のレスポンス例の形式）。null はそのまま返す。
     */
    private function toHourMinute(?string $time): ?string
    {
        return $time === null ? null : Str::beforeLast($time, ':');
    }
}
