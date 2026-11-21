import { request, showRequestError } from '../api/index.js';
import { decimal, formatMoney, formatQuantity } from '../utils/money.js';
import { formatDate } from '../utils/date.js';

export function mountAdmin() {
    const lookup = document.getElementById('btnConsultar');
    lookup?.addEventListener('click', async () => {
        const input = document.getElementById('consulta_digitos'), output = document.getElementById('resultadoConsulta');
        output.classList.remove('hidden'); output.setAttribute('role', 'status');
        if (!/^\d{2}$/.test(input.value)) { output.textContent = 'Ingresa exactamente dos dígitos, del 00 al 99.'; input.setAttribute('aria-invalid','true'); input.focus(); return; }
        input.setAttribute('aria-invalid','false'); lookup.disabled = true; lookup.setAttribute('aria-busy','true'); output.textContent = 'Consultando…';
        try {
            const url = new URL(lookup.dataset.url, window.location.origin);
            url.searchParams.set('digitos',input.value); url.searchParams.set('anio',lookup.dataset.year); url.searchParams.set('tipo',lookup.dataset.kind);
            const data = await request(url);
            output.textContent = data.encontrado ? `Dígitos ${data.digitos}: vence el ${formatDate(data.fecha_vencimiento)}.` : 'No hay una fecha de vencimiento disponible para esta consulta.';
        } catch (error) { showRequestError(output,error); }
        finally { lookup.disabled = false; lookup.removeAttribute('aria-busy'); }
    });
    const calculate = document.getElementById('btnCalcular');
    calculate?.addEventListener('click', async () => {
        const canonical = document.getElementById('simulador_base'), visible = document.getElementById('simulador_base-display'), output = document.getElementById('resultadoSimulador');
        output.classList.remove('hidden'); output.setAttribute('role','status');
        try { if (visible?.validity.customError || !canonical.value || decimal(canonical.value).lt('0')) throw new Error(); }
        catch { output.textContent = 'Indica una base gravable válida, mayor o igual a cero.'; visible?.focus(); return; }
        calculate.disabled = true; calculate.setAttribute('aria-busy','true'); output.textContent = 'Calculando…';
        try {
            const data = await request(calculate.dataset.url,{method:'POST',data:{base_uvt:canonical.value}});
            output.textContent = `Base: ${formatQuantity(String(data.base_uvt))} UVT. Impuesto: ${formatQuantity(String(data.impuesto_uvt))} UVT (${formatMoney(String(data.impuesto_cop))}).`;
        } catch (error) { showRequestError(output,error); }
        finally { calculate.disabled = false; calculate.removeAttribute('aria-busy'); }
    });
}
