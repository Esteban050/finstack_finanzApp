// Luminancia relativa y contraste WCAG 2.1; no opera sobre montos.
export function contrastRatio(first, second) {
    const luminance = (hex) => {
        if (!/^#[0-9a-f]{6}$/i.test(hex)) throw new TypeError('Se esperaba un color #RRGGBB.');
        const rgb = hex.slice(1).match(/../g).map((c) => parseInt(c, 16) / 255).map((c) => c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4);
        return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
    };
    const values = [luminance(first), luminance(second)].sort((a, b) => b - a);
    return (values[0] + .05) / (values[1] + .05);
}

export function maximumKitBackdrop(base, glowSource, gridSource, glowOpacity, gridOpacity) {
    const blend = (background, foreground, alpha) => {
        const channels = (hex) => hex.slice(1).match(/../g).map((part) => parseInt(part, 16));
        const bg = channels(background), fg = channels(foreground);
        return '#' + bg.map((value, index) => Math.round(value * (1 - alpha) + fg[index] * alpha).toString(16).padStart(2, '0')).join('');
    };
    let result = base;
    // Cota conservadora: se superponen los máximos de ambos gradientes y una línea.
    // El fondo real nunca alcanza todos estos máximos en el mismo punto.
    for (const stop of glowSource.matchAll(/<stop[^>]*stop-color="(#[0-9a-f]{6})"[^>]*stop-opacity="([\d.]+)"/gi)) result = blend(result, stop[1], Number(stop[2]) * glowOpacity);
    const grid = gridSource.match(/stroke="(#[0-9a-f]{6})"[^>]*stroke-opacity="([\d.]+)"/i);
    return blend(result, grid[1], Number(grid[2]) * gridOpacity);
}

export function contrastCases(resolve, backdropColor = null) {
    const cases = [];
    for (const background of ['ui-bg', 'ui-surface', 'ui-raised', ...(backdropColor ? ['backdrop'] : [])]) {
        for (const foreground of ['ui-text', 'ui-muted', 'accent', 'ui-danger', 'ui-warning']) cases.push({ foreground, background, minimum: 4.5 });
        cases.push({ foreground: 'ui-border-strong', background, minimum: 3 });
        cases.push({ foreground: 'accent', background, minimum: 3, usage: 'foco' });
    }
    cases.push({ foreground: 'accent-contrast', background: 'accent', minimum: 4.5 });
    cases.push({ foreground: 'ui-bg', background: 'ui-danger', minimum: 4.5 });
    return cases.map((item) => ({ ...item, ratio: contrastRatio(resolve(item.foreground), item.background === 'backdrop' ? backdropColor : resolve(item.background)) }));
}
