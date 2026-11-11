import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../scripts/check-integration-node.cjs', import.meta.url), 'utf8');

function accepted(version, frontend) {
    try {
        runInNewContext(source, {
            process: {
                versions: { node: version },
                argv: frontend ? ['node', 'check', '--frontend'] : ['node', 'check'],
                exit() { throw new Error('unsupported'); },
            },
            console: { error() {} },
        });
        return true;
    } catch (error) {
        if (error.message === 'unsupported') return false;
        throw error;
    }
}

test('Vite build rejects Node versions outside its engine requirements', () => {
    for (const version of ['18.20.0', '20.18.0', '21.7.0', '22.11.0']) {
        assert.equal(accepted(version, true), false, version);
    }
    for (const version of ['20.19.0', '22.12.0', '24.0.0', '25.2.1', '26.13.0']) {
        assert.equal(accepted(version, true), true, version);
    }
});

test('F-1 can still use Node 18 without requesting a frontend build', () => {
    assert.equal(accepted('18.20.0', false), true);
    assert.equal(accepted('16.20.0', false), false);
});
