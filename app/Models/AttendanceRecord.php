<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AttendanceRecord extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function pendingApplication(): HasOne
    {
        return $this->hasOne(Application::class)
            ->where('status', Application::STATUS_PENDING);
    }

    protected function status(): Attribute
    {
        return Attribute::make(get: function () {
            if (is_null($this->clock_in)) {
                return '勤務外';
            }
            if (! is_null($this->clock_out)) {
                return '退勤済';
            }
            $onBreak = $this->breaks->contains(fn ($b) => is_null($b->break_out));

            return $onBreak ? '休憩中' : '出勤中';
        });
    }

    // 休憩合計（分）。終了していない休憩は数えない
    private function breakMinutes(): int
    {
        return $this->breaks
            ->filter(fn ($b) => $b->break_in && $b->break_out)
            ->sum(fn ($b) => Carbon::parse($b->break_out)->diffInMinutes(Carbon::parse($b->break_in)));
    }

    // 分を時間表示に変換する
    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    // 出勤していれば休憩合計（休憩なしなら 0:00）
    protected function totalBreakTime(): Attribute
    {
        return Attribute::make(get: fn () => $this->clock_in
            ? $this->minutesToTime($this->breakMinutes())
            : null);
    }

    // 退勤済のときだけ勤務合計（退勤 − 出勤 − 休憩）
    protected function totalTime(): Attribute
    {
        return Attribute::make(get: function () {
            if (! $this->clock_in || ! $this->clock_out) {
                return null;
            }
            $worked = Carbon::parse($this->clock_out)->diffInMinutes(Carbon::parse($this->clock_in));

            return $this->minutesToTime($worked - $this->breakMinutes());
        });
    }
}
