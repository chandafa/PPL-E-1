// Satu konfigurasi untuk migrasi, generator PDF, dan server browser lokal.
export function browserEnvironment(env = process.env) {
    return {
        ...env,
        APP_ENV: 'testing',
        DB_CONNECTION: 'pgsql',
        DB_DATABASE: env.E1_UI_TEST_DATABASE || 'e1_pajak_ui_test',
        DB_URL: '',
        D2_DRIVER: 'mock',
        F1_API_ENABLED: 'false',
        F1_CHECK_ACCOUNT: 'false',
        E1_DEV_LOGIN: 'true',
    };
}
