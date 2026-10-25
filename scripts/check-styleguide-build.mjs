import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';

const root = 'public/build';
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'manifest.json'), 'utf8'));
assert.ok(!Object.keys(manifest).some((name) => /styleguide|render-styleguide/.test(name)), 'La guía no debe ser una entrada de producción.');
function inspect(directory) {
    for (const item of fs.readdirSync(directory, { withFileTypes: true })) {
        const file = path.join(directory, item.name);
        if (item.isDirectory()) { inspect(file); continue; }
        assert.doesNotMatch(item.name, /styleguide/);
        if (/\.(js|css|html|json)$/.test(file)) assert.doesNotMatch(fs.readFileSync(file, 'utf8'), /SgPaginator|sg-intro|Registro ficticio A|dev-styleguide|Todos los datos son ficticios/);
    }
}
inspect(root);
console.log('Producción OK: guía, fixtures y estilos de la guía excluidos del build.');
