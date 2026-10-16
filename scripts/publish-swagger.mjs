import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'node_modules/swagger-ui-dist');
const target = path.join(root, 'public/vendor/swagger-ui');
fs.mkdirSync(target, { recursive: true });
for (const file of ['swagger-ui-bundle.js', 'swagger-ui.css', 'favicon-32x32.png', 'LICENSE', 'NOTICE']) {
  fs.copyFileSync(path.join(source, file), path.join(target, file));
}
console.log('Aset Swagger UI tersedia di public/vendor/swagger-ui.');
