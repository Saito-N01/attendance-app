<?php

namespace App\Http\Resources;

use App\Models\AttendanceBreak;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceBreak
 */
class AttendanceBreakResource extends JsonResource
{
    /**
     * 休憩1回分（休憩入・休憩戻）
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'break_in' => $this->break_in,
            'break_out' => $this->break_out,
        ];
    }
}
