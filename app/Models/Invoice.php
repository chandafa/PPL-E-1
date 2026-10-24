<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    protected $primaryKey = 'invoice_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'invoice_id',
        'invoice_number',
        'termin_id',
        'tax_param_id',
        'invoice_date',
        'dpp_amount',
        'ppn_amount',
        'pph_amount',
        'total_amount',
        'status',
        'created_by',
    ];

    protected $casts = [
        'dpp_amount' => 'decimal:2',
        'ppn_amount' => 'decimal:2',
        'pph_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'invoice_date' => 'date:Y-m-d',
    ];

    public function termin(): BelongsTo
    {
        return $this->belongsTo(Termin::class, 'termin_id', 'termin_id');
    }

    public function taxParameter(): BelongsTo
    {
        return $this->belongsTo(TaxParameter::class, 'tax_param_id', 'tax_param_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(InvoiceHistory::class, 'invoice_id', 'invoice_id')->orderBy('changed_at', 'asc');
    }
}
