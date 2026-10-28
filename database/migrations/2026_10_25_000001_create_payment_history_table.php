<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_perubahan_pembayaran', function (Blueprint $table) {
            $table->uuid('audit_id')->primary();
            $table->uuid('tax_payment_id');
            $table->string('action', 14);
            $table->text('actor_akun_id');
            $table->timestampTz('changed_at');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot');
            $table->foreign('tax_payment_id')->references('tax_payment_id')->on('pembayaran_pajak');
            $table->index(['tax_payment_id', 'changed_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE riwayat_perubahan_pembayaran
                ADD CONSTRAINT ck_payment_history_action CHECK (action IN ('CREATE','COMPLETE','CORRECT')),
                ADD CONSTRAINT ck_payment_history_before CHECK (
                    (action = 'CREATE' AND before_snapshot IS NULL) OR
                    (action <> 'CREATE' AND before_snapshot IS NOT NULL))");
            DB::statement("CREATE OR REPLACE FUNCTION riwayat_perubahan_pembayaran_append_only() RETURNS trigger AS \$\$
                BEGIN
                    RAISE EXCEPTION 'riwayat_perubahan_pembayaran bersifat append-only';
                END;
                \$\$ LANGUAGE plpgsql");
            DB::statement('CREATE TRIGGER trg_payment_history_append_only
                BEFORE UPDATE OR DELETE ON riwayat_perubahan_pembayaran
                FOR EACH ROW EXECUTE FUNCTION riwayat_perubahan_pembayaran_append_only()');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_perubahan_pembayaran');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS riwayat_perubahan_pembayaran_append_only()');
        }
    }
};
