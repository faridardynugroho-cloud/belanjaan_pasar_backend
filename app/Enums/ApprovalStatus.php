<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case Pending = 'pending';
    case Revision = 'revision';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Revision => 'Perlu Perbaikan',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
        };
    }
}
