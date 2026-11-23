import { formatMoneyDraft, normalizeMoneyInput, exceedsMoneyDigits, cleanMoneyInsert } from '../utils/money.js';

const previousValues = new WeakMap();
const bound = new WeakSet();
const validDraft = value => /^-?(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d*)?$/.test(normalizeMoneyInput(value));
const significantLength = text => [...text].filter(char => /[\d,-]/.test(char)).length;

function caretOffset(text, count) {
    if (!count) return 0;
    let seen = 0;
    for (let index = 0; index < text.length; index++) {
        if (/[\d,-]/.test(text[index]) && ++seen === count) return index + 1;
    }
    return text.length;
}

export function groupMoneyControl(input, value = input.value, event = null) {
    const original = input.value;
    const start = input.selectionStart, end = input.selectionEnd;
    const editing = event?.type === 'input' && (event.inputType === 'insertText' || event.inputType?.startsWith('delete'));
    const formatted = formatMoneyDraft(value, { regroup: editing && validDraft(previousValues.get(input) || '') });
    previousValues.set(input, formatted);
    if (formatted !== original) {
        input.value = formatted;
        if (input.matches(':focus') && start !== null) {
            const from = significantLength(original.slice(0, start));
            const to = significantLength(original.slice(0, end ?? start));
            queueMicrotask(() => {
                if (input.matches(':focus')) input.setSelectionRange(caretOffset(formatted, from), caretOffset(formatted, to));
            });
        }
    }
    return formatted;
}

// maxDecimals: decimales permitidos (2 en dinero; tasas y precios pueden usar más).
// allowNegative: si el campo admite el signo menos (por defecto lo dice data-allow-negative).
export function bindMoneyGrouping(input, { isMoney = () => true, maxDecimals = 2, allowNegative = null } = {}) {
    if (bound.has(input)) return;
    bound.add(input);
    if (isMoney()) groupMoneyControl(input);
    const permiteNegativo = allowNegative ?? (() => input.closest('[data-allow-negative]')?.dataset.allowNegative === 'true');
    input.addEventListener('beforeinput', event => {
        if (event.inputType?.startsWith('insert')) {
            const pegado = event.inputType === 'insertFromPaste' || event.inputType === 'insertFromDrop';
            const original = event.data ?? event.dataTransfer?.getData('text') ?? '';
            // Solo números, puntos y comas: letras y caracteres especiales no entran.
            const texto = cleanMoneyInsert(original, { allowNegative: permiteNegativo(), pasted: pegado });
            const inicio = input.selectionStart ?? input.value.length, fin = input.selectionEnd ?? inicio;
            const resultado = texto === null ? null : input.value.slice(0, inicio) + texto + input.value.slice(fin);
            // RNG-09: tampoco más de 12 dígitos enteros ni más de 2 decimales
            // (tope $ 999.999.999.999,99); se calcula el texto que quedaría y se bloquea antes.
            if (resultado === null || (isMoney() && exceedsMoneyDigits(resultado, maxDecimals))) {
                event.preventDefault();
                return;
            }
            // Un valor pegado como "$ 1.000" entra ya limpio.
            if (texto !== original) {
                event.preventDefault();
                input.setRangeText(texto, inicio, fin, 'end');
                input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: event.inputType }));
            }
            return;
        }
        if (!isMoney() || input.selectionStart !== input.selectionEnd) return;
        const at = input.selectionStart;
        let from, to;
        // Borrar junto a un separador elimina también el dígito, sin atrapar el cursor.
        if (event.inputType === 'deleteContentBackward' && at > 1 && /[.'’]/.test(input.value[at - 1])) {
            from = at - 2; to = at;
        } else if (event.inputType === 'deleteContentForward' && /[.'’]/.test(input.value[at] || '')) {
            from = at; to = at + 2;
        } else return;
        event.preventDefault();
        input.setRangeText('', from, to, 'end');
        input.dispatchEvent(new InputEvent('input', { bubbles: true, inputType: event.inputType }));
    });
    input.addEventListener('input', event => {
        // Red de seguridad (autocompletar, arrastrar texto): vuelve al último valor válido.
        if (cleanMoneyInsert(input.value, { allowNegative: permiteNegativo(), pasted: true }) === null) {
            input.value = previousValues.get(input) ?? '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }
        if (!isMoney()) return;
        if (exceedsMoneyDigits(input.value, maxDecimals)) {
            input.value = previousValues.get(input) ?? '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            return;
        }
        groupMoneyControl(input, input.value, event);
    });
}
