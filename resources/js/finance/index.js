import Chart from 'chart.js/auto';
import { request, safeMessage, showRequestError, contractPayload } from '../api/index.js';
import { mapFieldErrors, bindErrorRevalidation } from '../ui/foundation.js';
import { moneyInputErrors } from '../income/index.js';
import { decimal, formatMoney, formatQuantity } from '../utils/money.js';
import { canonicalDecimal, nestedFinanceValues, validateFinance, parameterText } from './rules.js';
import { mountInvestmentGoals } from './projections.js';
import { mountProjectionViews } from './projection-view.js';

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
                    if (notices) notices.replaceChildren(...(data.avisos || data.mensajes || []).map(text => {const p=document.createElement('p');p.textContent=safeMessage(text).replace(/\b(?:RF|RN|RNG|CA)-\d+(?:\.\d+)?\b/g,'');return p;}));
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
                const errors = Object.fromEntries(Object.entries(error.errors || {}).map(([key,messages])=>[key,messages.map(message=>safeMessage(message).replace(/\s*\((?:RF|RN|RNG|CA)-\d+(?:\.\d+)?\)/g,''))]));
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
        const apply = () => {
            for(const section of document.querySelectorAll('[data-investment-fields]')) {
                const visible = section.dataset.investmentFields.split(' ').includes(type.value);
                section.hidden=!visible;
                for(const input of section.querySelectorAll('input,select')) input.disabled=!visible;
            }
        };
        type.addEventListener('change',apply);apply();
    }
    const movementType=document.querySelector('[data-movement-type]');
    if(movementType){const apply=()=>{const panel=document.querySelector('[data-movement-retention]');panel.hidden=movementType.value!=='RENDIMIENTO';panel.querySelectorAll('input').forEach(input=>{input.disabled=panel.hidden;});};movementType.addEventListener('change',apply);apply();}
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
    const rows=JSON.parse(document.getElementById(canvas.dataset.financeChart).textContent);
    const style=getComputedStyle(document.documentElement);
    new Chart(canvas, {
        type: 'line',
        data: {labels: rows.map(row=>row.label), datasets: [{label:canvas.dataset.chartLabel, data:rows.map(row=>row.value), borderColor:style.getPropertyValue('--accent').trim(), backgroundColor:style.getPropertyValue('--accent-secondary').trim(), tension:0, fill:false}]},
        options: {
            responsive:true, maintainAspectRatio:false, animation:false,
            plugins:{legend:{display:false},tooltip:{callbacks:{label:context=>formatMoney(String(context.raw))}}},
            scales:{x:{ticks:{color:style.getPropertyValue('--ui-muted').trim()}},y:{ticks:{color:style.getPropertyValue('--ui-muted').trim(),callback:value=>formatMoney(String(value))}}},
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
    const notices=host.querySelector('[data-comparison-notices]');notices.textContent=(data.mensajes || []).map(message=>safeMessage(message)).join(' ');
    host.hidden=false;host.focus();
}
