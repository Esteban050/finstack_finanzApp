import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';

const tokens = fs.readFileSync(new URL('../../css/design-system.css', import.meta.url), 'utf8') + fs.readFileSync(new URL('../../css/tokens.css', import.meta.url), 'utf8');
function color(name) {
    const value = tokens.match(new RegExp('--' + name + ':\\s*([^;]+)'))[1];
    return value.startsWith('var(') ? color(value.slice(6, -1)) : value;
}
function luminance(hex) {
    const rgb = hex.slice(1).match(/../g).map((c) => parseInt(c, 16) / 255).map((c) => c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4);
    return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
}
function ratio(a, b) { const values = [luminance(color(a)), luminance(color(b))].sort((x, y) => y - x); return (values[0] + .05) / (values[1] + .05); }
test('tokens AA: texto ≥4.5 y bordes/foco ≥3', () => {
    for (const background of ['ui-bg', 'ui-surface', 'ui-raised']) {
        for (const foreground of ['ui-text', 'ui-muted', 'accent', 'ui-danger', 'ui-warning']) assert.ok(ratio(foreground, background) >= 4.5, `${foreground}/${background}`);
        assert.ok(ratio('ui-border', background) >= 3, `borde/${background}`);
    }
    for (const button of ['accent', 'ui-danger']) assert.ok(ratio('ui-bg', button) >= 4.5);
});
