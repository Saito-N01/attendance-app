<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 勤怠1件分のJSON表現。一覧API（index）と詳細API（show）で共用する。
 *
 * @mixin \App\Models\AttendanceRecord
 */
class AttendanceRecordResource extends JsonResource
{
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
            'total_time' => $this->total_time,
            'total_break_time' => $this->total_break_time,
            'comment' => $this->comment,
            'breaks' => AttendanceBreakResource::collection($this->whenLoaded('breaks')),
            'applications' => ApplicationResource::collection($this->whenLoaded('applications')),
        ];
    }
}
