import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { contrastRatio, contrastCases, maximumKitBackdrop } from '../utils/contrast.js';

const tokens = fs.readFileSync(new URL('../../css/design-system.css', import.meta.url), 'utf8') + fs.readFileSync(new URL('../../css/tokens.css', import.meta.url), 'utf8');
function color(name) {
    const value = tokens.match(new RegExp('--' + name + ':\\s*([^;]+)'))[1];
    return value.startsWith('var(') ? color(value.slice(6, -1)) : value;
}
test('WCAG: referencia blanco/negro y colores iguales', () => {
    assert.equal(contrastRatio('#ffffff', '#000000'), 21);
    assert.equal(contrastRatio('#0B0F14', '#0B0F14'), 1);
});
test('tokens AA: texto ≥4.5 y bordes/foco ≥3', () => {
    for (const item of contrastCases(color)) assert.ok(item.ratio >= item.minimum, `${item.foreground}/${item.background}: ${item.ratio.toFixed(2)}`);
});

test('el texto directo y los controles mantienen AA incluso con el máximo conservador de los fondos', () => {
    const css = fs.readFileSync(new URL('../../css/design-system.css', import.meta.url), 'utf8');
    const opacity = (part) => Number(css.match(new RegExp('::' + part + ' \\{ background:[^}]*opacity:\\s*([\\d.]+)'))[1]);
    const glow = fs.readFileSync(new URL('../../assets/backgrounds/bg-glow.svg', import.meta.url), 'utf8');
    const grid = fs.readFileSync(new URL('../../assets/backgrounds/bg-grid.svg', import.meta.url), 'utf8');
    const backdrop = maximumKitBackdrop(color('ui-bg'), glow, grid, opacity('before'), opacity('after'));
    assert.ok(opacity('after') <= .07);
    for (const item of contrastCases(color, backdrop)) assert.ok(item.ratio >= item.minimum, `${item.foreground}/${item.background}`);
});
