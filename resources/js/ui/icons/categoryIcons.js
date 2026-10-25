import { ICONS } from './icons.js';
export const CATEGORY_DEFAULTS = {
  "Vivienda": {
    "icon": "cat-vivienda",
    "color": "sky"
  },
  "Alimentación": {
    "icon": "cat-alimentacion",
    "color": "amber"
  },
  "Transporte": {
    "icon": "cat-transporte",
    "color": "indigo"
  },
  "Salud": {
    "icon": "cat-salud",
    "color": "rose"
  },
  "Educación": {
    "icon": "cat-educacion",
    "color": "violet"
  },
  "Servicios públicos": {
    "icon": "cat-servicios-publicos",
    "color": "teal"
  },
  "Telecomunicaciones": {
    "icon": "cat-telecomunicaciones",
    "color": "sky"
  },
  "Entretenimiento": {
    "icon": "cat-entretenimiento",
    "color": "pink"
  },
  "Vestuario": {
    "icon": "cat-vestuario",
    "color": "orange"
  },
  "Obligaciones financieras": {
    "icon": "cat-obligaciones-financieras",
    "color": "red"
  },
  "Impuestos": {
    "icon": "cat-impuestos",
    "color": "slate"
  },
  "Seguridad social": {
    "icon": "cat-seguridad-social",
    "color": "emerald"
  },
  "Seguros": {
    "icon": "cat-seguros",
    "color": "lime"
  },
  "Mascotas": {
    "icon": "cat-mascotas",
    "color": "orange"
  },
  "Donaciones": {
    "icon": "cat-donaciones",
    "color": "pink"
  },
  "Otros": {
    "icon": "cat-otros",
    "color": "slate"
  }
};
export function getCategoryVisual(category, fallback = {}) {
  const name = typeof category === 'string' ? category : (category?.nombre ?? category?.name ?? '');
  const defaults = CATEGORY_DEFAULTS[name] ?? { icon: 'cat-otros', color: 'slate' };
  const iconName = fallback.iconName ?? category?.icon ?? category?.icono ?? defaults.icon;
  const color = fallback.color ?? category?.color ?? category?.colorVar ?? defaults.color;
  return { iconName: hasIcon(iconName) ? iconName : 'cat-otros', colorVar: 'var(--cat-' + color + ')' };
}
function hasIcon(name) { return Object.prototype.hasOwnProperty.call(ICONS, name); }
