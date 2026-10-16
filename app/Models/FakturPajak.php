<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * ENT-KE01-01 FakturPajak: faktur dan statusnya.
 *
 * "Aktif" untuk keunikan invoice (Q-04) berbeda dari "dihitung" pada rekap (Q-13).
 * Rekap hanya menghitung NORMAL yang belum digantikan; REPLACEMENT tidak dihitung.
 */
class FakturPajak extends Model
{
    use HasUuids;

    public const TYPE_OUTGOING = 'OUTGOING';

    public const TYPE_INCOMING = 'INCOMING';

    public const STATUS_NORMAL = 'NORMAL';

    public const STATUS_REPLACEMENT = 'REPLACEMENT';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $table = 'faktur_pajak';

    protected $primaryKey = 'tax_invoice_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tax_invoice_date' => 'date:Y-m-d',
            'dpp' => 'decimal:2',
            'ppn' => 'decimal:2',
            'ppnbm' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemFaktur::class, 'tax_invoice_id', 'tax_invoice_id')->orderBy('line_no');
    }

    /** Faktur asal yang digantikan faktur ini. */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_tax_invoice_id', 'tax_invoice_id');
    }

    /** Faktur pengganti yang menggantikan faktur ini (bila ada). */
    public function replacedBy(): HasOne
    {
        return $this->hasOne(self::class, 'replaces_tax_invoice_id', 'tax_invoice_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RiwayatPerubahanFaktur::class, 'tax_invoice_id', 'tax_invoice_id')
            ->orderBy('changed_at')->orderBy('audit_id');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Faktur aktif (berlaku): bukan CANCELLED dan belum digantikan faktur lain.
     * Dipakai untuk aturan satu invoice : satu faktur aktif (Q-04).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('status'), '<>', self::STATUS_CANCELLED)
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from($this->getTable().' as successor')
                    ->whereColumn('successor.replaces_tax_invoice_id', $this->qualifyColumn('tax_invoice_id'));
            });
    }

    /** Apakah faktur ini sudah digantikan faktur pengganti. */
    public function isReplaced(): bool
    {
        return self::query()->where('replaces_tax_invoice_id', $this->tax_invoice_id)->exists();
    }

    /** Q-13 Pertemuan 3 (tertutup): batal dan pengganti tidak dijumlahkan. */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->active()
            ->where($this->qualifyColumn('status'), self::STATUS_NORMAL)
            ->where($this->qualifyColumn('type'), self::TYPE_OUTGOING);
    }
}
