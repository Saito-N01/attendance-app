<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    public const STATUS_PENDING = 0;   // 承認待ち

    public const STATUS_APPROVED = 1;  // 承認済み

    /**
     * @var list<string>
     */
    protected $fillable = [
        'attendance_record_id',
        'user_id',
        'date',
        'new_clock_in',
        'new_clock_out',
        'comment',
        'status',
        'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected $casts = [
        'date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(ApplicationBreak::class)->orderBy('id');
    }

    protected function approvalStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === self::STATUS_PENDING ? '承認待ち' : '承認済み',
        );
    }

    protected function applicationDate(): Attribute
    {
        return Attribute::make(get: fn () => $this->created_at);
    }
}
