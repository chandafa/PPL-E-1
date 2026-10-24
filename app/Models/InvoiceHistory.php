<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceHistory extends Model
{
    use HasFactory;

    protected $table = 'invoice_histories';

    protected $fillable = [
        'invoice_id',
        'status',
        'changed_at',
        'changed_by',
        'note',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }
}
