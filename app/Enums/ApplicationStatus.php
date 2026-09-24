<?php

namespace App\Enums;

/**
 * 修正申請（applications.status）のステータス。
 */
enum ApplicationStatus: int
{
    case Pending = 0;  // 承認待ち
    case Approved = 1; // 承認済み

    public function label(): string
    {
        return match ($this) {
            self::Pending => '承認待ち',
            self::Approved => '承認済み',
        };
    }
}
