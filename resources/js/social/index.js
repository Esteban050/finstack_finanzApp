import { groupMoneyControl, bindMoneyGrouping } from '../ui/money-format.js';
import { request } from '../api/index.js';
import { decimal, formatMoney, parseMoneyInput } from '../utils/money.js';
import { mapFieldErrors } from '../ui/foundation.js';

export function socialResult(data) {
 const value=key=>String(data[key] ?? '0');
 return {salarioBruto:value('salario'),salarioNeto:value('salario_neto_estimado'),totalDescuentos:data.salario&&data.salario_neto_estimado?decimal(String(data.salario)).minus(String(data.salario_neto_estimado)).toFixed(2):value('total_aportes'),montoContrato:String(data.ingresos_brutos ?? data.monto_contrato ?? data.ingreso_base ?? '0'),presuncionCostos:value('presuncion_costos'),baseNeta:value('ingreso_neto'),ibc:value('ibc'),salud:value('salud'),pension:value('pension'),fsp:value('fsp'),arl:value('arl'),retencion:value('retencion_fuente'),totalPagar:String(data.total ?? data.total_aportes ?? '0'),ingresosMes:data.ingresos_mes ?? null,obligacionPorIngresos:data.obligacion_por_ingresos ?? null};
}
export function registerSocial() {
 window.seguridadSocialApp=()=>({
  tipoCotizante:'INDEPENDIENTE',periodo:'',smmlv:'0',porcPresuncionRentista:'',tarifaSaludIndep:'',tarifaPensionIndep:'',tarifaSaludEmp:'',tarifaPensionEmp:'',
  salarioDisplay:'',salarioCanonico:'0',retencionDisplay:'',retencionCanonica:'0',montoContratoDisplay:'',montoContratoCanonico:'0',claseRiesgoArl:0,
  modalPagoAbierto:false,tieneCalculo:false,avisoBajoSmmlv:false,calculando:false,errorCalculo:'',resultados:socialResult({}),_controller:null,_version:0,_timer:null,_data:null,
  init(){const host=this.$root;Object.assign(this,JSON.parse(host.dataset.tariffs||'{}'));this.smmlv=host.dataset.smmlv || '0';this.porcPresuncionRentista=host.dataset.presuncion;this.periodo=host.dataset.periodo || '';const initial=JSON.parse(host.dataset.initial || 'null');if(initial){this._data=initial;this.resultados=socialResult(initial);this.tieneCalculo=true;this.tipoCotizante=initial.tipo_cotizante || 'INDEPENDIENTE';this.salarioCanonico=String(initial.salario ?? '0');this.salarioDisplay=this.formatNumber(this.salarioCanonico);this.montoContratoCanonico=String(initial.ingresos_brutos ?? initial.ingreso_base ?? '0');this.montoContratoDisplay=this.formatNumber(this.montoContratoCanonico);}},
  get tituloFormulario(){return {ASALARIADO:'Liquidación para Asalariado (Nómina)',INDEPENDIENTE:'Liquidación para Independiente / Prestación de Servicios',RENTISTA_CAPITAL:'Liquidación para Rentista de capital'}[this.tipoCotizante];},
  get aplicaFspAsalariado(){return decimal(this.resultados.fsp).gt('0');},get aplicaFspIndep(){return this.aplicaFspAsalariado;},
  get tarifaFspDisplay(){return String(this._data?.parametros_usados?.tarifa_fsp ?? '—').replace('.',',')+' %';},get tarifaFspIndepDisplay(){return this.tarifaFspDisplay;},get tarifaArlCalculada(){return String(this._data?.porcentaje_arl ?? '0.00').replace('.',',');},
  get presuncionCostosCalculada(){return this.resultados.presuncionCostos;},get baseNetaRentistaCalculada(){return this.resultados.baseNeta;},
  cambiarTipo(tipo){this.tipoCotizante=tipo;this.modalPagoAbierto=false;this.calcularEnVivo();},
  formatNumber(value){try{return formatMoney(String(value)).replace(/\$\s*/,'');}catch{return '—';}},formatMoney(value){try{return formatMoney(String(value));}catch{return '—';}},
  limpiarCadena(value){try{return parseMoneyInput(String(value));}catch{return ''; }},
  onInput(event,canonical,display){bindMoneyGrouping(event.target);this[display]=groupMoneyControl(event.target,event.target.value,event);this[canonical]=this.limpiarCadena(event.target.value);this.calcularEnVivo();},
  onSalarioInput(e){this.onInput(e,'salarioCanonico','salarioDisplay');},onRetencionInput(e){this.onInput(e,'retencionCanonica','retencionDisplay');},onMontoContratoInput(e){this.onInput(e,'montoContratoCanonico','montoContratoDisplay');},
  calcularEnVivo(){clearTimeout(this._timer);this._version++;this._controller?.abort();this.tieneCalculo=false;this.modalPagoAbierto=false;this.errorCalculo='';this.calculando=false;this._timer=setTimeout(()=>this.calcularServidor(),450);},
  async calcularServidor(){const amount=this.tipoCotizante==='ASALARIADO'?this.salarioCanonico:this.montoContratoCanonico;if(!amount || decimal(amount).lte('0'))return;const version=++this._version;this._controller=new AbortController();this.calculando=true;const data={tipo_cotizante:this.tipoCotizante,periodo:this.periodo,...(this.tipoCotizante==='ASALARIADO'?{salario:amount,retencion_fuente:this.retencionCanonica || '0'}:{monto_contrato:amount,clase_riesgo_arl:this.claseRiesgoArl,...(this.tipoCotizante==='RENTISTA_CAPITAL'?{porcentaje_presuncion:this.porcPresuncionRentista}:{})})};try{const response=await request(this.$root.dataset.calculateUrl,{method:'POST',data,signal:this._controller.signal});if(version!==this._version)return;this._data=response.data;this.resultados=socialResult(response.data);this.tieneCalculo=true;this.avisoBajoSmmlv=(response.data.avisos||[]).length>0;const rateKeys=this.tipoCotizante==='ASALARIADO'?['tarifaSaludEmp','tarifaPensionEmp']:['tarifaSaludIndep','tarifaPensionIndep'];for(const [i,key]of rateKeys.entries())this[key]=String(response.data[i===0?'porcentaje_salud':'porcentaje_pension']??'0').replace('.',',');mapFieldErrors(this.$root.querySelector('form'),{});}catch(error){if(version!==this._version || error.name==='AbortError')return;this.errorCalculo=error.message;mapFieldErrors(this.$root.querySelector('form'),error.errors || {});}finally{if(version===this._version)this.calculando=false;}},
  onFormSubmit(event){event.preventDefault();clearTimeout(this._timer);return this.calcularServidor();},
  abrirModalPago(){if(!this.tieneCalculo || this.calculando || decimal(this.resultados.totalPagar).lte('0'))return;this.modalPagoAbierto=true;},
  destroy(){clearTimeout(this._timer);this._controller?.abort();}
 });
}
