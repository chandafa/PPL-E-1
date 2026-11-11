<?php

namespace Tests\Feature;

use App\Support\Integration\RuntimeSetup;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\File;
use PDO;

class RuntimeSetupDatabaseTest extends TestCase
{
    public function test_fresh_runtime_only_creates_d2_database_and_preserves_main_configuration(): void
    {
        $repo = sys_get_temp_dir().'/e1-runtime-database-'.bin2hex(random_bytes(6));
        mkdir($repo, 0700);
        $environment = "APP_ENV=local\nAPP_KEY=preserved-key\nDB_DATABASE=preserved_database\n";
        file_put_contents($repo.'/.env', $environment);
        $database = (new ConfigurationUrlParser)->parseConfiguration(config('database.connections.pgsql'));
        $connection = new PDO('pgsql:host='.$database['host'].';port='.$database['port'].';dbname=postgres', $database['username'], $database['password']);
        $runtime = null;
        try {
            $runtime = (new RuntimeSetup)->configure($repo, 'http', $database);
            $this->assertStringStartsWith('e1_integrasi_', $runtime['d2']['database']);
            $this->assertStringEndsWith('_d2', $runtime['d2']['database']);
            $this->assertArrayNotHasKey('e1', $runtime);
            $this->assertArrayNotHasKey('tests', $runtime);
            $this->assertDirectoryExists($runtime['d2']['storage'].'/framework/views');
            $this->assertFileDoesNotExist($repo.'/.env.integration');
            $this->assertStringContainsString('DB_DATABASE=preserved_database', file_get_contents($repo.'/.env'));
            $query = $connection->prepare('SELECT count(*) FROM pg_database WHERE datname = ?');
            $query->execute([$runtime['d2']['database']]);
            $this->assertSame(1, (int) $query->fetchColumn());
            $this->assertSame($runtime, (new RuntimeSetup)->configure($repo, 'http', $database));
        } finally {
            if ($runtime !== null) {
                $connection->exec('DROP DATABASE "'.$runtime['d2']['database'].'"');
            }
            File::deleteDirectory($repo);
        }
    }
}
