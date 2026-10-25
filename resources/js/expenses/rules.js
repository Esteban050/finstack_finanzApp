import { validateAmount } from '../utils/money.js';
import { formatDate, todayBogota } from '../utils/date.js';
import { validateRecurrence, validateIncomeFilters } from '../income/rules.js';

export const PAYMENT_LABELS = { EFECTIVO: 'Efectivo', TARJETA_DEBITO: 'Tarjeta débito', TARJETA_CREDITO: 'Tarjeta de crédito', TRANSFERENCIA: 'Transferencia', BILLETERA_DIGITAL: 'Billetera digital', OTRO: 'Otro' };
const has = (list, value) => list.includes(value);
export function activeChildren(categories, id) { return categories.find((category) => category.id === id)?.subcategorias?.filter((sub) => sub.activa) ?? []; }
export function validateTags(tags) {
    if (!Array.isArray(tags)) return 'Las etiquetas deben enviarse como una lista.';
    if (tags.length > 10) return 'Un gasto admite como máximo 10 etiquetas.';
    if (tags.some((tag) => typeof tag !== 'string' || [...tag.trim()].length < 2 || [...tag.trim()].length > 30)) return 'Cada etiqueta debe tener entre 2 y 30 caracteres.';
    return '';
}
export function validateTagDraft(draft, tags) {
    if (!draft.trim()) return '';
    return validateTags([...tags, draft.trim()]) || 'Agrega la etiqueta con Enter o Agregar, o borra el texto antes de guardar.';
}
export function validateExpense(values, categories, { recurrent = false, limit = 'none', frozen = {} } = {}) {
    const errors = {};
    if (!recurrent) {
        try { formatDate(values.fecha); if (!/^\d{4}-\d{2}-\d{2}$/.test(values.fecha)) throw new Error(); }
        catch { errors.fecha = ['Indica una fecha válida.']; }
        if (!errors.fecha && values.fecha > todayBogota()) errors.fecha = ['La fecha no puede ser futura. Para un gasto futuro, crea un gasto recurrente.'];
    }
    if (!validateAmount(values.monto_original)) errors.monto_original = ['El monto debe ser mayor que $ 0 y no superar $ 999.999.999.999,99.'];
    const category = categories.find((item) => item.id === values.categoria_id && item.activa);
    if (!category) errors.categoria_id = ['Selecciona una categoría activa.'];
    const children = activeChildren(categories, values.categoria_id);
    if (!children.length && category) errors.subcategoria_id = ['Esta categoría no tiene subcategorías activas. Crea o activa una subcategoría para continuar.'];
    else if (!children.some((sub) => sub.id === values.subcategoria_id)) errors.subcategoria_id = ['Selecciona una subcategoría activa de esta categoría.'];
    if (!has(Object.keys(PAYMENT_LABELS), values.medio_pago)) errors.medio_pago = ['Selecciona el medio de pago.'];
    if (!has(['FIJO', 'VARIABLE'], values.naturaleza)) errors.naturaleza = ['Selecciona la naturaleza: Fijo o Variable.'];
    if (!has(['ESENCIAL', 'DISCRECIONAL'], values.necesidad)) errors.necesidad = ['Selecciona la necesidad: Esencial o Discrecional.'];
    if (!has(['NO_DEDUCIBLE', 'POTENCIALMENTE_DEDUCIBLE'], values.relevancia_tributaria)) errors.relevancia_tributaria = ['Selecciona la relevancia tributaria.'];
    if (values.relevancia_tributaria === 'POTENCIALMENTE_DEDUCIBLE' && !has(['INTERESES_VIVIENDA', 'MEDICINA_PREPAGADA', 'DEPENDIENTES', 'AFC_VOLUNTARIOS', 'GMF', 'OTROS'], values.subtipo_deduccion)) errors.subtipo_deduccion = ['Selecciona el subtipo de deducción.'];
    const tagsError = validateTags(values.etiquetas ?? []); if (tagsError) errors.etiquetas = [tagsError];
    if ([...(values.comercio_beneficiario ?? '')].length > 80) errors.comercio_beneficiario = ['El comercio o beneficiario no puede superar los 80 caracteres.'];
    if ([...(values.descripcion ?? '')].length > 255) errors.descripcion = ['La descripción no puede superar los 255 caracteres.'];
    if (recurrent) Object.assign(errors, validateRecurrence({ ...frozen, ...values }, limit));
    return errors;
}
export function validateBulk(values, categories) {
    const errors = {};
    if (!values.ids?.length || values.ids.length > 100) errors.ids = ['Selecciona entre 1 y 100 gastos.'];
    if (values.categoria_id && !activeChildren(categories, values.categoria_id).some((sub) => sub.id === values.subcategoria_id)) errors.subcategoria_id = ['Selecciona una subcategoría activa de esta categoría.'];
    if (values.naturaleza && !has(['FIJO', 'VARIABLE'], values.naturaleza)) errors.naturaleza = ['Selecciona Fijo o Variable.'];
    if (values.necesidad && !has(['ESENCIAL', 'DISCRECIONAL'], values.necesidad)) errors.necesidad = ['Selecciona Esencial o Discrecional.'];
    if ('etiquetas' in values) { const message = validateTags(values.etiquetas); if (message) errors.etiquetas = [message]; }
    if (!['categoria_id', 'naturaleza', 'necesidad', 'etiquetas'].some((key) => key in values && (key === 'etiquetas' || values[key]))) errors.cambios = ['Indica al menos un cambio.'];
    return errors;
}
export function selectionWith(current, id, selected) {
    const next = new Set(current); if (!selected) next.delete(id); else if (next.size < 100 || next.has(id)) next.add(id);
    return { ids: next, blocked: selected && !next.has(id) };
}
export function validateCategory(values, catalog, { id = null, parentId = null } = {}) {
    const errors = {}, name = (values.nombre ?? '').trim();
    const peers = parentId ? catalog.find((item) => item.id === parentId)?.subcategorias ?? [] : catalog;
    if (!id && peers.filter((item) => !item.es_predeterminada).length >= (parentId ? 30 : 50)) errors.nombre = [parentId ? 'Alcanzaste el máximo de 30 subcategorías en esta categoría' : 'Alcanzaste el máximo de 50 categorías'];
    else if ([...name].length < 2 || [...name].length > 40) errors.nombre = ['El nombre debe tener entre 2 y 40 caracteres.'];
    else if (peers.some((item) => item.id !== id && item.nombre.trim().toLocaleLowerCase('es-CO') === name.toLocaleLowerCase('es-CO'))) errors.nombre = ['Ya existe una categoría con ese nombre en este nivel.'];
    if (parentId && !values.naturaleza_sugerida) errors.naturaleza_sugerida = ['Selecciona la naturaleza sugerida.'];
    if (parentId && !values.necesidad_sugerida) errors.necesidad_sugerida = ['Selecciona la necesidad sugerida.'];
    return errors;
}
export { validateIncomeFilters as validateExpenseFilters };
