<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema E-1 mengikuti Pertemuan2/03_Skema_Data_Awal_E-1_v0.1 (dan SQL ERD-nya).
 * Penamaan snake_case (keputusan tim); relasi lintas kelompok (invoiceId D-2,
 * projectId, clientId, akunId F-1) disimpan sebagai rujukan tanpa foreign key.
 *
 * Penyimpangan kecil yang disengaja (aditif, tidak mengubah arti 03):
 *  - faktur_pajak.client_id TEXT (03: UUID) agar menerima ID F-6 berprefiks;
 *  - faktur_pajak.tax_invoice_number UNIQUE (aturan NSFP tidak boleh ganda, Q-02);
 *  - item_faktur.line_no (urutan item pada PDF);
 *  - pembayaran_pajak.updated_at (API-KE01-11 melengkapi catatan).
 */
return new class extends Migration
{
    private const PERIOD_REGEX = '^[0-9]{4}-(0[1-9]|1[0-2])$';

    public function up(): void
    {
        // ENT-KE01-01 FakturPajak
        Schema::create('faktur_pajak', function (Blueprint $table) {
            $table->uuid('tax_invoice_id')->primary();
            $table->string('type', 10);
            $table->text('invoice_id')->nullable();
            $table->text('project_id')->nullable();
            $table->text('client_id')->nullable();
            $table->text('tax_invoice_number')->unique();
            $table->string('status', 12);
            $table->uuid('replaces_tax_invoice_id')->nullable();
            $table->date('tax_invoice_date');
            $table->char('tax_period', 7);
            $table->text('vendor_name')->nullable();
            $table->decimal('dpp', 18, 2);
            $table->decimal('ppn', 18, 2);
            $table->decimal('ppnbm', 18, 2)->nullable()->default('0.00');
            $table->text('pdf_uri');
            $table->text('created_by_akun_id');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->index('invoice_id');
            $table->index('tax_period');
            $table->index('project_id');
            $table->index('status');
        });

        // FK lokal ke diri sendiri ditambahkan setelah tabel (dan kunci primer) ada.
        Schema::table('faktur_pajak', function (Blueprint $table) {
            $table->foreign('replaces_tax_invoice_id')->references('tax_invoice_id')->on('faktur_pajak');
        });

        // ENT-KE01-02 ItemFaktur
        Schema::create('item_faktur', function (Blueprint $table) {
            $table->uuid('tax_invoice_item_id')->primary();
            $table->uuid('tax_invoice_id');
            $table->unsignedSmallInteger('line_no')->default(1);
            $table->text('item_code');
            $table->text('item_name');
            $table->decimal('sale_replacement_advance_term_amount', 18, 2);
            $table->decimal('discount_amount', 18, 2)->nullable();
            $table->decimal('received_advance_amount', 18, 2)->nullable()->default('0.00');

            $table->foreign('tax_invoice_id')->references('tax_invoice_id')->on('faktur_pajak');
            $table->index('tax_invoice_id');
        });

        // ENT-KE01-03 RekapPajakBulanan (read model; dihitung dari faktur)
        Schema::create('rekap_pajak_bulanan', function (Blueprint $table) {
            $table->uuid('recap_id')->primary();
            $table->char('tax_period', 7)->unique();
            $table->integer('invoice_count');
            $table->decimal('total_dpp', 18, 2);
            $table->decimal('total_ppn', 18, 2);
            $table->decimal('total_ppnbm', 18, 2)->nullable()->default('0.00');
            $table->decimal('amount_paid', 18, 2)->default('0.00');
            $table->decimal('payment_difference', 18, 2);
            $table->string('payment_status', 12);
            $table->timestampTz('computed_at');
        });

        // ENT-KE01-04 PembayaranPajak
        Schema::create('pembayaran_pajak', function (Blueprint $table) {
            $table->uuid('tax_payment_id')->primary();
            $table->char('tax_period', 7);
            $table->integer('tax_year');
            $table->decimal('amount_paid', 18, 2);
            $table->text('billing_id');
            $table->text('ntpn')->nullable();
            $table->text('e_billing_uri');
            $table->text('payment_proof_uri')->nullable();
            $table->string('status', 10);
            $table->text('created_by_akun_id');
            $table->timestampTz('created_at');
            $table->timestampTz('updated_at');

            $table->foreign('tax_period')->references('tax_period')->on('rekap_pajak_bulanan');
            $table->index('tax_period');
        });

        // ENT-KE01-05 RiwayatPerubahanFaktur (append-only)
        Schema::create('riwayat_perubahan_faktur', function (Blueprint $table) {
            $table->uuid('audit_id')->primary();
            $table->uuid('tax_invoice_id');
            $table->string('action', 14);
            $table->text('actor_akun_id');
            $table->timestampTz('changed_at');
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->text('note')->nullable();

            $table->foreign('tax_invoice_id')->references('tax_invoice_id')->on('faktur_pajak');
            $table->index(['tax_invoice_id', 'changed_at']);
        });

        $this->addConstraints();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS trg_riwayat_append_only ON riwayat_perubahan_faktur');
            DB::statement('DROP FUNCTION IF EXISTS riwayat_perubahan_faktur_append_only()');
        }

        Schema::dropIfExists('riwayat_perubahan_faktur');
        Schema::dropIfExists('pembayaran_pajak');
        Schema::dropIfExists('rekap_pajak_bulanan');
        Schema::dropIfExists('item_faktur');
        Schema::dropIfExists('faktur_pajak');
    }

    /** CHECK dan trigger setara SQL ERD 03 (PostgreSQL). */
    private function addConstraints(): void
    {
        $period = self::PERIOD_REGEX;

        DB::statement("ALTER TABLE faktur_pajak
            ADD CONSTRAINT ck_faktur_type CHECK (type IN ('OUTGOING','INCOMING')),
            ADD CONSTRAINT ck_faktur_status CHECK (status IN ('NORMAL','REPLACEMENT','CANCELLED')),
            ADD CONSTRAINT ck_faktur_invoiceid_per_type CHECK (
                (type = 'OUTGOING' AND invoice_id IS NOT NULL) OR (type = 'INCOMING' AND invoice_id IS NULL)),
            ADD CONSTRAINT ck_faktur_vendorname_per_type CHECK (
                (type = 'INCOMING' AND vendor_name IS NOT NULL) OR (type = 'OUTGOING' AND vendor_name IS NULL)),
            ADD CONSTRAINT ck_faktur_replaces_only_replacement CHECK (
                replaces_tax_invoice_id IS NULL OR status = 'REPLACEMENT'),
            ADD CONSTRAINT ck_faktur_taxperiod_format CHECK (tax_period ~ '{$period}')");

        DB::statement("ALTER TABLE rekap_pajak_bulanan
            ADD CONSTRAINT ck_rekap_taxperiod_format CHECK (tax_period ~ '{$period}'),
            ADD CONSTRAINT ck_rekap_payment_status CHECK (payment_status IN ('NOT_RECORDED','INCOMPLETE','CONFIRMED')),
            ADD CONSTRAINT ck_rekap_paymentdifference CHECK (payment_difference = amount_paid - total_ppn)");

        DB::statement("ALTER TABLE pembayaran_pajak
            ADD CONSTRAINT ck_pembayaran_taxperiod_format CHECK (tax_period ~ '{$period}'),
            ADD CONSTRAINT ck_pembayaran_status CHECK (status IN ('INCOMPLETE','CONFIRMED')),
            ADD CONSTRAINT ck_pembayaran_confirmed_lengkap CHECK (
                status <> 'CONFIRMED' OR (ntpn IS NOT NULL AND payment_proof_uri IS NOT NULL))");

        DB::statement("ALTER TABLE riwayat_perubahan_faktur
            ADD CONSTRAINT ck_riwayat_action CHECK (action IN ('CREATE','UPDATE','DELETE','STATUS_CHANGE')),
            ADD CONSTRAINT ck_riwayat_beforesnapshot CHECK (
                (action = 'CREATE' AND before_snapshot IS NULL) OR (action <> 'CREATE' AND before_snapshot IS NOT NULL)),
            ADD CONSTRAINT ck_riwayat_aftersnapshot CHECK (
                (action = 'DELETE' AND after_snapshot IS NULL) OR (action <> 'DELETE' AND after_snapshot IS NOT NULL))");

        // Riwayat tidak boleh diubah atau dihapus (Q-05, Q-06: riwayat tidak tertimpa).
        DB::statement("CREATE OR REPLACE FUNCTION riwayat_perubahan_faktur_append_only() RETURNS trigger AS \$\$
            BEGIN
                RAISE EXCEPTION 'riwayat_perubahan_faktur bersifat append-only';
            END;
            \$\$ LANGUAGE plpgsql");

        DB::statement('CREATE TRIGGER trg_riwayat_append_only
            BEFORE UPDATE OR DELETE ON riwayat_perubahan_faktur
            FOR EACH ROW EXECUTE FUNCTION riwayat_perubahan_faktur_append_only()');
    }
};
