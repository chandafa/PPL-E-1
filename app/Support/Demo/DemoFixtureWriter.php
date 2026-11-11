<?php

namespace App\Support\Demo;

use App\Models\FakturPajak;
use App\Models\PembayaranPajak;
use App\Services\Auth\F1Identity;
use App\Services\Tax\FileStore;
use App\Services\Tax\TaxInvoiceService;
use App\Services\Tax\TaxPaymentService;
use Database\Seeders\Support\DemoDataset;
use Database\Seeders\Support\DemoSampleValidator;
use Database\Seeders\Support\FakeEFakturPdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Data demo aditif: penyimpanan ulang tidak mereset koreksi, pembayaran, atau riwayat. */
class DemoFixtureWriter
{
    private array $temporaryFiles = [];

    private array $createdUris = [];

    public function __construct(
        private readonly TaxInvoiceService $invoices,
        private readonly TaxPaymentService $payments,
        private readonly FileStore $files,
        private readonly DemoSampleValidator $validator,
    ) {}

    public function write(array $fixtures, array $paymentFixtures, F1Identity $admin): array
    {
        $invoices = $this->invoices;
        $payments = $this->payments;
        $files = $this->files;
        $validator = $this->validator;
        $this->temporaryFiles = $this->createdUris = [];
        try {
            // Render PDF sebelum mengambil lock agar waktu transaksi tetap terbatas.
            $prepared = [];
            foreach ($fixtures as $id => $fixture) {
                $binary = FakeEFakturPdf::make($fixture['pdf']);
                $validator->validate($binary, $fixture);
                $prepared[$id] = $this->upload($binary);
            }
            $attachments = [];
            foreach ($paymentFixtures as $payment) {
                $attachments[$payment['billingId']] = [
                    $this->upload(FakeEFakturPdf::attachment('e-Billing Demo', DemoDataset::paymentDocumentFields($payment))),
                    isset($payment['ntpn']) ? $this->upload(FakeEFakturPdf::attachment('Bukti Pembayaran Demo', DemoDataset::paymentDocumentFields($payment, proof: true))) : null,
                ];
            }
            $counts = DB::transaction(function () use ($invoices, $payments, $prepared, $attachments, $fixtures, $paymentFixtures, $admin): array {
                if (DB::connection()->getDriverName() === 'pgsql') {
                    DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['e1.demo-seeder']);
                }
                $counts = ['invoices' => 0, 'payments' => 0];
                foreach ($fixtures as $id => $fixture) {
                    if (FakturPajak::query()->where('invoice_id', $id)->exists()) {
                        continue;
                    }
                    $faktur = $invoices->createOutgoing($id, $prepared[$id], null, $admin);
                    $this->createdUris[] = $faktur->pdf_uri;
                    $counts['invoices']++;
                    if ($fixture['cancel'] ?? false) {
                        $invoices->update($faktur->tax_invoice_id, ['status' => 'CANCELLED'], 'Faktur demo dibatalkan; tersedia PDF baru untuk pencatatan ulang', $admin);
                    }
                }
                foreach ($paymentFixtures as $input) {
                    if (PembayaranPajak::query()->where('billing_id', $input['billingId'])->exists()) {
                        continue;
                    }
                    [$billing, $proof] = $attachments[$input['billingId']];
                    $payment = $payments->create($input, $billing, $proof, $admin)['payment'];
                    $this->createdUris[] = $payment->e_billing_uri;
                    if ($payment->payment_proof_uri !== null) {
                        $this->createdUris[] = $payment->payment_proof_uri;
                    }
                    $counts['payments']++;
                }

                return $counts;
            });

            return $counts;
        } catch (Throwable $exception) {
            foreach ($this->createdUris as $uri) {
                $files->delete($uri);
            }
            throw $exception;
        } finally {
            foreach ($this->temporaryFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function upload(string $binary): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'e1seed');
        $this->temporaryFiles[] = $path;
        file_put_contents($path, $binary);

        return new UploadedFile($path, 'demo.pdf', 'application/pdf', null, true);
    }
}
