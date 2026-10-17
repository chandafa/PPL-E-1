import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    globalSetup: './tests/browser/setup.mjs',
    workers: 1,
    timeout: 45000,
    expect: { timeout: 10000 },
    use: {
        baseURL: process.env.E1_UI_BASE_URL || 'http://127.0.0.1:8081',
        headless: true,
        viewport: { width: 1440, height: 1000 },
        launchOptions: { args: ['--no-sandbox'], ...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH ? { executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH } : {}) },
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    ...(!process.env.E1_UI_BASE_URL ? {
        webServer: {
            command: 'php -d upload_max_filesize=6M -d post_max_size=16M -S 127.0.0.1:8081 -t public server.php',
            url: 'http://127.0.0.1:8081/up',
            reuseExistingServer: false,
            env: { APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: 'e1_pajak_ui_test', E1_DEV_LOGIN: 'true', ...(process.platform !== 'win32' ? { PHP_CLI_SERVER_WORKERS: '4' } : {}) },
        },
    } : {}),
    reporter: [['list'], ['json', { outputFile: 'test-results/browser-results.json' }]],
});
