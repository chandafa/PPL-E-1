<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxParameter extends Model
{
    use HasFactory;

    protected $table = 'tax_parameters';

    protected $primaryKey = 'tax_param_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tax_param_id',
        'contract_id',
        'ppn_rate',
        'pph_rate',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'ppn_rate' => 'decimal:2',
        'pph_rate' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'tax_param_id', 'tax_param_id');
    }
}
