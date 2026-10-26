import { request, showRequestError } from '../api/index.js';
import { formatMoney, subtractMoney, moneyCents } from '../utils/money.js';
import { periodRange, formatDate } from '../utils/date.js';
import { mapFieldErrors, bindErrorRevalidation } from './foundation.js';
import { validateIncomeFilters } from '../income/rules.js';
// Una página con los listados reales; cada listado conserva su paginación y totales.
export function mountMovements() {
    mountDashboard();
    const section = document.querySelector('[data-movements-history]');
    if (!section) return;
    const frame = section.querySelector('[data-movements-frame]');
    const tabs = [...section.querySelectorAll('[data-history-url]')];
    const loading = section.querySelector('[data-history-loading]');
    let observer;
    const select = (tab) => {
        for (const item of tabs) { item.setAttribute('aria-selected', String(item === tab)); item.tabIndex = item === tab ? 0 : -1; }
        section.querySelector('[role=tabpanel]').setAttribute('aria-labelledby', tab.id);
        frame.title = `Historial de ${tab.textContent.toLocaleLowerCase('es-CO')}`;
        loading.hidden = false; frame.src = tab.dataset.historyUrl;
    };
    for (const tab of tabs) {
        tab.addEventListener('click', () => select(tab));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? tabs[0] : event.key === 'End' ? tabs.at(-1) : tabs[(tabs.indexOf(tab) + 1) % tabs.length];
            next.focus(); select(next);
        });
    }
    const loaded = () => {
        observer?.disconnect();
        const doc = frame.contentDocument;
        if (!doc?.body) return;
        if (doc.querySelector('input[type=password]') && !doc.querySelector('.ui-main')) { window.location.assign(frame.contentWindow.location.href); return; }
        doc.body.classList.add('ui-embedded');
        const content = doc.querySelector('[data-page-content]') ?? doc.body;
        const resize = () => { frame.style.height = `${Math.ceil(content.getBoundingClientRect().height) + 64}px`; };
        observer = new ResizeObserver(resize); observer.observe(content); resize(); loading.hidden = true;
    };
    frame.addEventListener('load', loaded);
    if (frame.contentDocument?.readyState === 'complete') loaded();
}

export function dashboardTotals(income, expense) {
    const totals = {bruto:income.bruto,retencion:income.retencion,aportes:income.aportes,neto:income.neto,gastos:expense.monto};
    for (const value of Object.values(totals)) moneyCents(value);
    return {...totals,balance:subtractMoney(totals.neto,totals.gastos)};
}
function mountDashboard() {
    const root = document.querySelector('[data-dashboard]'); if (!root) return;
    const form = root.querySelector('form'), period = form.elements.periodo, from = form.elements.fecha_desde, to = form.elements.fecha_hasta;
    const status = root.querySelector('[data-dashboard-status]'), loading = root.querySelector('[data-dashboard-loading]'), results = root.querySelector('[data-dashboard-results]');
    const query = new URL(window.location.href).searchParams;
    if ([...period.options].some(option=>option.value===query.get('periodo'))) period.value=query.get('periodo');
    const range = periodRange(period.value); from.value=query.get('fecha_desde')||range.from; to.value=query.get('fecha_hasta')||range.to;
    const validate = () => {
        const errors = validateIncomeFilters({fecha_desde:from.value,fecha_hasta:to.value});
        for (const input of [from,to]) {try {formatDate(input.value);} catch {errors[input.name]=['Indica una fecha válida.'];}}
        return errors;
    };
    bindErrorRevalidation(form,validate);
    let controller;
    const load = async () => {
        const errors = validate(); mapFieldErrors(form,errors); if(Object.keys(errors).length) return;
        controller?.abort(); controller=new AbortController(); const active=controller;
        loading.hidden=false; results.hidden=true; status.hidden=true; root.setAttribute('aria-busy','true');
        const url=new URL(window.location.href); url.searchParams.set('periodo',period.value); url.searchParams.set('fecha_desde',from.value); url.searchParams.set('fecha_hasta',to.value); window.history.replaceState({},'',url);
        const endpoint=(path)=>{const target=new URL(path,window.location.origin);target.searchParams.set('fecha_desde',from.value);target.searchParams.set('fecha_hasta',to.value);target.searchParams.set('estado','CONFIRMADO');return target.href;};
        try {
            const [income,expense]=await Promise.all([request(endpoint(root.dataset.incomeUrl),{signal:active.signal}),request(endpoint(root.dataset.expenseUrl),{signal:active.signal})]);
            if(active!==controller)return;
            const totals=dashboardTotals(income.data.totales,expense.data.totales);
            for(const output of root.querySelectorAll('[data-dashboard-money]'))output.textContent=formatMoney(totals[output.dataset.dashboardMoney]);
            root.querySelector('[data-dashboard-balance-text]').textContent=moneyCents(totals.balance)<0n?'Los gastos superan los ingresos netos del periodo.':'Ingresos netos menos gastos del periodo.';
            results.hidden=false;
            if(String(income.data.totales.cantidad)==='0'&&String(expense.data.totales.cantidad)==='0'){status.hidden=false;status.textContent='No hay movimientos confirmados para el periodo seleccionado.';}
        } catch(error) {if(error.name!=='AbortError'&&active===controller)showRequestError(status,error);}
        finally {if(active===controller){loading.hidden=true;root.setAttribute('aria-busy','false');}}
    };
    period.addEventListener('change',()=>{if(period.value!=='personalizado'){const range=periodRange(period.value);from.value=range.from;to.value=range.to;}});
    for(const input of [from,to])input.addEventListener('change',()=>{period.value='personalizado';});
    form.addEventListener('submit',event=>{event.preventDefault();load();}); load();
}
