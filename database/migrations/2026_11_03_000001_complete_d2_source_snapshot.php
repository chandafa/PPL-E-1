<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('project_name')->nullable();
            $table->string('client_id', 50)->nullable()->index();
            $table->string('client_name')->nullable();
        });
        Schema::create('d2_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_id', 50);
            $table->unsignedInteger('line_number');
            $table->string('code', 50);
            $table->string('description');
            $table->decimal('quantity', 18, 2);
            $table->string('unit', 30);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('amount', 18, 2);
            $table->timestamps();
            $table->foreign('invoice_id')->references('invoice_id')->on('invoices')->cascadeOnDelete();
            $table->unique(['invoice_id', 'line_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('d2_invoice_items');
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn(['project_name', 'client_id', 'client_name']);
        });
    }
};
