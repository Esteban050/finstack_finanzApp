import fs from 'node:fs';
import path from 'node:path';
import vm from 'node:vm';

const root = process.cwd();
const source = fs.readFileSync(path.join(root, 'resources/js/ui/icons/icons.js'), 'utf8');
const icons = vm.runInNewContext('(' + source.replace(/^.*?export const ICONS = /s, '').replace(/;\s*export const ICON_NAMES[\s\S]*$/, '') + ')');
const keys = Object.keys(icons);
if (keys.length !== 87) throw new Error(`Se esperaban 87 iconos; encontrados ${keys.length}`);
if (keys.some((key) => !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(key))) throw new Error('Clave no kebab-case');
const allowed = /^(path|circle|rect|line|polyline|polygon|ellipse)$/;
for (const [key, body] of Object.entries(icons)) {
  if (/<\/?(?!path|circle|rect|line|polyline|polygon|ellipse)[a-z][^>]*>/i.test(body)) throw new Error(`Etiqueta no permitida en ${key}`);
  if (/\b(style|transform)\s*=|<\/?(text|image|defs|linearGradient|radialGradient|stop|pattern|use|g)\b|#[0-9a-f]{3,8}\b|\b(?:rgb|rgba)\s*\(/i.test(body)) throw new Error(`Contenido no permitido en ${key}`);
  for (const fill of body.matchAll(/\bfill\s*=\s*["']([^"']+)["']/g)) if (!['none', 'currentColor'].includes(fill[1])) throw new Error(`fill inválido en ${key}`);
}
const files = [];
function walk(dir) { for (const entry of fs.readdirSync(dir, { withFileTypes: true })) { const file = path.join(dir, entry.name); if (entry.isDirectory()) walk(file); else if (/\.(js|blade\.php|vue|svelte|tsx?)$/.test(file)) files.push(file); } }
walk(path.join(root, 'resources'));
for (const file of files) {
  const content = fs.readFileSync(file, 'utf8');
  for (const match of content.matchAll(/(?:<x-icon[^>]*\bname=|data-icon-name=|renderIcon\(\s*)["']([^"']+)["']/g)) if (!match[1].startsWith('{{') && !Object.hasOwn(icons, match[1])) throw new Error(`Referencia inexistente ${match[1]} en ${path.relative(root, file)}`);
}
console.log(`check-icons OK: ${keys.length} iconos, claves válidas y referencias verificadas.`);
