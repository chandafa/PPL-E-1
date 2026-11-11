import { execFileSync } from 'node:child_process';
import { browserEnvironment } from '../browser/environment.mjs';

export default function setup() {
    const env = browserEnvironment({ ...process.env, D2_MOCK_DATASET: 'sqlite', E1_UI_TEST_DATABASE: process.env.E1_UI_TEST_DATABASE || 'e1_d2_source_ui_test' });
    if (!/^e1_d2_source_[a-z0-9_]+$/.test(env.DB_DATABASE)) throw new Error('Database demo terpisah diperlukan.');
    for (const args of [
        ['migrate:fresh', '--force'],
        ['db:seed', '--class=D2SourceDemoSeeder', '--force'],
        ['demo:samples', '--dataset=d2-source', '--path=storage/framework/testing/d2-source-browser'],
    ]) execFileSync('php', ['artisan', ...args], { env, stdio: 'inherit' });
}
