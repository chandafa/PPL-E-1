// Observasi HTTP saja: tidak mengubah handler/body/header layanan F-1.
const http = require('node:http');
const fs = require('node:fs');
const emit = http.Server.prototype.emit;
http.Server.prototype.emit = function (event, ...args) {
    if (event === 'request' && process.env.E1_DEPENDENCY_TRACE) {
        const [req, res] = args;
        const startedAtUtc = new Date().toISOString();
        res.once('finish', () => fs.appendFileSync(process.env.E1_DEPENDENCY_TRACE, JSON.stringify({
            service: 'F1', startedAtUtc, finishedAtUtc: new Date().toISOString(),
            method: req.method, path: req.url.split('?')[0], status: res.statusCode,
            requestId: req.headers['x-request-id'] || null,
        }) + '\n'));
    }
    return emit.call(this, event, ...args);
};
