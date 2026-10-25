import { getCategoryVisual } from '../ui/icons/categoryIcons.js';
import { ICONS } from '../ui/icons/icons.js';

export const legacyIcons = {
    home: ['cat-vivienda', 'Vivienda'], utensils: ['cat-alimentacion', 'Alimentación'], car: ['cat-transporte', 'Transporte'],
    heartbeat: ['cat-salud', 'Salud'], 'graduation-cap': ['cat-educacion', 'Educación'], bolt: ['cat-servicios-publicos', 'Servicios públicos'],
    wifi: ['cat-telecomunicaciones', 'Telecomunicaciones'], film: ['cat-entretenimiento', 'Entretenimiento'], tshirt: ['cat-vestuario', 'Vestuario'],
    'credit-card': ['pay-tarjeta-credito', 'Tarjeta de crédito'], landmark: ['cat-obligaciones-financieras', 'Obligaciones financieras'],
    'user-shield': ['cat-seguridad-social', 'Seguridad social'], umbrella: ['cat-seguros', 'Seguros'], paw: ['cat-mascotas', 'Mascotas'],
    'hand-holding-heart': ['cat-donaciones', 'Donaciones'], 'ellipsis-h': ['cat-otros', 'Otros'],
};
const sourceIcons = {
    Salario: 'src-salario', 'Honorarios/Servicios': 'src-honorarios-servicios', Arrendamientos: 'src-arrendamientos',
    'Rendimientos financieros': 'src-rendimientos-financieros', Dividendos: 'src-dividendos', 'Pensión': 'src-pension',
    'Venta de activos': 'src-venta-de-activos', 'Negocio propio': 'src-negocio-propio', Otros: 'src-otros-ingresos',
};

const colors = ['emerald', 'sky', 'violet', 'pink', 'amber', 'orange', 'red', 'teal', 'lime', 'indigo', 'rose', 'slate'];
// El catálogo es del servidor; solo se traduce su hex a la paleta existente.
export function catalogVisual(category, palette) {
    const hex = String(category?.color_hex ?? '').trim().toUpperCase();
    const color = colors.find((name) => hex && hex === String(palette[name] ?? '').trim().toUpperCase()) ?? 'slate';
    const icon = category?.icono;
    const iconName = Object.hasOwn(ICONS, icon) ? icon : legacyIcons[icon]?.[0] ?? sourceIcons[category?.typeName] ?? getCategoryVisual(category?.nombre ?? '').iconName;
    return getCategoryVisual(category, { color, iconName });
}
export function categoryPalette(style) {
    return Object.fromEntries(colors.map((name) => [name, style.getPropertyValue(`--cat-${name}`)]));
}
