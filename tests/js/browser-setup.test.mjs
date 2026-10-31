import { test } from 'node:test';
import assert from 'node:assert/strict';
import { prepareBrowser } from '../browser/setup.mjs';
import { browserEnvironment } from '../browser/environment.mjs';

test('migrasi dan generator memakai DB uji yang sama dengan server browser', () => {
    const env = { DB_DATABASE: 'main_database', DB_URL: 'postgres://main', D2_DRIVER: 'http', F1_API_ENABLED: 'true' };
    const calls = [];
    prepareBrowser(env, (...args) => calls.push(args));

    assert.deepEqual(calls.map(([command, args]) => [command, args[1]]), [
        ['php', 'migrate:fresh'], ['php', 'demo:samples'],
    ]);
    for (const [, , options] of calls) {
        assert.deepEqual(options.env, browserEnvironment(env));
        assert.equal(options.env.DB_DATABASE, 'e1_pajak_ui_test');
        assert.equal(options.env.DB_URL, '');
        assert.equal(options.env.APP_ENV, 'testing');
        assert.equal(options.env.D2_DRIVER, 'mock');
        assert.equal(options.env.F1_API_ENABLED, 'false');
        assert.equal(options.stdio, 'inherit');
    }
    assert.equal(env.DB_DATABASE, 'main_database');
});

test('database browser khusus diterapkan ke kedua perintah', () => {
    prepareBrowser({ E1_UI_TEST_DATABASE: 'custom_ui_test' }, (command, args, options) => {
        assert.equal(options.env.DB_DATABASE, 'custom_ui_test');
    });
});

test('server eksternal tidak dimigrasikan dan environment-nya dipertahankan', () => {
    const env = { E1_UI_BASE_URL: 'http://127.0.0.1:9000', DB_DATABASE: 'external_test', D2_DRIVER: 'mock' };
    const calls = [];
    prepareBrowser(env, (...args) => calls.push(args));

    assert.equal(calls.length, 1);
    assert.equal(calls[0][1][1], 'demo:samples');
    assert.deepEqual(calls[0][2].env, env);
});

test('kegagalan migrasi menghentikan setup sebelum generator dijalankan', () => {
    let calls = 0;
    assert.throws(() => prepareBrowser({}, () => {
        calls++;
        throw new Error('migration failed');
    }), /migration failed/);
    assert.equal(calls, 1);
});
