import { execFileSync } from 'node:child_process';
import { browserEnvironment } from './environment.mjs';

export function prepareBrowser(env = process.env, run = execFileSync) {
    const options = {
        env: env.E1_UI_BASE_URL ? { ...env } : browserEnvironment(env),
        // Artisan harus terlihat di log CI jika setup/PDF gagal.
        stdio: 'inherit',
    };
    if (!env.E1_UI_BASE_URL) {
        run('php', ['artisan', 'migrate:fresh', '--seed', '--force'], options);
    }
    run('php', ['artisan', 'demo:samples', '--path=storage/framework/testing/e1-browser-samples'], options);
}

export default function setup() {
    prepareBrowser();
}
