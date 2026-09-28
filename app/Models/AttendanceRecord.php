<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
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

    /**
     * 承認待ちの修正申請のみ。
     */
    public function pendingApplication(): HasOne
    {
        return $this->hasOne(Application::class)
            ->where('status', ApplicationStatus::Pending);
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
}
