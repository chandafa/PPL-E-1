import { defineConfig } from '@playwright/test';
import { browserEnvironment } from './tests/browser/environment.mjs';

const env = browserEnvironment({ ...process.env, D2_MOCK_DATASET: 'sqlite', E1_UI_TEST_DATABASE: process.env.E1_UI_TEST_DATABASE || 'e1_d2_source_ui_test' });
if (!/^e1_d2_source_[a-z0-9_]+$/.test(env.DB_DATABASE)) {
    throw new Error('Gunakan database khusus dengan awalan e1_d2_source_; setup mengosongkan database tersebut.');
}

export default defineConfig({
    testDir: './tests/d2-source-browser',
    outputDir: './test-results/d2-source',
    globalSetup: './tests/d2-source-browser/setup.mjs',
    workers: 1,
    timeout: 60000,
    use: {
        baseURL: 'http://127.0.0.1:8089', headless: true,
        launchOptions: { args: ['--no-sandbox'], ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) },
        screenshot: 'only-on-failure',
    },
    webServer: {
        command: 'php -d upload_max_filesize=6M -d post_max_size=16M -S 127.0.0.1:8089 -t public server.php',
        url: 'http://127.0.0.1:8089/up', reuseExistingServer: false, env,
    },
    reporter: 'list',
});
