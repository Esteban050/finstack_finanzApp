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
export const subcategoryIcons = {
    "Arriendo": "src-arrendamientos",
    "Administración y mantenimiento": "cat-vivienda",
    "Mercado y supermercado": "src-negocio-propio",
    "Restaurantes y domicilios": "cat-alimentacion",
    "Ayuda familiar": "ui-usuario",
    "Fundaciones y caridad": "cat-donaciones",
    "Cursos y libros": "cat-educacion",
    "Matrícula y pensiones": "pay-transferencia",
    "Salidas, cine y viajes": "cat-entretenimiento",
    "Suscripciones streaming": "act-recurrente",
    "GMF (4x1000) e impuesto de renta": "pay-transferencia",
    "Impuesto predial y vehicular": "cat-impuestos",
    "Alimento para mascotas": "cat-mascotas",
    "Veterinaria y vacunas": "cat-salud",
    "Cuota de crédito / hipoteca": "cat-obligaciones-financieras",
    "Cuota de manejo y comisiones": "pay-tarjeta-credito",
    "Gastos varios no clasificados": "cat-otros",
    "Imprevistos y emergencias": "ui-advertencia",
    "Medicamentos y consultas": "cat-salud",
    "Medicina prepagada": "cat-seguros",
    "Aporte PILA independiente": "act-aportes",
    "Aporte voluntario pensión / AFC": "src-pension",
    "Seguro de vida y hogar": "cat-seguros",
    "SOAT y seguro todo riesgo": "cat-transporte",
    "Acueducto y alcantarillado": "cls-esencial",
    "Energía eléctrica y gas": "cat-servicios-publicos",
    "Internet hogar": "cat-telecomunicaciones",
    "Plan de telefonía móvil": "ui-notificaciones",
    "Combustible y parqueaderos": "cat-transporte",
    "Transporte público y taxis": "pay-billetera-digital",
    "Lavandería y cuidado": "act-recurrente",
    "Ropa y calzado": "cat-vestuario"
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
    const iconName = Object.hasOwn(ICONS, icon) ? icon : subcategoryIcons[category?.nombre] ?? legacyIcons[icon]?.[0] ?? sourceIcons[category?.typeName] ?? getCategoryVisual(category?.nombre ?? '').iconName;
    return getCategoryVisual(category, { color, iconName });
}
export function categoryPalette(style) {
    return Object.fromEntries(colors.map((name) => [name, style.getPropertyValue(`--cat-${name}`)]));
}
