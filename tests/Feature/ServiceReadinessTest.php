<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Services\Invoices\HttpInvoiceProvider;
use App\Services\Invoices\InvoiceProvider;
use App\Support\Integration\DemoIdentity;
use App\Support\Integration\ServiceReadiness;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

class ServiceReadinessTest extends TestCase
{
    private function httpIntegration(): void
    {
        config(['e1.d2.driver' => 'http', 'e1.d2.integration_catalog' => false]);
        $identity = Mockery::mock(DemoIdentity::class);
        $identity->shouldReceive('authenticate')->andReturn(['accessToken' => 'private-test-token']);
        $this->app->instance(DemoIdentity::class, $identity);
        $this->app->bind(InvoiceProvider::class, fn () => new HttpInvoiceProvider(
            'http://127.0.0.1:8026', '/api/v1/invoices/{invoiceId}', 1, request()->bearerToken(), fn () => ['invoice-1'],
        ));
    }

    public function test_authenticated_invoice_and_login_are_checked_without_writing_demo_data(): void
    {
        $this->httpIntegration();
        request()->headers->set('Authorization', 'Bearer original');
        Http::fake([
            '127.0.0.1:8087/*' => Http::response('<form id="f1-login">'),
            '127.0.0.1:8026/*' => Http::response(['data' => ['invoiceId' => 'invoice-1']]),
        ]);
        (new ServiceReadiness)->check();
        Http::assertSent(fn ($request) => str_contains($request->url(), ':8026/') && $request->hasHeader('Authorization', 'Bearer private-test-token'));
        $this->assertSame('Bearer original', request()->headers->get('Authorization'));
        $this->assertFalse(config('e1.d2.integration_catalog'));
    }

    public static function failedStatuses(): array
    {
        return [[401], [403], [500]];
    }

    #[DataProvider('failedStatuses')]
    public function test_open_port_with_failed_api_is_rejected_with_status_and_restores_authentication(int $status): void
    {
        $this->httpIntegration();
        Http::fake([
            '127.0.0.1:8087/*' => Http::response('<form id="f1-login">'),
            '127.0.0.1:8026/*' => Http::response(['secret' => 'must-not-appear'], $status),
        ]);
        try {
            (new ServiceReadiness)->check();
            $this->fail('API gagal harus menghentikan integrasi.');
        } catch (ApiException $error) {
            $this->assertStringContainsString('HTTP '.$status, $error->getMessage());
            $this->assertStringNotContainsString('must-not-appear', $error->getMessage());
        }
        $this->assertNull(request()->headers->get('Authorization'));
        $this->assertFalse(config('e1.d2.integration_catalog'));
    }

    public function test_wrong_invoice_response_is_rejected(): void
    {
        $this->httpIntegration();
        Http::fake([
            '127.0.0.1:8087/*' => Http::response('<form id="f1-login">'),
            '127.0.0.1:8026/*' => Http::response(['data' => ['invoiceId' => 'wrong']]),
        ]);
        $this->expectExceptionMessage('Format respons invoice D-2 tidak sesuai kontrak');
        (new ServiceReadiness)->check();
    }

    public function test_mock_mode_does_not_contact_d2(): void
    {
        config(['e1.d2.driver' => 'mock']);
        Http::fake(['127.0.0.1:8087/*' => Http::response('<form id="f1-login">')]);
        (new ServiceReadiness)->check();
        Http::assertSentCount(1);
    }

    public function test_login_error_page_with_status_200_is_not_accepted(): void
    {
        config(['e1.d2.driver' => 'http']);
        $identity = Mockery::mock(DemoIdentity::class);
        $identity->shouldNotReceive('authenticate');
        $this->app->instance(DemoIdentity::class, $identity);
        Http::fake(['127.0.0.1:8087/*' => Http::response('<html>Error</html>')]);
        $this->expectExceptionMessage('Halaman login E-1 belum siap');
        (new ServiceReadiness)->check();
    }

    public function test_missing_source_invoice_is_not_accepted_as_ready(): void
    {
        $this->httpIntegration();
        Http::fake([
            '127.0.0.1:8087/*' => Http::response('<form id="f1-login">'),
            '127.0.0.1:8026/*' => Http::response([], 404),
        ]);
        $this->expectExceptionMessage('API D-2 belum menyediakan invoice demo');
        (new ServiceReadiness)->check();
    }

    public function test_temporary_startup_failure_is_retried(): void
    {
        config(['e1.d2.driver' => 'mock']);
        Http::fake(['127.0.0.1:8087/*' => Http::sequence()->push('', 503)->push('<form id="f1-login">')]);
        $this->artisan('integration:verify')->assertSuccessful();
        Http::assertSentCount(2);
    }

    public function test_connection_failure_does_not_expose_credentials(): void
    {
        Http::fake(fn () => throw new ConnectionException('password=private-secret'));
        $this->artisan('integration:verify')
            ->doesntExpectOutputToContain('private-secret')
            ->expectsOutputToContain('seed faktur tidak dijalankan')
            ->assertFailed();
    }
}
