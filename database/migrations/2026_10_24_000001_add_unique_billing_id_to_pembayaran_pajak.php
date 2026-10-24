<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pembayaran_pajak', function (Blueprint $table) {
            $table->unique('billing_id', 'pembayaran_pajak_billing_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pembayaran_pajak', function (Blueprint $table) {
            $table->dropUnique('pembayaran_pajak_billing_id_unique');
        });
    }
};
