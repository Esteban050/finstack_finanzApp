import { request } from './index.js';

export function creditsApi(base) {
    return {
        list: (page,size,signal)=>request(`${base}?page=${page}&por_pagina=${size}`,{signal}),
        debt: signal=>request(base+'/endeudamiento',{signal}),
        detail: (id,signal)=>request(`${base}/${id}`,{signal}),
        calculate: (data,signal)=>request(base+'/calcular',{method:'POST',data,signal}),
        save: (data,id)=>request(id?`${base}/${id}`:base,{method:id?'PUT':'POST',data}),
        remove: id=>request(`${base}/${id}`,{method:'DELETE'}),
        payments: (id,signal)=>request(`${base}/${id}/pagos`,{signal}),
        simulate: (id,data,signal)=>request(`${base}/${id}/pagos/simular`,{method:'POST',data,signal}),
        pay: (id,data)=>request(`${base}/${id}/pagos`,{method:'POST',data}),
        reverse: (id,paymentId,motivo)=>request(`${base}/${id}/pagos/${paymentId}/reversar`,{method:'POST',data:{motivo}}),
    };
}
