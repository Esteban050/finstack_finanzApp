import Chart from 'chart.js/auto';
import { dailyProjection, addMonthsCivil } from './projections.js';
import { decimal, formatMoney } from '../utils/money.js';
import { formatDate, todayBogota } from '../utils/date.js';

export function mountProjectionViews() {
    document.querySelectorAll('[data-projection-view]').forEach(host=>{
        const data=JSON.parse(host.querySelector('[data-projection-data]').textContent);
        const end=host.querySelector('[data-projection-end]');end.min=data.opening;end.max=addMonthsCivil(data.opening,360);end.value=new URL(window.location.href).searchParams.get('projection_end') || (data.goalPercent!==null?addMonthsCivil(data.opening,data.months || 12):todayBogota());
        const errorHost=host.querySelector('[data-projection-error]');errorHost.id='projection-end-custom-error';
        end.setAttribute('aria-describedby',(end.getAttribute('aria-describedby') || '')+' '+errorHost.id);
        let result,page=0,charts=[];
        const table=()=>{
            const body=host.querySelector('[data-projection-rows]');body.replaceChildren();
            for(const row of result.rows.slice(page*20,page*20+20)){
                const tr=document.createElement('tr');tr.setAttribute('role','row');
                for(const [label,value] of [['Fecha',formatDate(row.date)],['Ganancia registrada',row.actual===null?'Sin dato futuro':formatMoney(row.actual)],['Referencia de meta',row.expected===null?'Sin meta':formatMoney(row.expected)],['Proyección por tasa pactada',row.caused===null?'No aplica':formatMoney(row.caused)]]){
                    const td=document.createElement('td');td.dataset.label=label;td.setAttribute('role','cell');td.textContent=value;if(label!=='Fecha')td.className='ui-number';tr.append(td);
                }body.append(tr);
            }
            host.querySelector('[data-projection-page]').textContent=`Días ${page*20+1}–${Math.min((page+1)*20,result.rows.length)} de ${result.rows.length}`;
            host.querySelector('[data-projection-previous]').disabled=page===0;host.querySelector('[data-projection-next]').disabled=(page+1)*20>=result.rows.length;
        };
        const render=()=>{
            const error=host.querySelector('[data-projection-error]');
            try {result=dailyProjection(data,end.value);error.hidden=true;end.setAttribute('aria-invalid','false');}
            catch(problem){error.hidden=false;error.textContent=problem.message;end.setAttribute('aria-invalid','true');end.focus();return;}
            page=Math.max(0,Math.ceil(result.rows.length/20)-1);table();
            const url=new URL(window.location.href);url.searchParams.set('projection_end',end.value);window.history.replaceState(null,'',url);
            for(const [key,value] of [['earned',result.earned],['goal',result.goal],['remaining',result.remaining]]) {
                const el = host.querySelector(`[data-projection-${key}]`);
                if (el) {
                    el.textContent = value===null?'—':(key==='earned' && decimal(value).gt('0')?'+':'')+formatMoney(value);
                    if (key === 'earned' && value !== null) {
                        el.dataset.result = decimal(value).lt('0') ? 'negative' : decimal(value).gt('0') ? 'positive' : 'neutral';
                    }
                }
            }
            const actualCut=end.value>todayBogota()?todayBogota():end.value;
            const isFixed = Boolean(data.isFixedRate || data.rate !== null);
            host.querySelector('[data-projection-caption]').textContent=`Ganancia registrada hasta ${formatDate(actualCut)}, con la última valoración disponible. ${result.goal===null?'Sin meta definida.':`Meta al ${formatDate(result.horizon)}.`}${data.rate===null?'':' El escenario por tasa pactada proyecta el rendimiento sobre el capital pactado hasta su vencimiento.'}`;
            charts.forEach(chart=>chart.destroy());charts=[];
            const style=getComputedStyle(document.documentElement), color=key=>style.getPropertyValue(key).trim();
            const options={
                responsive:true,
                maintainAspectRatio:false,
                animation:false,
                plugins:{
                    legend:{labels:{color:color('--ui-text')}},
                    tooltip:{
                        callbacks:{
                            label:context=>{
                                const val=context.raw?.y ?? context.raw;
                                if(val===null || val===undefined)return '';
                                const n=Number(val);
                                if(context.dataset.label==='Punto de equilibrio ($0)')return 'Punto de equilibrio ($0)';
                                const prefix = n > 0 ? 'Ganancia: +' : n < 0 ? 'Pérdida por desvalorización: ' : 'Sin variación: ';
                                return `${context.dataset.label} (${prefix}${formatMoney(String(val))})`;
                            }
                        }
                    }
                },
                scales:{
                    x:{ticks:{color:color('--ui-muted'),maxTicksLimit:8}},
                    y:{ticks:{color:color('--ui-muted'),callback:value=>formatMoney(String(value))}}
                }
            };

            const datasets=[{
                label: isFixed ? 'Rendimiento pactado acumulado' : 'Ganancia / Pérdida neta',
                data: result.rows.map(row=>row.actual),
                borderColor: color('--accent'),
                borderWidth: 2.5,
                pointRadius: result.rows.length <= 1 ? 6 : 3,
                pointHoverRadius: 8,
                pointBackgroundColor: ctx => {
                    const y = Number(ctx.raw?.y ?? ctx.raw ?? 0);
                    return y < 0 ? color('--ui-danger') : color('--ui-success');
                },
                segment: {
                    borderColor: ctx => {
                        const y0 = Number(ctx.p0.parsed.y);
                        const y1 = Number(ctx.p1.parsed.y);
                        return (y0 < 0 || y1 < 0) ? color('--ui-danger') : color('--ui-success');
                    }
                }
            }];

            if(!isFixed) {
                datasets.push({
                    label: 'Punto de equilibrio ($0)',
                    data: result.rows.map(()=> '0.00'),
                    borderColor: color('--ui-muted'),
                    borderDash: [5, 5],
                    pointRadius: result.rows.length <= 1 ? 4 : 0,
                    borderWidth: 1.5,
                    fill: false
                });
            }

            if(result.goal!==null)datasets.push({label:'Referencia lineal de meta',data:result.rows.map(row=>row.expected),borderColor:color('--ui-muted'),borderDash:[6,4],pointRadius:0,borderWidth:2});
            if(data.rate!==null)datasets.push({label:'Escenario por tasa pactada (EA)',data:result.rows.map(row=>row.caused),borderColor:color('--ui-success'),borderDash:[2,3],pointRadius:0,borderWidth:2});
            charts.push(new Chart(host.querySelector('[data-projection-line]'),{type:'line',data:{labels:result.rows.map(row=>formatDate(row.date)),datasets},options}));

            charts.push(new Chart(host.querySelector('[data-projection-scatter]'),{
                type:'scatter',
                data:{
                    datasets:[{
                        label:'Ganancia en fechas con valoración',
                        data:result.rows.filter(row=>row.valued && row.actual!==null).map(row=>({x:row.day,y:row.actual,date:row.date})),
                        backgroundColor: ctx => {
                            const y = Number(ctx.raw?.y ?? 0);
                            return y < 0 ? color('--ui-danger') : color('--ui-success');
                        },
                        borderColor: ctx => {
                            const y = Number(ctx.raw?.y ?? 0);
                            return y < 0 ? color('--ui-danger') : color('--ui-success');
                        },
                        pointRadius: 5
                    }]
                },
                options:{
                    ...options,
                    plugins:{
                        ...options.plugins,
                        tooltip:{
                            callbacks:{
                                title:items=>items[0]?formatDate(items[0].raw.date):'',
                                label:context=>{
                                    const y = Number(context.raw.y);
                                    const state = y > 0 ? 'Ganancia: +' : y < 0 ? 'Pérdida por desvalorización: ' : 'Equilibrio: ';
                                    return `${state}${formatMoney(String(context.raw.y))}`;
                                }
                            }
                        }
                    },
                    scales:{
                        ...options.scales,
                        x:{
                            type:'linear',
                            title:{display:true,text:'Días desde la apertura',color:color('--ui-muted')},
                            ticks:{color:color('--ui-muted'),precision:0}
                        }
                    }
                }
            }));
            const goalChart=host.querySelector('[data-projection-bar]');host.querySelector('[data-projection-bar-panel]').hidden=result.goal===null;
            if(result.goal!==null)charts.push(new Chart(goalChart,{type:'bar',data:{labels:['Ganancia registrada','Meta de ganancia'],datasets:[{label:'Avance y meta',data:[result.earned || '0',result.goal],backgroundColor:[color('--accent'),color('--ui-muted')]}]},options:{...options,plugins:{...options.plugins,legend:{display:false}}}}));
        };
        host.querySelector('[data-update-projection]').addEventListener('click',render);
        end.addEventListener('change',render);
        host.querySelector('[data-projection-previous]').addEventListener('click',()=>{if(page>0){page--;table();}});
        host.querySelector('[data-projection-next]').addEventListener('click',()=>{if((page+1)*20<result.rows.length){page++;table();}});
        new MutationObserver(render).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});render();
    });
}
