<?php

namespace Tests\Feature;

use Tests\TestCase;

class SwaggerDocumentationTest extends TestCase
{
    public function test_halaman_swagger_memakai_kontrak_dan_aset_lokal(): void
    {
        $this->get('/docs')->assertOk()
            ->assertSee('SwaggerUIBundle')
            ->assertSee('/docs/openapi.yaml', false)
            ->assertSee('vendor/swagger-ui/swagger-ui-bundle.js')
            ->assertSee('persistAuthorization: false', false)
            ->assertSee('validatorUrl: null', false);
    }

    public function test_unduhan_openapi_identik_dengan_kontrak_yang_diuji(): void
    {
        $response = $this->get('/docs/openapi.yaml')->assertOk();
        $this->assertSame(file_get_contents(base_path('openapi.yaml')), file_get_contents($response->baseResponse->getFile()->getPathname()));
        $response->assertHeader('Content-Type', 'application/yaml; charset=utf-8');
    }
}
