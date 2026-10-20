import { execFileSync } from 'node:child_process';

export default function setup() {
    if (!process.env.E1_UI_BASE_URL) {
        execFileSync('php', ['artisan', 'migrate:fresh', '--seed', '--force'], {
            env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'pgsql', DB_DATABASE: process.env.E1_UI_TEST_DATABASE || 'e1_pajak_ui_test' },
            stdio: 'pipe',
        });
    }
    execFileSync('php', ['artisan', 'demo:samples', '--path=storage/framework/testing/e1-browser-samples'], { stdio: 'pipe' });
}
