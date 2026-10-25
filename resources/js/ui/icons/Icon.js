import { ICONS } from './icons.js';
export function renderIcon(name, { size = 24, strokeWidth = 1.75, label, className = '' } = {}) {
  const resolved = Object.prototype.hasOwnProperty.call(ICONS, name) ? name : 'cat-otros';
  if (!Object.prototype.hasOwnProperty.call(ICONS, name) && import.meta.env?.DEV) throw new Error('Icono inexistente: ' + name);
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor');
  svg.setAttribute('stroke-width', strokeWidth); svg.setAttribute('stroke-linecap', 'round'); svg.setAttribute('stroke-linejoin', 'round');
  svg.setAttribute('width', size); svg.setAttribute('height', size); if (className) svg.setAttribute('class', className);
  if (label) { svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', label); } else { svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('focusable', 'false'); }
  svg.innerHTML = ICONS[resolved]; return svg;
}
