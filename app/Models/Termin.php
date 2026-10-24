<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Termin extends Model
{
    use HasFactory;

    protected $table = 'termins';

    protected $primaryKey = 'termin_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'termin_id',
        'contract_id',
        'termin_number',
        'termin_value',
        'due_date',
        'status',
    ];

    protected $casts = [
        'termin_number' => 'integer',
        'termin_value' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'contract_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class, 'termin_id', 'termin_id');
    }
}
