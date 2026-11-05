import Chart from 'chart.js/auto';
import { request, showRequestError, contractPayload } from '../api/index.js';
import { mapFieldErrors, bindErrorRevalidation } from '../ui/foundation.js';
import { moneyInputErrors } from '../income/index.js';
import { decimal, formatMoney, formatQuantity, parseMoneyInput } from '../utils/money.js';
import { canonicalDecimal, nestedFinanceValues, validateFinance, parameterText, financeMessage } from './rules.js';
import { mountInvestmentGoals } from './projections.js';
import { mountProjectionViews } from './projection-view.js';
import { mountPilaAutofill } from './pila.js';

function formValues(form) {
    const entries = new FormData(form);
    const errors = {};
    for(const input of form.querySelectorAll('input:not([type=hidden]):not(:disabled),select:not(:disabled),textarea:not(:disabled)')) {
        const field=input.closest('[data-field]');if(!field)continue;
        const name=field.dataset.field;
        if(input.required && !input.value.trim())errors[name]=['Este campo es obligatorio.'];
        else if(input.validity.customError)errors[name]=[input.validationMessage];
        else if(input.value && ((input.minLength>0 && [...input.value].length<input.minLength) || (input.maxLength>0 && [...input.value].length>input.maxLength)))errors[name]=[`El campo debe tener ${input.minLength>0?'entre '+input.minLength+' y ':'como máximo '}${input.maxLength} caracteres.`];
        else if(input.type==='number' && input.value && (!/^\d+$/.test(input.value) || input.validity.rangeUnderflow || input.validity.rangeOverflow))errors[name]=[`Indica un número entero entre ${input.min} y ${input.max}.`];
        else if(input.type==='date' && input.value && ((input.max && input.value>input.max) || (input.min && input.value<input.min)))errors[name]=['La fecha debe estar dentro del periodo permitido y no ser futura.'];
        else if(input.type==='file' && input.files[0] && (!/\.(pdf|jpe?g|png)$/i.test(input.files[0].name) || input.files[0].size>5*1024*1024))errors[name]=['Selecciona un PDF, JPG o PNG de hasta 5 MB.'];
    }
    for (const input of form.querySelectorAll('[data-decimal-input]:not(:disabled)')) {
        if (!input.value && !input.required) { entries.delete(input.name); continue; }
        try { entries.set(input.name, canonicalDecimal(input.value, {min:input.dataset.min, max:input.dataset.max, precision:input.dataset.precision})); }
        catch (error) { errors[input.closest('[data-field]').dataset.field] = [error.message]; }
    }
    const values = nestedFinanceValues(entries);
    return { values, entries, errors: {...validateFinance(values,form.dataset.financeForm), ...moneyInputErrors(form), ...errors} };
}
function updateFormats(root = document) {
    root.querySelectorAll('[data-percent]').forEach(host => {
        try { host.textContent = decimal(host.dataset.percent).toFixed(2).replace('.', ',') + ' %'; }
        catch { host.textContent = 'No calculable'; }
    });
    root.querySelectorAll('[data-quantity]').forEach(host => {
        try { host.textContent = formatQuantity(host.dataset.quantity); }
        catch { host.textContent = '—'; }
    });
    root.querySelectorAll('[data-unit-price]').forEach(host => {
        try { host.textContent = '$\u00a0' + formatQuantity(host.dataset.unitPrice); }
        catch { host.textContent = '—'; }
    });
    root.querySelectorAll('[data-sign-money]').forEach(host => {
        try { const value = decimal(host.dataset.signMoney); host.textContent = (value.gt('0') ? '+' : '') + formatMoney(host.dataset.signMoney); host.dataset.result = value.isZero() ? 'neutral' : value.gt('0') ? 'positive' : 'negative'; }
        catch { host.textContent = '—'; }
    });
}
export function mountFinance() {
    Chart.defaults.font.size=13;
    Chart.defaults.font.family=getComputedStyle(document.body).fontFamily;
    updateFormats();
    mountInvestmentGoals();
    mountProjectionViews();
    mountPilaAutofill();
    mountPortfolioPieCharts();
    const notice={'#cambios-guardados':'Los cambios se guardaron correctamente.','#registro-eliminado':'El registro se eliminó correctamente.'}[window.location.hash];
    if(notice){const toast=document.getElementById('ui-toast');if(toast){toast.querySelector('[data-toast-message]').textContent=notice;toast.hidden=false;}window.history.replaceState(null,'',window.location.pathname+window.location.search);}
    document.querySelectorAll('[data-finance-form]').forEach(form => {
        form.noValidate=true;
        normalizeFields(form);
        const invalidateResult=()=>{if(form.dataset.financeResult)document.getElementById(form.dataset.financeResult).hidden=true;};
        form.addEventListener('input',invalidateResult);form.addEventListener('change',invalidateResult);
        bindErrorRevalidation(form, () => formValues(form).errors);
        const clearResolvedStatus=()=>{const status=form.querySelector('[data-form-status]');if(status && !form.querySelector('[aria-invalid=true]'))status.hidden=true;};
        form.addEventListener('input',clearResolvedStatus);form.addEventListener('change',clearResolvedStatus);
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (form.getAttribute('aria-busy') === 'true') return;
            const {values, entries, errors} = formValues(form);
            mapFieldErrors(form, errors);
            if (Object.keys(errors).length) return;
            const button = event.submitter, status = form.querySelector('[data-form-status]');
            if(status)status.hidden=true;
            const method = values._method || form.method.toUpperCase(); delete values._method;
            const controls=[...form.elements].map(control=>({control,disabled:control.disabled}));
            for(const {control} of controls)control.disabled=true;
            form.setAttribute('aria-busy','true'); button?.setAttribute('disabled','');
            form.querySelector('[data-form-loading]')?.removeAttribute('hidden');
            try {
                const payload = form.enctype === 'multipart/form-data' ? entries : values;
                const target=new URL(form.action);
                if(method==='GET')for(const [key,value] of Object.entries(contractPayload(target.pathname,method,values)))target.searchParams.set(key,value);
                const response = await request(target, {method,...(method==='GET'?{}:{data:payload})});
                if(form.dataset.financeForm==='liquidation'){window.location.reload();return;}
                if (form.dataset.financeResult) {
                    const host = document.getElementById(form.dataset.financeResult);
                    const data = response.data;
                    for (const output of host.querySelectorAll('[data-result-key]')) {
                        const value = data[output.dataset.resultKey];
                        if(value===null || value===undefined || value==='No calculable')output.textContent='No calculable';
                        else if(output.dataset.resultFormat==='percent')output.textContent=decimal(String(value)).toFixed(2).replace('.',',')+' %';
                        else {output.textContent=(output.dataset.resultFormat==='sign-money' && decimal(String(value)).gt('0')?'+':'')+formatMoney(String(value));if(output.dataset.resultFormat==='sign-money')output.dataset.result=decimal(String(value)).isZero()?'neutral':decimal(String(value)).gt('0')?'positive':'negative';}
                    }
                    const notices = host.querySelector('[data-result-notices]');
                    if (notices) notices.replaceChildren(...(data.avisos || data.mensajes || []).map(text => {const p=document.createElement('p');p.textContent=financeMessage(text);return p;}));
                    const parameters=host.querySelector('[data-result-parameters]');
                    if(parameters){parameters.replaceChildren();const list=document.createElement('dl');list.className='finance-breakdown';const labels={anio_vigencia:'Vigencia',version:'Versión',acto_administrativo:'Acto administrativo',valor_smmlv:'Salario mínimo mensual',ibc_min_smmlv:'IBC mínimo en salarios mínimos',ibc_max_smmlv:'IBC máximo en salarios mínimos',ibc_en_smmlv:'IBC en salarios mínimos',porc_ibc:'Porcentaje de IBC',tarifa_salud:'Tarifa de salud (%)',tarifa_pension:'Tarifa de pensión (%)',tarifa_fsp:'Tarifa del Fondo de Solidaridad Pensional (%)',tarifa_arl:'Tarifa de riesgos laborales (%)',pensionado:'Pensionado',clase_riesgo_arl:'Clase de riesgo laboral'};for(const [key,value] of Object.entries(data.parametros_usados || {})){const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=labels[key] || key.replaceAll('_',' ');dd.textContent=parameterText(key,value);list.append(dt,dd);}parameters.append(list);}
                    if(data.id){const refresh=document.createElement('a');refresh.href=window.location.href;refresh.textContent='Actualizar histórico de liquidaciones';host.querySelector('[data-result-refresh]')?.remove();refresh.dataset.resultRefresh='';host.append(refresh);}
                    host.hidden=false; host.focus();
                } else if (form.hasAttribute('data-finance-compare')) {
                    await renderComparison(form,response.data,values.alternativas);
                } else {
                    const destination=new URL(form.dataset.returnUrl || window.location.href,window.location.origin);
                    const samePage=destination.pathname===window.location.pathname && destination.search===window.location.search;
                    destination.hash=method==='DELETE'?'registro-eliminado':'cambios-guardados';
                    window.location.assign(destination);
                    if(samePage)window.location.reload();
                }
            } catch(error) {
                const errors = Object.fromEntries(Object.entries(error.errors || {}).map(([key,messages])=>[key,messages.map(financeMessage)]));
                mapFieldErrors(form,errors); showRequestError(status,error);
                if(status)for(const [key,messages] of Object.entries(errors))if(![...form.querySelectorAll('[data-field]')].some(field=>field.dataset.field===key)){const p=document.createElement('p');p.textContent=messages.join(' ');status.append(p);}
            } finally {
                for(const {control,disabled} of controls)control.disabled=disabled;
                form.setAttribute('aria-busy','false');
                form.querySelector('[aria-invalid=true]:not([disabled])')?.focus();
                const loading=form.querySelector('[data-form-loading]');if(loading)loading.hidden=true;
                const pageLoading=document.querySelector('[data-page-loading]');if(pageLoading)pageLoading.hidden=true;
                document.getElementById('main-content')?.setAttribute('aria-busy','false');
            }
        });
    });
    const type = document.querySelector('[data-investment-type]');
    if (type) {
        const form = type.closest('form');
        const riskSuggestions = JSON.parse(form?.dataset?.riskSuggestions || '{}');
        const descBox = form?.querySelector('[data-type-description]');
        const descText = form?.querySelector('[data-type-description-text]');
        const descriptions = {
            CDT: 'Título a plazo fijo respaldado por Fogafín. El capital permanece inmutable hasta el vencimiento. No admite retiros parciales ni aportes adicionales durante su vigencia.',
            BONOS_TES: 'Título de renta fija soberana o corporativa con tasa cupón y pago de intereses pactados periódicos.',
            CUENTA_AHORRO_ALTO_RENDIMIENTO: 'Cuenta con disponibilidad inmediata. Los rendimientos diarios se capitalizan directamente en el saldo de tu cuenta (se reflejan actualizando el valor de mercado).',
            FONDO_INVERSION_COLECTIVA: 'Fondo administrado por fiduciarias o comisionistas. La rentabilidad se manifiesta mediante la valorización de la unidad.',
            ACCIONES: 'Renta variable con cotización bursátil. Rentabilidad sujeta a la valorización del precio y dividendos en efectivo.',
            ETF: 'Fondo cotizado indexado a un índice o canasta. Rentabilidad sujeta al precio de cotización y tipo de cambio (USD/COP).',
            CRIPTOACTIVO: 'Activo digital descentralizado con cotización continua 24/7 y alta volatilidad.',
            INMUEBLE_RENTA: 'Inversión inmobiliaria física. Retorno vía cánones de arrendamiento y plusvalía por valorización.',
            NEGOCIO_PARTICIPACION: 'Participación en sociedad o negocio comercial. Rendimiento vía reparto periódico de utilidades.',
            FONDO_VOLUNTARIO_PENSION: 'Ahorro a largo plazo con beneficios tributarios de renta exenta.',
            OTRO: 'Otras alternativas informales o esquemas cooperativos.'
        };

        const updateCdtPreview = () => {
            const preview = form.querySelector('[data-cdt-preview]');
            if (!preview) return;
            const isCdt = ['CDT', 'BONOS_TES'].includes(type.value);
            if (!isCdt) { preview.hidden = true; return; }
            const montoVal = parseMoneyInput(form.querySelector('[name=monto_inicial]')?.value || '0');
            const tasaInput = form.querySelector('[name=tasa_ea]')?.value || '';
            const plazoInput = form.querySelector('[name=plazo_dias]')?.value || '';
            const aperturaInput = form.querySelector('[name=fecha_apertura]')?.value || '';
            const vencimientoInput = form.querySelector('[name=fecha_vencimiento]');

            if (aperturaInput && plazoInput) {
                const days = parseInt(plazoInput, 10);
                if (days > 0) {
                    const start = new Date(aperturaInput + 'T12:00:00Z');
                    const end = new Date(start.getTime() + days * 86400000);
                    const yyyy = end.getUTCFullYear();
                    const mm = String(end.getUTCMonth() + 1).padStart(2, '0');
                    const dd = String(end.getUTCDate()).padStart(2, '0');
                    const endStr = `${yyyy}-${mm}-${dd}`;
                    const targetEl = form.querySelector('[data-cdt-calculated-maturity]');
                    if (targetEl) targetEl.textContent = `${dd}/${mm}/${yyyy}`;
                    if (vencimientoInput && !vencimientoInput.value) {
                        vencimientoInput.value = endStr;
                    }
                }
            }

            if (montoVal && tasaInput && plazoInput) {
                try {
                    const capital = decimal(montoVal);
                    const tasa = decimal(tasaInput.replace(',', '.'));
                    const days = decimal(plazoInput);
                    if (capital.gt('0') && tasa.gte('0') && days.gt('0')) {
                        const factor = decimal('1').plus(tasa.div('100')).pow(days.div('365'));
                        const estimated = capital.times(factor.minus('1')).toFixed(2);
                        const yieldEl = form.querySelector('[data-cdt-estimated-yield]');
                        if (yieldEl) yieldEl.textContent = formatMoney(estimated);
                        preview.hidden = false;
                        return;
                    }
                } catch {}
            }
            preview.hidden = true;
        };

        const updateUnitsCalc = () => {
            const isUnits = ['ACCIONES', 'ETF', 'CRIPTOACTIVO'].includes(type.value);
            const notice = form.querySelector('[data-units-calc-notice]');
            if (!isUnits || !notice) return;
            const unidades = form.querySelector('[name=unidades]')?.value?.replace(',', '.');
            const precio = form.querySelector('[name=precio_unitario_compra]')?.value?.replace(',', '.');
            if (unidades && precio) {
                try {
                    const u = decimal(unidades), p = decimal(precio);
                    if (u.gt('0') && p.gt('0')) {
                        const total = u.times(p).toFixed(2);
                        const montoInput = form.querySelector('[name=monto_inicial]');
                        if (montoInput) {
                            montoInput.value = formatMoney(total).replace(/^\$\s*/, '');
                            form.dispatchEvent(new CustomEvent('money-change', { detail: { name: 'monto_inicial' } }));
                        }
                        const amountEl = form.querySelector('[data-units-calculated-amount]');
                        if (amountEl) amountEl.textContent = formatMoney(total);
                        notice.hidden = false;
                        return;
                    }
                } catch {}
            }
            notice.hidden = true;
        };

        const apply = () => {
            const selected = type.value;
            for(const section of form.querySelectorAll('[data-investment-fields]')) {
                const visible = section.dataset.investmentFields.split(' ').includes(selected);
                section.hidden = !visible;
                for(const input of section.querySelectorAll('input,select')) input.disabled = !visible;
            }
            if (descBox && descText) {
                if (descriptions[selected]) {
                    descText.textContent = descriptions[selected];
                    descBox.hidden = false;
                } else {
                    descBox.hidden = true;
                }
            }
            const riskSelect = form.querySelector('#nivel_riesgo');
            if (riskSelect && riskSuggestions[selected] && !riskSelect.value) {
                riskSelect.value = riskSuggestions[selected];
            }
            updateCdtPreview();
            updateUnitsCalc();
        };

        type.addEventListener('change', apply);
        form.addEventListener('input', (e) => {
            if (['tasa_ea', 'plazo_dias', 'fecha_apertura'].includes(e.target.name)) updateCdtPreview();
            if (['unidades', 'precio_unitario_compra'].includes(e.target.name)) updateUnitsCalc();
        });
        form.addEventListener('money-change', (e) => {
            if (e.detail?.name === 'monto_inicial') updateCdtPreview();
        });
        apply();
    }
    const movementType=document.querySelector('[data-movement-type]');
    if(movementType){const apply=()=>{const panel=document.querySelector('[data-movement-retention]');panel.hidden=movementType.value!=='RENDIMIENTO';panel.querySelectorAll('input').forEach(input=>{input.disabled=panel.hidden;});};movementType.addEventListener('change',apply);apply();}

    const movementForm = document.querySelector('[data-finance-form="movement"]');
    if (movementForm) {
        const montoInput = movementForm.querySelector('[name=monto]');
        const unidadesInput = movementForm.querySelector('[name=unidades]');
        const preview = movementForm.querySelector('[data-movement-units-preview]');

        const updateMovementPreview = () => {
            if (!preview) return;
            const monto = parseMoneyInput(montoInput?.value || '0');
            const unidades = unidadesInput?.value?.replace(',', '.');
            if (monto && unidades && Number(monto) > 0 && Number(unidades) > 0) {
                try {
                    const implied = decimal(monto).div(decimal(unidades)).toFixed(2);
                    preview.textContent = `Precio unitario implícito: ${formatMoney(implied)}`;
                    preview.hidden = false;
                    return;
                } catch {}
            }
            preview.hidden = true;
        };

        montoInput?.addEventListener('input', updateMovementPreview);
        unidadesInput?.addEventListener('input', updateMovementPreview);
        movementForm.addEventListener('money-change', updateMovementPreview);
    }
    document.querySelectorAll('[data-finance-chart]').forEach(canvas => drawChart(canvas));
    new MutationObserver(()=>document.querySelectorAll('[data-finance-chart]').forEach(canvas=>{
        const chart=Chart.getChart(canvas);if(!chart)return;
        const style=getComputedStyle(document.documentElement);
        chart.data.datasets[0].borderColor=style.getPropertyValue('--accent').trim();
        for(const axis of ['x','y'])chart.options.scales[axis].ticks.color=style.getPropertyValue('--ui-muted').trim();
        chart.update('none');
    })).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});
    document.querySelectorAll('[data-add-alternative]').forEach(button=>button.addEventListener('click',()=>{
        const host=document.querySelector('[data-alternatives]');
        const panels=[...host.querySelectorAll('[data-alternative]')];
        if(panels.length>=4)return;
        const template=document.querySelector('[data-alternative-template]');
        const panel=template.content.cloneNode(true);
        host.append(panel); reindexAlternatives(host); updateAlternativeButtons(host);host.lastElementChild.querySelector('input:not([type=hidden])')?.focus();
    }));
    document.querySelector('[data-alternatives]')?.addEventListener('click',event=>{
        if(!event.target.closest('[data-remove-alternative]'))return;
        const host=event.target.closest('[data-alternatives]');
        if(host.querySelectorAll('[data-alternative]').length<=1)return;
        event.target.closest('[data-alternative]').remove();reindexAlternatives(host);updateAlternativeButtons(host);host.querySelector('[data-remove-alternative]')?.focus();
    });
    const alternatives=document.querySelector('[data-alternatives]');if(alternatives)updateAlternativeButtons(alternatives);
}
function normalizeFields(root) {
    root.querySelectorAll('[data-field]').forEach(field=>{
        field.dataset.field=field.dataset.field.replace(/\[(\w+)\]/g,'.$1');
        const input=field.querySelector('input:not([type=hidden]),select,textarea'),error=field.querySelector('[data-field-error]');
        if(input && error){input.setAttribute('aria-describedby',[field.querySelector('.ui-help')?.id,error.id].filter(Boolean).join(' '));input.setAttribute('aria-invalid',String(!error.hidden));}
    });
}
function reindexAlternatives(host) {
    host.querySelectorAll('[data-alternative]').forEach((panel,index)=>{
        panel.querySelector('[data-alternative-number]').textContent=String(index+1);
        for(const node of panel.querySelectorAll('[name],[id],[for],[data-field],[aria-describedby]')) for(const attr of ['name','id','for','data-field','aria-describedby']) {
            if(node.hasAttribute(attr)) node.setAttribute(attr,node.getAttribute(attr).replace(/alternativas\[\d+\]/g,`alternativas[${index}]`).replace(/alternativas\.\d+\./g,`alternativas.${index}.`).replace(/alt-\d+-/g,`alt-${index}-`));
        }
    });
    normalizeFields(host);
}
function updateAlternativeButtons(host) {
    const count=host.querySelectorAll('[data-alternative]').length;
    document.querySelector('[data-add-alternative]').disabled=count>=4;
    host.querySelectorAll('[data-remove-alternative]').forEach(button=>{button.disabled=count<=1;});
    document.querySelector('[data-alternative-count]').textContent=`${count} de 4 alternativas`;
}
function drawChart(canvas) {
    const rows = JSON.parse(document.getElementById(canvas.dataset.financeChart).textContent);
    const style = getComputedStyle(document.documentElement);
    const color = key => style.getPropertyValue(key).trim();
    const initialCost = canvas.dataset.chartCost ? Number(canvas.dataset.chartCost) : null;

    const datasets = [{
        label: canvas.dataset.chartLabel,
        data: rows.map(row => row.value),
        borderColor: color('--accent'),
        backgroundColor: color('--accent-secondary'),
        tension: 0,
        fill: false,
        pointRadius: rows.length <= 1 ? 6 : 4,
        pointHoverRadius: 8,
        pointBackgroundColor: initialCost !== null ? (ctx => {
            const y = Number(ctx.raw?.y ?? ctx.raw ?? 0);
            return y < initialCost ? color('--ui-danger') : color('--ui-success');
        }) : color('--accent'),
        segment: initialCost !== null ? {
            borderColor: ctx => {
                const y0 = Number(ctx.p0.parsed.y);
                const y1 = Number(ctx.p1.parsed.y);
                return (y0 < initialCost || y1 < initialCost) ? color('--ui-danger') : color('--ui-success');
            }
        } : undefined
    }];

    if (initialCost !== null && rows.length > 0) {
        datasets.push({
            label: 'Capital neto aportado (referencia)',
            data: rows.map(() => initialCost),
            borderColor: color('--ui-muted'),
            borderDash: [5, 5],
            pointRadius: rows.length <= 1 ? 4 : 0,
            borderWidth: 1.5,
            fill: false
        });
    }

    new Chart(canvas, {
        type: 'line',
        data: { labels: rows.map(row => row.label), datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: { display: Boolean(initialCost) },
                tooltip: {
                    callbacks: {
                        label: context => {
                            const val = Number(context.raw);
                            if (context.dataset.label.includes('referencia') || context.dataset.label.includes('Costo')) {
                                return `Capital de referencia: ${formatMoney(String(val))}`;
                            }
                            const diff = initialCost !== null ? val - initialCost : 0;
                            const diffText = initialCost !== null
                                ? (diff > 0 ? ` (+${formatMoney(String(diff))} de ganancia)` : diff < 0 ? ` (${formatMoney(String(diff))} por desvalorización)` : ' (al equilibrio)')
                                : '';
                            return `${context.dataset.label}: ${formatMoney(String(val))}${diffText}`;
                        }
                    }
                }
            },
            scales: {
                x: { ticks: { color: color('--ui-muted') } },
                y: { ticks: { color: color('--ui-muted'), callback: value => formatMoney(String(value)) } },
            },
        },
    });
}
function renderComparison(form,data,alternatives) {
    const host=document.querySelector('[data-comparison]'), cards=host.querySelector('[data-comparison-cards]');
    cards.querySelectorAll('canvas').forEach(canvas=>Chart.getChart(canvas)?.destroy());cards.replaceChildren();host.querySelectorAll('[data-comparison-series]').forEach(node=>node.remove());
    const keys={total_aportado:'Total aportado',valor_final_bruto:'Valor final bruto',total_comisiones:'Comisiones',impuestos:'Retención estimada',valor_final_neto:'Valor final neto',ganancia_neta:'Ganancia neta',valor_final_pesos_hoy:'En pesos de hoy'};
    data.alternativas.forEach((alternative,index)=>{
        const card=document.createElement('section');card.className='ui-card';
        const title=document.createElement('h3');title.textContent=alternative.nombre;card.append(title);
        if(alternative.es_mejor){const badge=document.createElement('p');badge.className='ui-badge';badge.textContent='Mayor valor final neto';card.append(badge);}
        const list=document.createElement('dl');list.className='finance-breakdown';
        for(const [key,label] of Object.entries(keys)){const term=document.createElement('dt'),value=document.createElement('dd');term.textContent=label;value.textContent=alternative[key]===null?'No calculable':formatMoney(alternative[key]);list.append(term,value);}
        card.append(list);
        const table=document.createElement('table');table.className='ui-table';const caption=document.createElement('caption');caption.textContent='Evolución mensual';table.append(caption);
        const head=document.createElement('thead'),headrow=document.createElement('tr');for(const text of ['Mes','Valor','Total aportado']){const th=document.createElement('th');th.scope='col';th.textContent=text;headrow.append(th);}head.append(headrow);table.append(head);
        const body=document.createElement('tbody');for(const row of alternative.evolucion){const tr=document.createElement('tr');for(const [label,text] of [['Mes',String(row.mes)],['Valor',formatMoney(row.saldo)],['Total aportado',formatMoney(row.aportado)]]){const td=document.createElement('td');td.dataset.label=label;td.textContent=text;tr.append(td);}body.append(tr);}table.append(body);
        const detail=document.createElement('details'),summary=document.createElement('summary');summary.textContent='Ver evolución mensual';detail.append(summary,table);card.append(detail);
        const canvas=document.createElement('canvas'), chartHost=document.createElement('div');chartHost.className='finance-chart';canvas.role='img';canvas.setAttribute('aria-label',`Evolución de ${alternative.nombre}; datos en la tabla mensual`);chartHost.append(canvas);card.append(chartHost);cards.append(card);
        const script=document.createElement('script');script.type='application/json';script.id=`comparison-series-${index}`;script.dataset.comparisonSeries='';script.textContent=JSON.stringify(alternative.evolucion.map(row=>({label:`Mes ${row.mes}`,value:row.saldo})));host.append(script);canvas.dataset.financeChart=script.id;canvas.dataset.chartLabel='Valor de la alternativa';drawChart(canvas);
    });
    const save=host.querySelector('[data-comparison-save]');save.querySelectorAll('[data-simulation-value]').forEach(input=>input.remove());
    alternatives.forEach((row,index)=>Object.entries(row).forEach(([key,value])=>{const input=document.createElement('input');input.type='hidden';input.name=`alternativas[${index}][${key}]`;input.value=value;input.dataset.simulationValue='';save.append(input);}));
    const notices=host.querySelector('[data-comparison-notices]');notices.textContent=(data.mensajes || []).map(financeMessage).join(' ');
    host.hidden=false;host.focus();
}

