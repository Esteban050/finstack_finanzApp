import { readCookie, setCookie } from '../utils/cookies.js';

const YEAR_IN_SECONDS = 31536000;

export function mountTheme() {
    const root = document.documentElement;
    const switches = document.querySelectorAll('[data-theme-choice]');

    function applyTheme(mode) {
        const dark = mode === 'dark';
        root.dataset.theme = mode;
        root.dataset.palette = dark ? root.dataset.darkPalette : root.dataset.lightPalette;
        for (const button of switches) {
            button.setAttribute('aria-pressed', String(dark));
            button.title = dark ? 'Activar modo claro' : 'Activar modo oscuro';
            button.hidden = false;
        }
    }

    applyTheme(root.dataset.theme);
    for (const button of switches) {
        button.addEventListener('click', () => {
            const mode = root.dataset.theme === 'dark' ? 'light' : 'dark';
            applyTheme(mode);
            setCookie('finanzapp_theme', mode, YEAR_IN_SECONDS);
        });
    }

    const form = document.querySelector('[data-theme-settings]');
    if (!form) return;

    const fields = {light: form.elements.light, dark: form.elements.dark};
    const demo = document.querySelector('[data-theme-preview]');
    const demoLabel = document.querySelector('[data-theme-preview-label]');
    const previewModes = document.querySelectorAll('[data-preview-mode]');
    const status = form.querySelector('[data-theme-status]');
    fields.light.value = root.dataset.lightPalette;
    fields.dark.value = root.dataset.darkPalette;

    function preview(mode) {
        const choice = form.querySelector(`input[name="${mode}"]:checked`);
        demo.dataset.theme = mode;
        demo.dataset.palette = choice.value;
        demoLabel.textContent = choice.dataset.paletteLabel;
        for (const button of previewModes) {
            button.setAttribute('aria-pressed', String(button.dataset.previewMode === mode));
        }
    }

    form.addEventListener('change', event => {
        status.textContent = '';
        preview(event.target.name);
    });
    form.addEventListener('click', event => {
        if (event.target.matches('input[data-palette-label]:checked') && demo.dataset.theme !== event.target.name) {
            preview(event.target.name);
        }
    });
    for (const button of previewModes) {
        button.addEventListener('click', () => preview(button.dataset.previewMode));
        button.disabled = false;
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const saved = `${fields.light.value}.${fields.dark.value}`;
        setCookie('finanzapp_palettes', saved, YEAR_IN_SECONDS);
        const persisted = readCookie('finanzapp_palettes') === saved;
        status.classList.toggle('ui-error', !persisted);
        if (!persisted) {
            status.textContent = 'El navegador no pudo guardar los temas. Revisa si permite cookies y vuelve a intentarlo.';
            return;
        }
        root.dataset.lightPalette = fields.light.value;
        root.dataset.darkPalette = fields.dark.value;
        applyTheme(root.dataset.theme);
        status.textContent = 'Temas guardados.';
    });

    preview(root.dataset.theme);
    form.querySelector('fieldset').disabled = false;
}
