<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceInvoiceItem extends Model
{
    protected $table = 'd2_invoice_items';

    protected $fillable = ['invoice_id', 'line_number', 'code', 'description', 'quantity', 'unit', 'unit_price', 'amount'];

    protected $casts = ['line_number' => 'integer', 'quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];
}