export function mountPortfolioPieCharts() {
    const containers = document.querySelectorAll('[data-portfolio-distribution]');
    if (!containers.length) return;

    const style = getComputedStyle(document.documentElement);
    const color = key => style.getPropertyValue(key).trim();

    const RISK_COLORS = {
        'BAJO': '#10b981',
        'MEDIO': '#f59e0b',
        'ALTO': '#ef4444',
        'SIN_DECLARAR': '#64748b'
    };

    const TYPE_PALETTE = [
        '#2563eb',
        '#10b981',
        '#f59e0b',
        '#8b5cf6',
        '#06b6d4',
        '#ec4899',
        '#f97316',
        '#14b8a6',
        '#6366f1',
        '#64748b'
    ];

    containers.forEach(container => {
        const type = container.dataset.portfolioDistribution;
        const script = document.getElementById(`pie-data-${type}`);
        const canvas = container.querySelector(`[data-portfolio-pie="${type}"]`);
        if (!script || !canvas) return;

        let data = [];
        try {
            data = JSON.parse(script.textContent);
        } catch {
            return;
        }
        if (!Array.isArray(data) || data.length === 0) return;

        const isRisk = type === 'por_riesgo';
        const backgroundColors = data.map((item, idx) => {
            if (isRisk && RISK_COLORS[item.key]) {
                return RISK_COLORS[item.key];
            }
            return TYPE_PALETTE[idx % TYPE_PALETTE.length];
        });

        const badges = container.querySelectorAll('.portfolio-pie-badge');
        badges.forEach((badge, idx) => {
            if (backgroundColors[idx]) {
                badge.style.backgroundColor = backgroundColors[idx];
            }
        });

        const surfaceColor = color('--ui-surface') || '#ffffff';

        const chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: data.map(d => d.label),
                datasets: [{
                    data: data.map(d => d.value),
                    backgroundColor: backgroundColors,
                    borderColor: surfaceColor,
                    borderWidth: 2,
                    hoverOffset: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: context => {
                                const idx = context.dataIndex;
                                const item = data[idx];
                                const formattedMoney = formatMoney(String(item.value));
                                return ` ${item.label}: ${formattedMoney} (${item.percentage.toFixed(2).replace('.', ',')} %)`;
                            }
                        }
                    }
                }
            }
        });

        const items = container.querySelectorAll('.portfolio-pie-item');
        items.forEach((itemEl, idx) => {
            itemEl.addEventListener('mouseenter', () => {
                chart.setActiveElements([{ datasetIndex: 0, index: idx }]);
                chart.tooltip.setActiveElements([{ datasetIndex: 0, index: idx }]);
                chart.update();
            });
            itemEl.addEventListener('mouseleave', () => {
                chart.setActiveElements([]);
                chart.tooltip.setActiveElements([]);
                chart.update();
            });
        });
    });
}
