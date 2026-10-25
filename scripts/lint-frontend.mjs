import fs from 'node:fs';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import postcss from 'postcss';

function files(root) { return fs.readdirSync(root, { withFileTypes: true }).flatMap((entry) => entry.isDirectory() ? files(path.join(root, entry.name)) : [path.join(root, entry.name)]); }
let count = 0;
for (const file of [...files('resources/js'), ...files('scripts')].filter((file) => /\.(m?js)$/.test(file))) {
    const result = spawnSync(process.execPath, ['--check', file], { encoding: 'utf8' });
    if (result.status !== 0) throw new Error(result.stderr);
    count++;
}
for (const file of files('resources/js').filter((file) => /\.js$/.test(file) && !file.includes('tests'))) {
    const source = fs.readFileSync(file, 'utf8');
    if (/localStorage|sessionStorage|parseFloat\(/.test(source)) throw new Error(`${file}: almacenamiento de sesión o aritmética float prohibida`);
    if (/\.innerHTML\s*=/.test(source) && !/[\\/]ui[\\/](icons|illustrations)[\\/]/.test(file)) throw new Error(`${file}: usar textContent para datos dinámicos`);
}
console.log(`Lint frontend OK: sintaxis de ${count} archivos JS y reglas de seguridad.`);
for (const file of files('resources/css').filter((file) => file.endsWith('.css'))) postcss.parse(fs.readFileSync(file, 'utf8'), { from: file });
for (const file of files('resources/views').filter((file) => file.endsWith('.blade.php'))) {
    const source = fs.readFileSync(file, 'utf8');
    for (const [, namespace, name] of source.matchAll(/<x-(ui|income|expense|auth)\.([a-z-]+)\b/g)) {
        if (!fs.existsSync(`resources/views/components/${namespace}/${name}.blade.php`)) throw new Error(`${file}: componente ${namespace}.${name} inexistente`);
    }
    if (/parseFloat\(|\(float\)/.test(source)) throw new Error(`${file}: aritmética monetaria con float prohibida`);
}
console.log('CSS válido y referencias de componentes Blade verificadas.');
