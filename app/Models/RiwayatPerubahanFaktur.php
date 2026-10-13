<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * ENT-KE01-05 RiwayatPerubahanFaktur: jejak append-only (satu baris = satu
 * perubahan). Pembaruan dan penghapusan ditolak trigger basis data.
 */
class RiwayatPerubahanFaktur extends Model
{
    use HasUuids;

    public const ACTION_CREATE = 'CREATE';

    public const ACTION_UPDATE = 'UPDATE';

    public const ACTION_DELETE = 'DELETE';

    public const ACTION_STATUS_CHANGE = 'STATUS_CHANGE';

    public $timestamps = false;

    protected $table = 'riwayat_perubahan_faktur';

    protected $primaryKey = 'audit_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'changed_at' => 'datetime',
        ];
    }
}
