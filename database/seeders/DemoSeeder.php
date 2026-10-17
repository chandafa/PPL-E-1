<?php

namespace Database\Seeders;

use App\Models\FakturPajak;
use App\Models\PembayaranPajak;
use App\Services\Auth\F1Identity;
use App\Services\Tax\FileStore;
use App\Services\Tax\TaxInvoiceService;
use App\Services\Tax\TaxPaymentService;
use Database\Seeders\Support\DemoDataset;
use Database\Seeders\Support\FakeEFakturPdf;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Data demo aditif: penyimpanan ulang tidak mereset koreksi, pembayaran, atau riwayat. */
class DemoSeeder extends Seeder
{
    private array $temporaryFiles = [];

    private array $createdUris = [];

    public function run(TaxInvoiceService $invoices, TaxPaymentService $payments, FileStore $files): void
    {
        if (app()->isProduction() || config('e1.d2.driver') !== 'mock') {
            $this->command?->warn('DemoSeeder hanya tersedia di luar production dengan D2_DRIVER=mock.');

            return;
        }
        $this->temporaryFiles = $this->createdUris = [];
        try {
            // Render PDF sebelum mengambil lock agar waktu transaksi tetap terbatas.
            $prepared = [];
            foreach (DemoDataset::seededInvoices() as $id => $fixture) {
                $options = $fixture['pdf'];
                $options['date'] = $fixture['seedDate'] ?? $options['date'];
                $prepared[$id] = $this->upload(FakeEFakturPdf::make($options));
            }
            $replacement = $this->upload(FakeEFakturPdf::make(DemoDataset::originalReplacement()['pdf']));
            $attachments = [];
            foreach (DemoDataset::payments() as $payment) {
                $attachments[$payment['billingId']] = [
                    $this->upload(FakeEFakturPdf::attachment('e-Billing Demo', $payment)),
                    isset($payment['ntpn']) ? $this->upload(FakeEFakturPdf::attachment('Bukti Pembayaran Demo', $payment)) : null,
                ];
            }
            $counts = DB::transaction(function () use ($invoices, $payments, $prepared, $replacement, $attachments): array {
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['e1.demo-seeder']);
                $admin = new F1Identity('AKN-0002', 'Admin');
                $coo = new F1Identity('AKN-0005', 'COO');
                $counts = ['invoices' => 0, 'payments' => 0];
                foreach (DemoDataset::seededInvoices() as $id => $fixture) {
                    if (FakturPajak::query()->where('invoice_id', $id)->exists()) {
                        continue;
                    }
                    $faktur = $invoices->createOutgoing($id, $prepared[$id], null, $admin);
                    $this->createdUris[] = $faktur->pdf_uri;
                    $counts['invoices']++;
                    if ($id === 'inv-demo-003') {
                        $new = $invoices->createOutgoing($id, $replacement, $faktur->tax_invoice_id, $admin);
                        $this->createdUris[] = $new->pdf_uri;
                        $counts['invoices']++;
                    }
                    if (isset($fixture['correctDate'])) {
                        $invoices->update($faktur->tax_invoice_id, ['taxInvoiceDate' => $fixture['correctDate']], 'Koreksi tanggal faktur demo', $coo);
                    }
                    if ($fixture['cancel'] ?? false) {
                        $invoices->update($faktur->tax_invoice_id, ['status' => 'CANCELLED'], 'Faktur demo dibatalkan; tersedia PDF baru untuk pencatatan ulang', $admin);
                    }
                }
                foreach (DemoDataset::payments() as $input) {
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
            $this->command?->info("Demo E-1: {$counts['invoices']} faktur dan {$counts['payments']} pembayaran ditambahkan; data yang sudah ada dipertahankan.");
            $this->command?->info('Dataset awal: 32 faktur, 5 pembayaran, 5 masa pajak. Invoice 006/007 disisakan untuk UI. PDF: php artisan demo:samples');
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
