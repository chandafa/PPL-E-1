<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    use HasFactory;

    protected $table = 'contracts';

    protected $primaryKey = 'contract_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'contract_id',
        'project_id',
        'contract_number',
        'contract_value',
        'created_by',
    ];

    protected $casts = [
        'contract_value' => 'decimal:2',
    ];

    public function termins(): HasMany
    {
        return $this->hasMany(Termin::class, 'contract_id', 'contract_id');
    }

    public function taxParameters(): HasMany
    {
        return $this->hasMany(TaxParameter::class, 'contract_id', 'contract_id');
    }
}
