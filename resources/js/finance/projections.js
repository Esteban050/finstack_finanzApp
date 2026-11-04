import { decimal, parseMoneyInput, formatMoney } from '../utils/money.js';
import { formatDate, todayBogota } from '../utils/date.js';

const DAY = 86400000;
const civilTime = value => { formatDate(value); return Date.parse(value + 'T12:00:00Z'); };
const civilDate = value => new Date(value).toISOString().slice(0, 10);
export function addMonthsCivil(start, count) {
    const [year,month,day]=start.split('-').map(Number);
    const last=new Date(Date.UTC(year,month-1+count+1,0)).getUTCDate();
    return civilDate(Date.UTC(year,month-1+count,Math.min(day,last),12));
}
export function goalFromInput(input, mode, initial) {
    if (!input.trim()) return {percent:'',gain:'',rounded:false};
    const capital=decimal(initial);
    if(capital.lte('0'))throw new Error('Indica primero el monto inicial de la inversión.');
    if(mode==='percent' && !/^-?\d+(?:,\d{1,4})?$/.test(input))throw new Error('Usa un porcentaje con máximo cuatro decimales.');
    const entered=mode==='money'?decimal(parseMoneyInput(input)):decimal(input.replace(',','.'));
    if(mode==='money' && entered.lt('0'))throw new Error('La ganancia esperada en pesos no puede ser negativa.');
    const percent=(mode==='money'?entered.div(capital).times('100'):entered).toFixed(4);
    if(decimal(percent).lt('-100') || decimal(percent).gt('10000'))throw new Error('La meta debe equivaler a un porcentaje entre −100 y 10.000 %.');
    const gain=capital.times(percent).div('100').toFixed(2);
    return {percent,gain,rounded:mode==='money' && !entered.eq(gain)};
}

// La meta es una referencia lineal, no una rentabilidad de mercado prometida.
// El valor real usa la última valoración registrada y los flujos conocidos, sin interpolar precios.
export function dailyProjection(data, end, today=todayBogota()) {
    const start=civilTime(data.opening), last=civilTime(end), limit=civilTime(addMonthsCivil(data.opening,360));
    if(last<start || last>limit)throw new Error('Elige una fecha desde la apertura y hasta 30 años después.');
    const actualEnd=Math.min(last,civilTime(today));
    const horizon=addMonthsCivil(data.opening, data.months || 12);
    const horizonDays=Math.max(1,Math.round((civilTime(horizon)-start)/DAY));
    const goal=data.goalPercent===null || data.goalPercent==='' ? null : decimal(data.initial).times(data.goalPercent).div('100');
    const flows=[...data.flows].sort((a,b)=>a.date.localeCompare(b.date));
    const valuations=[...data.valuations].sort((a,b)=>a.date.localeCompare(b.date));
    const valuedDates=new Set(valuations.map(row=>row.date));
    const factor=data.rate===null?null:decimal('1').plus(decimal(data.rate).div('100')).pow(decimal('1').div('365'));
    let growth=decimal('1');
    let fi=0,vi=0,value=decimal(data.initial),netFlows=decimal('0'),earned=null;
    const rows=[];
    for(let time=start,day=0;time<=last;time+=DAY,day++) {
        const date=civilDate(time);
        if(time<=actualEnd) {
            while(fi<flows.length && flows[fi].date<=date){const flow=flows[fi++];netFlows=['APORTE','COMISION','RETENCION'].includes(flow.type)?netFlows.minus(flow.amount):netFlows.plus(flow.amount);}
            while(vi<valuations.length && valuations[vi].date<=date)value=decimal(valuations[vi++].value);
            earned=value.plus(netFlows).toFixed(2);
        }
        const expected=goal===null?null:goal.times(String(Math.min(day,horizonDays))).div(String(horizonDays)).toFixed(2);
        const caused=factor===null?null:decimal(data.initial).times(growth).minus(data.initial).toFixed(2);
        rows.push({date,day,actual:time<=actualEnd?earned:null,expected,caused,valued:valuedDates.has(date)});
        if(factor!==null)growth=growth.times(factor);
    }
    return {rows,goal:goal?.toFixed(2) ?? null,earned,remaining:goal===null||earned===null?null:goal.minus(earned).lt('0')?'0.00':goal.minus(earned).toFixed(2),horizon};
}

export function mountInvestmentGoals() {
    document.querySelectorAll('[data-investment-goal]').forEach(root=>{
        const form=root.closest('form'),input=root.querySelector('[data-goal-input]'),hidden=root.querySelector('[name=rentabilidad_esperada_porc]'),output=root.querySelector('[data-goal-equivalent]');
        let mode='percent';input.value=(hidden.value || '').replace('.',',');
        const capital=()=>root.dataset.capital || form.querySelector('[name=monto_inicial]')?.value || '';
        const update=()=>{
            let message='';
            try {const result=goalFromInput(input.value,mode,capital());hidden.value=result.percent;output.textContent=result.percent===''?'Sin meta definida.':`${result.rounded?'Meta equivalente que se guardará:':'Ganancia esperada:'} ${formatMoney(result.gain)} · ${result.percent.replace('.',',')} % sobre el monto inicial.`;}
            catch(error){message=error.message;hidden.value='';output.textContent='—';}
            const field=root.closest('[data-field]'),error=field.querySelector('[data-field-error]');error.textContent=message;error.hidden=!message;input.setCustomValidity(message);input.setAttribute('aria-invalid',String(Boolean(message)));
        };
        input.addEventListener('input',update);form.addEventListener('money-change',event=>{if(event.detail.name==='monto_inicial')update();});
        root.querySelectorAll('[data-goal-mode]').forEach(button=>button.addEventListener('click',()=>{
            if(mode===button.dataset.goalMode)return;
            try {const result=goalFromInput(input.value,mode,capital());mode=button.dataset.goalMode;input.value=result.percent===''?'':mode==='percent'?result.percent.replace('.',','):formatMoney(result.gain).replace(/^\$\s/,'');}
            catch {mode=button.dataset.goalMode;input.value='';}
            root.querySelectorAll('[data-goal-mode]').forEach(item=>item.setAttribute('aria-pressed',String(item.dataset.goalMode===mode)));
            root.querySelector('[data-goal-unit]').textContent=mode==='money'?'COP':'%';update();input.focus();
        }));
        update();
    });
}
