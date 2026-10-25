export { empty_ingresos } from './empty-ingresos.js';
export { empty_gastos } from './empty-gastos.js';
export { empty_resultados } from './empty-resultados.js';
export { error_generico } from './error-generico.js';
export { acceso_denegado } from './acceso-denegado.js';
export { sesion_expirada } from './sesion-expirada.js';
export { recurso_no_encontrado } from './recurso-no-encontrado.js';

import { empty_ingresos } from './empty-ingresos.js';
import { empty_gastos } from './empty-gastos.js';
import { empty_resultados } from './empty-resultados.js';
import { error_generico } from './error-generico.js';
import { acceso_denegado } from './acceso-denegado.js';
import { sesion_expirada } from './sesion-expirada.js';
import { recurso_no_encontrado } from './recurso-no-encontrado.js';

export const ILLUSTRATIONS = { empty_ingresos, empty_gastos, empty_resultados, error_generico, acceso_denegado, sesion_expirada, recurso_no_encontrado };
export function renderIllustration(name, { className = '' } = {}) {
  const svg = ILLUSTRATIONS[name];
  if (!svg) throw new Error('Ilustración inexistente: ' + name);
  const wrapper = document.createElement('div');
  wrapper.innerHTML = svg;
  const element = wrapper.firstElementChild;
  if (className) element.setAttribute('class', className);
  return element;
}
