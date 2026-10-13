<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * ENT-KE01-03 RekapPajakBulanan: read model per masa pajak, dihitung dari
 * faktur dan pembayaran (bukan input manual). Satu baris per masa pajak.
 */
class RekapPajakBulanan extends Model
{
    use HasUuids;

    public const PAYMENT_NOT_RECORDED = 'NOT_RECORDED';

    public const PAYMENT_INCOMPLETE = 'INCOMPLETE';

    public const PAYMENT_CONFIRMED = 'CONFIRMED';

    public $timestamps = false;

    protected $table = 'rekap_pajak_bulanan';

    protected $primaryKey = 'recap_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'invoice_count' => 'integer',
            'total_dpp' => 'decimal:2',
            'total_ppn' => 'decimal:2',
            'total_ppnbm' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'payment_difference' => 'decimal:2',
            'computed_at' => 'datetime',
        ];
    }
}
