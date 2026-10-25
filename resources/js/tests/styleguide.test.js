import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';

test('guía de desarrollo renderiza los componentes Blade reales sin Laravel ni operaciones de negocio', () => {
    const html = execFileSync('php', ['scripts/render-styleguide.php'], { encoding: 'utf8' });
    assert.match(html, /Todos los datos son ficticios/);
    assert.match(html, /data-styleguide/);
    assert.doesNotMatch(html, /<\/?x-|\{\{|@(?:props|if|method|include)\(/);
    for (const component of ['ui-sidebar', 'ui-drawer', 'ui-button', 'ui-field', 'ui-money', 'ui-tags', 'ui-badge', 'ui-card', 'ui-table', 'ui-pagination', 'ui-empty', 'ui-dialog', 'ui-toast', 'ui-skeleton', 'ui-help-tip']) assert.ok(html.includes(component), component);
    const ids = [...html.matchAll(/\bid="([^"]+)"/g)].map((match) => match[1]);
    assert.equal(ids.length, new Set(ids).size, 'IDs de controles únicos');
    assert.match(html, /id="sg-money-disabled"[^>]*disabled/);
    assert.doesNotMatch(html, /<select[^>]*-currency/);
    assert.match(html, /class="ui-money-unit">COP/);
    assert.match(html, /id="sg-tags-disabled"[^>]*disabled/);
    assert.match(html, /data-align="right"[^>]*>Monto COP/);
    assert.match(html, /data-page-size/);
    assert.doesNotMatch(html, />Aplicar<|(?:RNG|RN|CA|RF)-\d/);
    assert.doesNotMatch(html, /Fuente inactiva de ejemplo/);
    assert.match(html, /name="moneda" value="COP"/);
});

test('middleware y entrada de la guía están restringidos a desarrollo', () => {
    const config = fs.readFileSync('vite.config.js', 'utf8');
    const plugin = fs.readFileSync('scripts/styleguide-plugin.mjs', 'utf8');
    assert.match(config, /command === 'serve'/);
    assert.match(plugin, /apply: 'serve'/);
    assert.doesNotMatch(config.match(/input:\s*\[([^\]]+)\]/)[1], /styleguide/);
});
