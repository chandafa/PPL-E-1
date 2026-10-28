<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Riwayat pembayaran append-only; snapshot menyimpan referensi bukti setiap versi. */
class RiwayatPerubahanPembayaran extends Model
{
    use HasUuids;

    public const ACTION_CREATE = 'CREATE';

    public const ACTION_COMPLETE = 'COMPLETE';

    public const ACTION_CORRECT = 'CORRECT';

    public $timestamps = false;

    protected $table = 'riwayat_perubahan_pembayaran';

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
