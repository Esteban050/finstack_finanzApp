import { formatMoneyDraft, normalizeMoneyInput } from '../utils/money.js';

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

export function bindMoneyGrouping(input, { isMoney = () => true } = {}) {
    if (bound.has(input)) return;
    bound.add(input);
    if (isMoney()) groupMoneyControl(input);
    input.addEventListener('beforeinput', event => {
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
        if (isMoney()) groupMoneyControl(input, input.value, event);
    });
}
