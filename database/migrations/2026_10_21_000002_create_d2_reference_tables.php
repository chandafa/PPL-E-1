<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabel referensi invoice dari modul D-2, dibuat di database E-1 yang sama
     * (tidak dibuat database baru). E-1 hanya membaca data invoice D-2 sebagai
     * rujukan pencatatan e-Faktur; tidak mengubah data di sumber asli D-2.
     */
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->string('contract_id', 50)->primary();
            $table->string('project_id', 50)->index();
            $table->string('contract_number', 50)->unique();
            $table->decimal('contract_value', 18, 2);
            $table->string('created_by', 50);
            $table->timestamps();
        });

        Schema::create('termins', function (Blueprint $table) {
            $table->string('termin_id', 50)->primary();
            $table->string('contract_id', 50);
            $table->unsignedInteger('termin_number');
            $table->decimal('termin_value', 18, 2);
            $table->date('due_date');
            $table->string('status', 30)->default('SIAP_DITAGIH');
            $table->timestamps();

            $table->foreign('contract_id')
                ->references('contract_id')
                ->on('contracts')
                ->onDelete('cascade');
        });

        Schema::create('tax_parameters', function (Blueprint $table) {
            $table->string('tax_param_id', 50)->primary();
            $table->string('contract_id', 50)->nullable();
            $table->decimal('ppn_rate', 5, 2)->default(11.00);
            $table->decimal('pph_rate', 5, 2)->default(2.00);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')
                ->references('contract_id')
                ->on('contracts')
                ->onDelete('set null');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->string('invoice_id', 50)->primary();
            $table->string('invoice_number', 50)->unique();
            $table->string('termin_id', 50)->unique();
            $table->string('tax_param_id', 50);
            $table->date('invoice_date');
            $table->decimal('dpp_amount', 18, 2);
            $table->decimal('ppn_amount', 18, 2);
            $table->decimal('pph_amount', 18, 2);
            $table->decimal('total_amount', 18, 2);
            $table->string('status', 30)->default('TERTAGIH');
            $table->string('created_by', 50);
            $table->timestamps();

            $table->foreign('termin_id')
                ->references('termin_id')
                ->on('termins')
                ->onDelete('restrict');

            $table->foreign('tax_param_id')
                ->references('tax_param_id')
                ->on('tax_parameters')
                ->onDelete('restrict');
        });

        Schema::create('invoice_histories', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_id', 50);
            $table->string('status', 30);
            $table->timestamp('changed_at');
            $table->string('changed_by', 50);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('invoice_id')
                ->references('invoice_id')
                ->on('invoices')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_histories');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('tax_parameters');
        Schema::dropIfExists('termins');
        Schema::dropIfExists('contracts');
    }
};
