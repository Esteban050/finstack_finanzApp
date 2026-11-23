import { groupMoneyControl, bindMoneyGrouping } from '../ui/money-format.js';
import { request } from '../api/index.js';
import { decimal, formatMoney, parseMoneyInput } from '../utils/money.js';
import { mapFieldErrors } from '../ui/foundation.js';

export function socialResult(data) {
 const value=key=>String(data[key] ?? '0');
 const superoTope25=Boolean(data.supero_tope_25_smmlv || data.tope_25_smmlv_alcanzado);
 const excedenteExento=value('excedente_desafectado_tope')!=='0'?value('excedente_desafectado_tope'):(value('excedente_total_exento')!=='0'?value('excedente_total_exento'):value('excedente_exento'));
 const ibcMaxSmmlv=value('ibc_max_smmlv')!=='0'?value('ibc_max_smmlv'):(data.parametros_usados?.ibc_max_smmlv?String(data.parametros_usados.ibc_max_smmlv):null);
 return {salarioBruto:value('salario'),salarioNeto:value('salario_neto_estimado'),totalDescuentos:data.salario&&data.salario_neto_estimado?decimal(String(data.salario)).minus(String(data.salario_neto_estimado)).toFixed(2):value('total_aportes'),montoContrato:String(data.ingresos_brutos ?? data.monto_contrato ?? data.ingreso_base ?? '0'),porcentajePresuncion:data.porcentaje_presuncion_costos ?? data.parametros_usados?.porcentaje_presuncion_costos ?? (data.ingresos_mes?.porcentaje_presuncion ?? null),presuncionCostos:value('presuncion_costos'),baseNeta:value('ingreso_neto'),ibc:value('ibc'),salud:value('salud'),pension:value('pension'),fsp:value('fsp'),arl:value('arl'),retencion:value('retencion_fuente'),totalPagar:String(data.total ?? data.total_aportes ?? '0'),ingresosMes:data.ingresos_mes ?? null,obligacionPorIngresos:data.obligacion_por_ingresos ?? null,obligado:data.obligado ?? null,origenMonto:data.origen_monto ?? null,avisos:Array.isArray(data.avisos)?data.avisos.filter(aviso=>typeof aviso==='string'):[],superoTope25,excedenteExento,ibcMaxSmmlv};
}
export function registerSocial() {
 window.seguridadSocialApp=()=>({
  tipoCotizante:'INDEPENDIENTE',periodo:'',smmlv:'0',porcPresuncionRentista:'',tarifaSaludIndep:'',tarifaPensionIndep:'',tarifaSaludEmp:'',tarifaPensionEmp:'',
  salarioDisplay:'',salarioCanonico:'0',retencionDisplay:'',retencionCanonica:'0',montoContratoDisplay:'',montoContratoCanonico:'0',claseRiesgoArl:0,
  modalPagoAbierto:false,tieneCalculo:false,avisoBajoSmmlv:false,calculando:false,errorCalculo:'',resultados:socialResult({}),_controller:null,_version:0,_timer:null,_data:null,
  // Ingresos registrados del mes: el usuario decide si calcula con ellos ('INGRESOS') o con un monto digitado ('MANUAL').
  ingresosMes:null,origenMonto:'',buscandoIngresos:false,errorIngresos:'',_ingresosCtrl:null,
  get esperandoDecision(){return this.tipoCotizante!=='ASALARIADO' && this.ingresosMes?.cantidad>0 && !this.origenMonto;},
  get nombreClasificacion(){return this.tipoCotizante==='RENTISTA_CAPITAL'?'rentas de capital':'rentas de trabajo';},
  mesLegible(valor){const [anio,mes]=String(valor||'').split('-');const meses=['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];return meses[Number(mes)-1]?`${meses[Number(mes)-1]} de ${anio}`:'';},
  get periodoLegible(){return this.mesLegible(this.periodo);},
  // Mes vencido: el periodo a cotizar usa los ingresos del mes anterior.
  get mesIngresosLegible(){return this.mesLegible(this.ingresosMes?.mes_ingresos);},
  async buscarIngresos(){this._ingresosCtrl?.abort();clearTimeout(this._timer);this._version++;this._controller?.abort();this.ingresosMes=null;this.origenMonto='';this.errorIngresos='';this.tieneCalculo=false;this.modalPagoAbierto=false;this.calculando=false;if(this.tipoCotizante==='ASALARIADO' || !this.periodo)return;const ctrl=new AbortController();this._ingresosCtrl=ctrl;this.buscandoIngresos=true;try{const url=new URL(this.$root.dataset.ingresosUrl,window.location.origin);url.searchParams.set('periodo',this.periodo);url.searchParams.set('tipo_cotizante',this.tipoCotizante);const response=await request(url,{signal:ctrl.signal});if(ctrl!==this._ingresosCtrl)return;this.ingresosMes=response.data ?? null;if(!(this.ingresosMes?.cantidad>0)){this.origenMonto='MANUAL';this.calcularEnVivo();}}catch(error){if(error.name==='AbortError' || ctrl!==this._ingresosCtrl)return;this.errorIngresos=error.message;this.origenMonto='MANUAL';}finally{if(ctrl===this._ingresosCtrl)this.buscandoIngresos=false;}},
  usarIngresos(){if(!(this.ingresosMes?.cantidad>0))return;this.origenMonto='INGRESOS';this.montoContratoCanonico=String(this.ingresosMes.total);this.montoContratoDisplay=this.formatNumber(this.montoContratoCanonico);this.calcularEnVivo();},
  usarManual(){this.origenMonto='MANUAL';this.montoContratoCanonico='0';this.montoContratoDisplay='';clearTimeout(this._timer);this._version++;this._controller?.abort();this.tieneCalculo=false;this.calculando=false;this.$nextTick(()=>this.$root.querySelector('[data-monto-manual]')?.focus());},
  cambiarPeriodo(){this.porcPresuncionRentista=(this.periodo&&String(this.periodo)<'2026-05')?'27.50':'28.08';if(this.tipoCotizante==='ASALARIADO')this.calcularEnVivo();else this.buscarIngresos();},
  init(){const host=this.$root;Object.assign(this,JSON.parse(host.dataset.tariffs||'{}'));this.smmlv=host.dataset.smmlv || '0';this.porcPresuncionRentista=host.dataset.presuncion;this.periodo=host.dataset.periodo || '';const initial=JSON.parse(host.dataset.initial || 'null');if(initial){this._data=initial;this.resultados=socialResult(initial);this.tieneCalculo=true;this.tipoCotizante=initial.tipo_cotizante || 'INDEPENDIENTE';this.salarioCanonico=String(initial.salario ?? '0');this.salarioDisplay=this.formatNumber(this.salarioCanonico);this.montoContratoCanonico=String(initial.ingresos_brutos ?? initial.ingreso_base ?? '0');this.montoContratoDisplay=this.formatNumber(this.montoContratoCanonico);this.origenMonto=initial.origen_monto || (this.tipoCotizante==='ASALARIADO'?'':'MANUAL');this.ingresosMes=initial.ingresos_mes ?? null;if(initial.parametros_usados?.clase_riesgo_arl !== undefined && initial.parametros_usados?.clase_riesgo_arl !== null){this.claseRiesgoArl=Number(initial.parametros_usados.clase_riesgo_arl);}else{this.claseRiesgoArl=Number(host.dataset.claseArl || 0);}}else{this.claseRiesgoArl=Number(host.dataset.claseArl || 0);this.$nextTick(()=>this.buscarIngresos());}},
  get tituloFormulario(){return {ASALARIADO:'Liquidación para Asalariado (Nómina)',INDEPENDIENTE:'Liquidación para Independiente / Prestación de Servicios',RENTISTA_CAPITAL:'Liquidación para Rentista de capital'}[this.tipoCotizante];},
  get aplicaFspAsalariado(){return decimal(this.resultados.fsp).gt('0');},get aplicaFspIndep(){return this.aplicaFspAsalariado;},
  get tarifaFspDisplay(){const val=this._data?.parametros_usados?.tarifa_fsp ?? this._data?.porcentaje_fsp;if(val===undefined||val===null||val==='—')return '—';const num=parseFloat(val);return isNaN(num)?'—':num.toFixed(2).replace('.',',')+' %';},get tarifaFspIndepDisplay(){return this.tarifaFspDisplay;},get tarifaArlCalculada(){return String(this._data?.porcentaje_arl ?? '0.00').replace('.',',');},
  get tope25Smmlv(){try{return decimal(this.smmlv || '0').times(25).toFixed(2);}catch{return '0.00';}},
  get superoTope25EnVivo(){
    if(this.tieneCalculo && this.resultados?.superoTope25) return true;
    try {
      const tope=decimal(this.tope25Smmlv);
      if(tope.lte('0')) return false;
      if(this.tipoCotizante==='ASALARIADO'){
        const salario=decimal(this.salarioCanonico || '0');
        const base=this._data?.es_salario_integral ? salario.times('0.70') : salario;
        return base.gt(tope);
      }
      if(this.tipoCotizante==='INDEPENDIENTE'){
        const ibcPreliminar=decimal(this.montoContratoCanonico || '0').times('0.40');
        return ibcPreliminar.gt(tope);
      }
      if(this.tipoCotizante==='RENTISTA_CAPITAL'){
        const monto=decimal(this.montoContratoCanonico || '0');
        const presuncion=monto.times(decimal(this.porcentajePresuncionRentista || '28.08').div('100'));
        const baseNeta=decimal.max('0', monto.minus(presuncion));
        const ibcPreliminar=baseNeta.times('0.40');
        return ibcPreliminar.gt(tope);
      }
    } catch {}
    return false;
  },
  get excedenteExentoEnVivo(){
    if(this.tieneCalculo && this.resultados?.superoTope25 && decimal(this.resultados.excedenteExento || '0').gt('0')){
      return this.resultados.excedenteExento;
    }
    try {
      const tope=decimal(this.tope25Smmlv);
      if(this.tipoCotizante==='ASALARIADO'){
        const salario=decimal(this.salarioCanonico || '0');
        const base=this._data?.es_salario_integral ? salario.times('0.70') : salario;
        return base.gt(tope) ? base.minus(tope).toFixed(2) : '0';
      }
      if(this.tipoCotizante==='INDEPENDIENTE'){
        const ibcPreliminar=decimal(this.montoContratoCanonico || '0').times('0.40');
        return ibcPreliminar.gt(tope) ? ibcPreliminar.minus(tope).toFixed(2) : '0';
      }
      if(this.tipoCotizante==='RENTISTA_CAPITAL'){
        const monto=decimal(this.montoContratoCanonico || '0');
        const presuncion=monto.times(decimal(this.porcentajePresuncionRentista || '28.08').div('100'));
        const baseNeta=decimal.max('0', monto.minus(presuncion));
        const ibcPreliminar=baseNeta.times('0.40');
        return ibcPreliminar.gt(tope) ? ibcPreliminar.minus(tope).toFixed(2) : '0';
      }
    } catch {}
    return '0';
  },
  get montoSupera25SmmlvPeroIbcNo(){
    if(this.tipoCotizante==='ASALARIADO') return false;
    try {
      const tope=decimal(this.tope25Smmlv);
      if(tope.lte('0')) return false;
      const monto=decimal(this.montoContratoCanonico || '0');
      return monto.gt(tope) && !this.superoTope25EnVivo;
    } catch {
      return false;
    }
  },
  get porcentajePresuncionRentista(){if(this.resultados?.porcentajePresuncion){return String(this.resultados.porcentajePresuncion);}if(this.periodo&&String(this.periodo)<'2026-05'){return '27.50';}return this.porcPresuncionRentista||'28.08';},
  get porcentajePresuncionDisplay(){return String(parseFloat(this.porcentajePresuncionRentista)).replace('.',',');},
  get presuncionCostosCalculada(){return this.resultados.presuncionCostos;},get baseNetaRentistaCalculada(){return this.resultados.baseNeta;},
  cambiarTipo(tipo){this.tipoCotizante=tipo;this.modalPagoAbierto=false;if(tipo==='ASALARIADO'){this._ingresosCtrl?.abort();this.ingresosMes=null;this.origenMonto='';this.calcularEnVivo();}else{this.montoContratoCanonico='0';this.montoContratoDisplay='';this.buscarIngresos();}},
  formatNumber(value){try{return formatMoney(String(value)).replace(/\$\s*/,'');}catch{return '—';}},formatMoney(value){try{return formatMoney(String(value));}catch{return '—';}},
  limpiarCadena(value){try{return parseMoneyInput(String(value));}catch{return ''; }},
  onInput(event,canonical,display){bindMoneyGrouping(event.target);this[display]=groupMoneyControl(event.target,event.target.value,event);this[canonical]=this.limpiarCadena(event.target.value);this.calcularEnVivo();},
  onSalarioInput(e){this.onInput(e,'salarioCanonico','salarioDisplay');},onRetencionInput(e){this.onInput(e,'retencionCanonica','retencionDisplay');},onMontoContratoInput(e){if(this.origenMonto==='INGRESOS')return;this.origenMonto='MANUAL';this.onInput(e,'montoContratoCanonico','montoContratoDisplay');},
  calcularEnVivo(){clearTimeout(this._timer);this._version++;this._controller?.abort();this.tieneCalculo=false;this.modalPagoAbierto=false;this.errorCalculo='';this.calculando=false;this._timer=setTimeout(()=>this.calcularServidor(),450);},
  async calcularServidor(){if(this.esperandoDecision || this.buscandoIngresos)return;const amount=this.tipoCotizante==='ASALARIADO'?this.salarioCanonico:this.montoContratoCanonico;if(!amount || decimal(amount).lte('0'))return;const version=++this._version;this._controller=new AbortController();this.calculando=true;const data={tipo_cotizante:this.tipoCotizante,periodo:this.periodo,...(this.tipoCotizante==='ASALARIADO'?{salario:amount,retencion_fuente:this.retencionCanonica || '0'}:{monto_contrato:amount,origen_monto:this.origenMonto || 'MANUAL',clase_riesgo_arl:this.claseRiesgoArl,...(this.tipoCotizante==='RENTISTA_CAPITAL'?{porcentaje_presuncion:this.porcentajePresuncionRentista}:{})})};try{const response=await request(this.$root.dataset.calculateUrl,{method:'POST',data,signal:this._controller.signal});if(version!==this._version)return;this._data=response.data;this.resultados=socialResult(response.data);this.tieneCalculo=true;this.avisoBajoSmmlv=(response.data.avisos||[]).length>0;const rateKeys=this.tipoCotizante==='ASALARIADO'?['tarifaSaludEmp','tarifaPensionEmp']:['tarifaSaludIndep','tarifaPensionIndep'];for(const [i,key]of rateKeys.entries())this[key]=String(response.data[i===0?'porcentaje_salud':'porcentaje_pension']??'0').replace('.',',');mapFieldErrors(this.$root.querySelector('form'),{});}catch(error){if(version!==this._version || error.name==='AbortError')return;this.errorCalculo=error.message;mapFieldErrors(this.$root.querySelector('form'),error.errors || {});}finally{if(version===this._version)this.calculando=false;}},
  onFormSubmit(event){event.preventDefault();clearTimeout(this._timer);return this.calcularServidor();},
  abrirModalPago(){if(!this.tieneCalculo || this.calculando || this.resultados.obligado===false || decimal(this.resultados.totalPagar).lte('0'))return;this.modalPagoAbierto=true;},
  destroy(){clearTimeout(this._timer);this._controller?.abort();this._ingresosCtrl?.abort();}
 });
}
