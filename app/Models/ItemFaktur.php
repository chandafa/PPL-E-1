<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * ENT-KE01-02 ItemFaktur: rincian barang/jasa hasil ekstraksi PDF e-Faktur.
 */
class ItemFaktur extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'item_faktur';

    protected $primaryKey = 'tax_invoice_item_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'line_no' => 'integer',
            'sale_replacement_advance_term_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'received_advance_amount' => 'decimal:2',
        ];
    }
}
