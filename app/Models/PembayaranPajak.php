<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ENT-KE01-04 PembayaranPajak: jumlah setor, billing, NTPN, e-Billing, dan
 * bukti pembayaran per masa pajak. CONFIRMED hanya bila NTPN dan bukti ada (Q-10).
 */
class PembayaranPajak extends Model
{
    use HasUuids;

    public const STATUS_INCOMPLETE = 'INCOMPLETE';

    public const STATUS_CONFIRMED = 'CONFIRMED';

    protected $table = 'pembayaran_pajak';

    protected $primaryKey = 'tax_payment_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public function history(): HasMany
    {
        return $this->hasMany(RiwayatPerubahanPembayaran::class, 'tax_payment_id', 'tax_payment_id')
            ->orderBy('changed_at')->orderBy('audit_id');
    }

    protected function casts(): array
    {
        return [
            'tax_year' => 'integer',
            'amount_paid' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
