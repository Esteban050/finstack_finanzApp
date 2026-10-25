Formato: un solo icons.js con el contenido interno de cada SVG. El envoltorio (viewBox 24, stroke="currentColor", grosor 1.75) se agrega una sola vez con un helper. Así Codex lo consume sin 87 archivos sueltos y los iconos heredan el color de la categoría vía currentColor.

1. icons.js (los 87 iconos)
js
// icons.js — set SCFP · 87 iconos · viewBox 24 · trazo 1.75 · currentColor
const ICONS = {
  // ── Lote 1 · categorías de gasto (1/2)
  'cat-vivienda': '<path d="M3 11l9-8 9 8"/><path d="M5 9.5V20h14V9.5"/><path d="M10 20v-6h4v6"/>',
  'cat-alimentacion': '<path d="M5 3v5a2 2 0 0 0 4 0V3"/><path d="M7 3v5"/><path d="M7 10v11"/><path d="M17 21V3c-2.3 1.3-3.5 4.2-3.5 7.5H17"/>',
  'cat-transporte': '<path d="M4 17H3a1 1 0 0 1-1-1v-3.5c0-.4.1-.8.3-1.1L4.5 7.8A2 2 0 0 1 6.3 7H14a2 2 0 0 1 1.5.7L18 10.5l2.5 1a1.5 1.5 0 0 1 1 1.4V16a1 1 0 0 1-1 1h-1"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6"/>',
  'cat-salud': '<path d="M12 21s-8-5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 10c0 6-8 11-8 11z"/><path d="M7.5 12.5H10l1.5-3 2 5 1.5-2h1.5"/>',
  'cat-educacion': '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/><path d="M22 9v5"/>',
  'cat-servicios-publicos': '<path d="M8 3L4.5 12H8l-1 9 5-11H8.5z"/><path d="M17.5 8c-2.5 3.2-4 5.4-4 7.5a4 4 0 0 0 8 0c0-2.1-1.5-4.3-4-7.5z"/>',
  'cat-telecomunicaciones': '<rect x="4" y="3" width="10" height="18" rx="2"/><path d="M8 18h2"/><path d="M17.2 8.8a2.2 2.2 0 0 1 0 3.4"/><path d="M19.8 6.5a5.5 5.5 0 0 1 0 8"/>',
  'cat-entretenimiento': '<rect x="3" y="10" width="18" height="11" rx="1.5"/><path d="M3 10V6a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v4"/><path d="M7.5 5L5.5 10M13 5l-2 5M18.5 5l-2 5"/>',

  // ── Lote 2 · categorías de gasto (2/2)
  'cat-vestuario': '<path d="M8 3L3 6l2 4 3-1.2V21h8V8.8L19 10l2-4-5-3a4 4 0 0 1-8 0z"/>',
  'cat-obligaciones-financieras': '<path d="M3 9l9-6 9 6z"/><path d="M6 12v6M10 12v6M14 12v6M18 12v6"/><path d="M4 18h16M3 21h18"/>',
  'cat-impuestos': '<path d="M5 3h14v18l-3.5-2-3.5 2-3.5-2L5 21z"/><circle cx="9.5" cy="8.5" r="1"/><circle cx="14.5" cy="13.5" r="1"/><path d="M15 7.5l-6 7"/>',
  'cat-seguridad-social': '<path d="M12 3l8 3v5.5c0 4.5-3.2 8-8 9.5-4.8-1.5-8-5-8-9.5V6z"/><path d="M12 9v6M9 12h6"/>',
  'cat-seguros': '<path d="M3 12a9 9 0 0 1 18 0z"/><path d="M12 12v7a2 2 0 0 1-4 0"/><path d="M12 3V2"/>',
  'cat-mascotas': '<path d="M12 12.5c-3 0-5.5 2.8-5.5 5 0 1.8 1.3 2.5 3 2.5 1 0 1.6-.4 2.5-.4s1.5.4 2.5.4c1.7 0 3-.7 3-2.5 0-2.2-2.5-5-5.5-5z"/><circle cx="5.5" cy="9.5" r="1.6"/><circle cx="9.5" cy="5" r="1.6"/><circle cx="14.5" cy="5" r="1.6"/><circle cx="18.5" cy="9.5" r="1.6"/>',
  'cat-donaciones': '<path d="M12 11.5c-2.5-1.6-3.7-3.2-3.7-4.8a2 2 0 0 1 3.7-1.1 2 2 0 0 1 3.7 1.1c0 1.6-1.2 3.2-3.7 4.8z"/><rect x="2.5" y="14.5" width="3.5" height="6.5" rx="1"/><path d="M6 16l3.5-1.5h5a1.7 1.7 0 0 1 0 3.4H11"/><path d="M6 19.5l5 1.5 7-2.5 2.3-1.8a1.5 1.5 0 0 0-1.9-2.3L17 15.5"/>',
  'cat-otros': '<circle cx="12" cy="12" r="9"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>',

  // ── Lote 3 · tipos de fuente de ingreso (1/2)
  'src-salario': '<rect x="3" y="9" width="18" height="11" rx="2"/><path d="M7 9V5.5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1V9"/><circle cx="12" cy="7" r="1.2"/><path d="M3 14h18"/>',
  'src-honorarios-servicios': '<path d="M13 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/><path d="M13 3l5 5v1.5"/><path d="M13 3v5h5"/><path d="M8.5 12l2 2 3.5-4"/><path d="M20.5 11.2l2 2-7 7-3 .9.9-3z"/>',
  'src-arrendamientos': '<rect x="3" y="3" width="10" height="18" rx="1.5"/><path d="M6.5 7h.01M9.5 7h.01M6.5 10.5h.01M9.5 10.5h.01M6.5 14h.01M9.5 14h.01"/><path d="M6.5 21v-3.5h3V21"/><circle cx="17" cy="13" r="2.5"/><path d="M18.8 14.8l2.9 2.9M20.4 16.4l1.4-1.4"/>',
  'src-rendimientos-financieros': '<path d="M3 18l5.5-5.5 3.5 3L16 11"/><circle cx="18" cy="7" r="3"/><path d="M18 5.8v2.4"/>',
  'src-dividendos': '<path d="M10 6a7 7 0 1 0 7 7h-7z"/><path d="M12 4a7 7 0 0 1 7 7h-7z"/><circle cx="19" cy="19" r="2.2"/>',
  'src-pension': '<path d="M7 3h10M7 21h10"/><path d="M8 3v3.5a4 4 0 0 0 1.5 3.1L12 12l-2.5 2.4A4 4 0 0 0 8 17.5V21"/><path d="M16 3v3.5a4 4 0 0 1-1.5 3.1L12 12l2.5 2.4A4 4 0 0 1 16 17.5V21"/><circle cx="12" cy="18" r="1.4"/>',
  'src-venta-de-activos': '<path d="M3 12.2V4a1 1 0 0 1 1-1h8.2a1 1 0 0 1 .7.3l8.1 8.1a1.5 1.5 0 0 1 0 2.1l-6.9 6.9a1.5 1.5 0 0 1-2.1 0L3.3 12.9a1 1 0 0 1-.3-.7z"/><path d="M7.5 7.5h.01"/><path d="M11.5 14l4.5-4.5M12.5 9.5H16V13"/>',
  'src-negocio-propio': '<path d="M3 9l1.5-5h15L21 9a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0z"/><path d="M5 11.5V20h14v-8.5"/><path d="M10 20v-5h4v5"/>',

  // ── Lote 4 · otros ingresos, medios de pago, etiqueta
  'src-otros-ingresos': '<ellipse cx="10" cy="6" rx="6" ry="2.5"/><path d="M4 6v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5V6"/><path d="M4 10v4c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5v-4"/><path d="M19.5 15v5M17 17.5h5"/>',
  'pay-efectivo': '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
  'pay-tarjeta-debito': '<rect x="2.5" y="5" width="19" height="14" rx="2"/><rect x="5.5" y="9" width="4" height="3" rx=".6"/><path d="M5.5 15.5h6"/>',
  'pay-tarjeta-credito': '<path d="M6 8V5.5A1.5 1.5 0 0 1 7.5 4h12A1.5 1.5 0 0 1 21 5.5v8a1.5 1.5 0 0 1-1.5 1.5H19"/><rect x="3" y="8" width="16" height="12" rx="1.5"/><path d="M3 12h16"/><path d="M6 16.5h3"/>',
  'pay-transferencia': '<path d="M4 8h15M15.5 4.5L19 8l-3.5 3.5M20 16H5M8.5 12.5L5 16l3.5 3.5"/>',
  'pay-billetera-digital': '<path d="M13 10V4.5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h4"/><path d="M8 5.5h1"/><rect x="10" y="11.5" width="11.5" height="9" rx="2"/><path d="M21.5 14.5H19a1.5 1.5 0 0 0 0 3h2.5"/>',
  'pay-otro': '<rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>',
  'cls-etiqueta': '<path d="M3 7a2 2 0 0 1 2-2h10.5L21 12l-5.5 7H5a2 2 0 0 1-2-2z"/><circle cx="7" cy="12" r="1.3"/>',

  // ── Lote 5 · clasificación del gasto + movimientos
  'cls-fijo': '<path d="M9 3h6M10.2 3l-.7 6-3 3.5V14h11v-1.5L14.5 9l-.7-6M12 14v7"/>',
  'cls-variable': '<path d="M3 12c1.2-6 3.3-6 4.5 0s3.3 6 4.5 0 3.3-6 4.5 0 3.3 6 4.5 0"/>',
  'cls-esencial': '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5"/><path d="M5.6 5.6l3.9 3.9M14.5 14.5l3.9 3.9M18.4 5.6l-3.9 3.9M9.5 14.5l-3.9 3.9"/>',
  'cls-discrecional': '<path d="M11 3c.6 4.7 2.3 6.4 8 8-5.7 1.6-7.4 3.3-8 8-.6-4.7-2.3-6.4-8-8 5.7-1.6 7.4-3.3 8-8z"/><path d="M19.5 2.5v4M17.5 4.5h4"/>',
  'cls-no-deducible': '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M4 4l16 16"/>',
  'cls-deducible': '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 14.5l2.2 2.2 4-4.7"/>',
  'act-ingreso': '<path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="M17 5L8 14M8 8.5V14h5.5"/>',
  'act-gasto': '<path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/><path d="M8 14L17 5M11.5 5H17v5.5"/>',

  // ── Lote 6 · acciones de dinero y estados
  'act-balance': '<path d="M12 3v17M8 21h8M5 6h14"/><path d="M5 6L2.5 13a2.5 2.5 0 0 0 5 0z"/><path d="M19 6l-2.5 7a2.5 2.5 0 0 0 5 0z"/>',
  'act-recurrente': '<path d="M20 12a8 8 0 0 0-13.7-5.6L4 8.5M4 4v4.5h4.5"/><path d="M4 12a8 8 0 0 0 13.7 5.6L20 15.5M20 20v-4.5h-4.5"/>',
  'act-pendiente': '<circle cx="12" cy="12" r="9"/><path d="M12 12V6.5M12 12h4"/>',
  'act-confirmar': '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/>',
  'act-descartar': '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
  'act-pausar': '<circle cx="12" cy="12" r="9"/><path d="M10 9v6M14 9v6"/>',
  'act-terminar': '<circle cx="12" cy="12" r="9"/><rect x="9" y="9" width="6" height="6" rx="1"/>',
  'act-neto': '<rect x="5" y="2.5" width="14" height="19" rx="2"/><rect x="8" y="5.5" width="8" height="4" rx=".8"/><path d="M8.5 14h1.5M14 14h1.5M8.5 18h1.5M14 18h1.5"/>',

  // ── Lote 7 · conceptos de cálculo y gestión
  'act-retencion': '<path d="M13.5 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4M13.5 3L19 8.5V11M13.5 3v5.5H19"/><path d="M8.5 15l4-5M9 10h.01M12.5 15h.01"/><path d="M17 14v7M14.5 18.5L17 21l2.5-2.5"/>',
  'act-aportes': '<path d="M12 3l8 3v5.5c0 4.5-3.2 8-8 9.5-4.8-1.5-8-5-8-9.5V6z"/><path d="M12 15c-2-1.3-3-2.5-3-3.8a1.6 1.6 0 0 1 3-.8 1.6 1.6 0 0 1 3 .8c0 1.3-1 2.5-3 3.8z"/>',
  'act-moneda-cop': '<circle cx="12" cy="12" r="9"/><path d="M14.5 9.2c-.4-1-1.4-1.5-2.5-1.5-1.5 0-2.5.8-2.5 2 0 1.2 1 1.7 2.5 2s2.5.8 2.5 2c0 1.2-1 2-2.5 2-1.1 0-2.1-.5-2.5-1.5"/><path d="M12 6v1.7M12 15.7V18"/>',
  'act-clasificacion-tributaria': '<path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><circle cx="12" cy="14.2" r="2.8"/><path d="M10.9 14.3l.9.9 1.5-1.7"/>',
  'act-fusionar': '<path d="M4 4l7 7.5M20 4l-7 7.5M12 11v10M8.5 17.5L12 21l3.5-3.5"/>',
  'act-reordenar': '<circle cx="9" cy="5" r="1" fill="currentColor"/><circle cx="15" cy="5" r="1" fill="currentColor"/><circle cx="9" cy="12" r="1" fill="currentColor"/><circle cx="15" cy="12" r="1" fill="currentColor"/><circle cx="9" cy="19" r="1" fill="currentColor"/><circle cx="15" cy="19" r="1" fill="currentColor"/>',
  'act-inactivar': '<circle cx="12" cy="12" r="9"/><path d="M5.7 5.7l12.6 12.6"/>',
  'act-seleccion-masiva': '<path d="M8.5 8V5.5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-3"/><rect x="3.5" y="8.5" width="12" height="12" rx="2"/><path d="M7 14.5l2.5 2.5 4-5"/>',

  // ── Lote 8 · navegación y acciones de interfaz
  'ui-resumen': '<rect x="3" y="3" width="8" height="10" rx="1.5"/><rect x="13" y="3" width="8" height="6" rx="1.5"/><rect x="13" y="11" width="8" height="10" rx="1.5"/><rect x="3" y="15" width="8" height="6" rx="1.5"/>',
  'ui-fuentes': '<circle cx="5" cy="5.5" r="2"/><circle cx="12" cy="5.5" r="2"/><circle cx="19" cy="5.5" r="2"/><path d="M12 7.5V16M5 7.5v1.5a4 4 0 0 0 4 4h3M19 7.5v1.5a4 4 0 0 1-4 4h-3"/><circle cx="12" cy="18.5" r="2.5"/>',
  'ui-categorias': '<rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="2"/>',
  'ui-editar': '<path d="M4 20l1.2-4.3 11.3-11.2a2.1 2.1 0 0 1 3 3L8.2 18.8z"/><path d="M12.5 21h8"/>',
  'ui-eliminar': '<path d="M4 6h16M10 3h4M6 6l1 14a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-14M10 10.5v6M14 10.5v6"/>',
  'ui-filtrar': '<path d="M3 4h18l-7 8.5V19l-4 2v-8.5z"/>',
  'ui-buscar': '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/>',
  'ui-calendario': '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 17.5h.01M12 17.5h.01"/>',

  // ── Lote 9 · avisos y acciones básicas
  'ui-ayuda': '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.6 2.2c-.7.4-1.1 1-1.1 1.8M12 17h.01"/>',
  'ui-info': '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.5h.01"/>',
  'ui-advertencia': '<path d="M10.3 4.2L2.8 17.5A2 2 0 0 0 4.5 20.5h15a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0z"/><path d="M12 9.5v4M12 17h.01"/>',
  'ui-exito': '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16.3 9.3"/>',
  'ui-error': '<circle cx="12" cy="12" r="9"/><path d="M9.2 9.2l5.6 5.6M14.8 9.2l-5.6 5.6"/>',
  'ui-cerrar': '<path d="M6 6l12 12M18 6L6 18"/>',
  'ui-mas': '<path d="M12 5v14M5 12h14"/>',
  'ui-menu': '<path d="M4 6h16M4 12h16M4 18h16"/>',

  // ── Lote 10 · cuenta y seguridad
  'ui-usuario': '<circle cx="12" cy="8" r="4"/><path d="M4.5 21v-1a5.5 5.5 0 0 1 5.5-5.5h4a5.5 5.5 0 0 1 5.5 5.5v1"/>',
  'ui-cerrar-sesion': '<path d="M10 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h4"/><path d="M10 12h11M17 8l4 4-4 4"/>',
  'ui-notificaciones': '<path d="M6 9a6 6 0 0 1 12 0c0 6 2 7.5 2 7.5H4S6 15 6 9z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
  'ui-ojo': '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
  'ui-ojo-tachado': '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/>',
  'ui-candado': '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/><path d="M12 15.2V17"/>',
  'ui-correo': '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3.5 7l8.5 6 8.5-6"/>',
  'ui-politica-datos': '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M12 11l3 1.1v2.4c0 1.8-1.3 3.1-3 3.8-1.7-.7-3-2-3-3.8v-2.4z"/>',

  // ── Lote 11 · direccionales
  'ui-chevron-izquierda': '<path d="M14.5 6l-6 6 6 6"/>',
  'ui-chevron-derecha': '<path d="M9.5 6l6 6-6 6"/>',
  'ui-chevron-arriba': '<path d="M6 14.5l6-6 6 6"/>',
  'ui-chevron-abajo': '<path d="M6 9.5l6 6 6-6"/>',
  'ui-volver': '<path d="M20 12H5M11 6l-6 6 6 6"/>',
  'ui-ordenar': '<path d="M8 20V5M4.5 8.5L8 5l3.5 3.5M16 4v15M12.5 15.5L16 19l3.5-3.5"/>',
  'ui-cargando': '<path d="M12 3a9 9 0 1 0 9 9"/>',
};

// Helper: envoltorio único. Hereda color con currentColor.
function iconSvg(key, size = 24, stroke = 1.75) {
  const body = ICONS[key];
  if (!body) throw new Error('Icono inexistente: ' + key);
  return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' + size +
    '" height="' + size + '" fill="none" stroke="currentColor" stroke-width="' + stroke +
    '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + body + '</svg>';
}
2. preview.html (para verificarlos tú)

Guárdalo junto a icons.js y ábrelo en el navegador.

html
<!doctype html>
<meta charset="utf-8">
<title>Preview iconos SCFP</title>
<style>
  body{background:#0B1210;color:#E5E7EB;font:12px system-ui;margin:24px}
  .grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
  .cell{border:1px solid #1f2d28;border-radius:10px;padding:12px;display:flex;flex-direction:column;align-items:center;gap:8px}
  .row{display:flex;align-items:center;gap:12px;color:#34D399}
  .k{color:#9CA3AF;font-size:10px;text-align:center;word-break:break-all}
</style>
<div class="grid" id="g"></div>
<script src="icons.js"></script>
<script>
  const g = document.getElementById('g');
  Object.keys(ICONS).forEach(k => {
    g.insertAdjacentHTML('beforeend',
      '<div class="cell"><div class="row">' + iconSvg(k,16) + iconSvg(k,24) + iconSvg(k,48) +
      '</div><div class="k">' + k + '</div></div>');
  });
</script>

Qué mirar: a 16 px ningún trazo debe fundirse con otro, y el peso visual debe ser parejo.

3. Paleta cerrada para color de categoría (RN-027.4)

Doce colores, todos con contraste alto sobre 
#0B1210:

css
:root{
  --cat-emerald:#34D399; --cat-sky:#38BDF8;  --cat-violet:#A78BFA; --cat-pink:#F472B6;
  --cat-amber:#FBBF24;   --cat-orange:#FB923C; --cat-red:#F87171;  --cat-teal:#2DD4BF;
  --cat-lime:#A3E635;    --cat-indigo:#818CF8; --cat-rose:#FB7185;  --cat-slate:#94A3B8;
}

Valores iniciales por categoría, solo como respaldo si el backend no entrega ícono/color:

js
const CATEGORY_DEFAULTS = {
  'Vivienda':{icon:'cat-vivienda',color:'sky'}, 'Alimentación':{icon:'cat-alimentacion',color:'amber'},
  'Transporte':{icon:'cat-transporte',color:'indigo'}, 'Salud':{icon:'cat-salud',color:'rose'},
  'Educación':{icon:'cat-educacion',color:'violet'}, 'Servicios públicos':{icon:'cat-servicios-publicos',color:'teal'},
  'Telecomunicaciones':{icon:'cat-telecomunicaciones',color:'sky'}, 'Entretenimiento':{icon:'cat-entretenimiento',color:'pink'},
  'Vestuario':{icon:'cat-vestuario',color:'orange'}, 'Obligaciones financieras':{icon:'cat-obligaciones-financieras',color:'red'},
  'Impuestos':{icon:'cat-impuestos',color:'slate'}, 'Seguridad social':{icon:'cat-seguridad-social',color:'emerald'},
  'Seguros':{icon:'cat-seguros',color:'lime'}, 'Mascotas':{icon:'cat-mascotas',color:'orange'},
  'Donaciones':{icon:'cat-donaciones',color:'pink'}, 'Otros':{icon:'cat-otros',color:'slate'},
};
4. Fondos

En background-image, currentColor no funciona. Por eso estos usan colores fijos con opacidad.

bg-grid.svg (cuadrícula tenue que se repite):

svg
<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%">
  <defs>
    <pattern id="g" width="32" height="32" patternUnits="userSpaceOnUse">
      <path d="M32 0H0V32" fill="none" stroke="#6EE7B7" stroke-opacity=".07" stroke-width="1"/>
    </pattern>
  </defs>
  <rect width="100%" height="100%" fill="url(#g)"/>
</svg>

Para que se desvanezca hacia los bordes:

css
.auth-bg{background:url(bg-grid.svg);
  -webkit-mask-image:radial-gradient(ellipse at center,#000 30%,transparent 75%);
          mask-image:radial-gradient(ellipse at center,#000 30%,transparent 75%)}

bg-glow.svg (resplandores esmeralda y verde azulado sobre fondo oscuro):

svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice">
  <defs>
    <radialGradient id="a" cx="22%" cy="18%" r="48%">
      <stop offset="0" stop-color="#34D399" stop-opacity=".22"/><stop offset="1" stop-color="#34D399" stop-opacity="0"/>
    </radialGradient>
    <radialGradient id="b" cx="85%" cy="88%" r="45%">
      <stop offset="0" stop-color="#2DD4BF" stop-opacity=".16"/><stop offset="1" stop-color="#2DD4BF" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <rect width="1200" height="800" fill="#0B1210"/>
  <rect width="1200" height="800" fill="url(#a)"/>
  <rect width="1200" height="800" fill="url(#b)"/>
</svg>

bg-auth-lines.svg (líneas de flujo para el panel de marca del login):

svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 1000" preserveAspectRatio="xMidYMid slice" fill="none" stroke="#34D399" stroke-width="1.2">
  <defs><path id="w" d="M-60 260C120 170 260 400 440 310S700 190 860 300"/></defs>
  <g stroke-opacity=".10">
    <use href="#w" y="0"/><use href="#w" y="48"/><use href="#w" y="96"/><use href="#w" y="144"/>
    <use href="#w" y="192"/><use href="#w" y="240"/><use href="#w" y="288"/><use href="#w" y="336"/>
    <use href="#w" y="384"/><use href="#w" y="432"/><use href="#w" y="480"/><use href="#w" y="528"/>
  </g>
</svg>
5. Marca (opcional)

El cerdito se queda. Este es un isotipo simple para el logo del sidebar y el favicon, por si no quieres usar el cerdito ahí:

svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
  <defs><linearGradient id="m" x1="0" y1="0" x2="1" y2="1">
    <stop offset="0" stop-color="#34D399"/><stop offset="1" stop-color="#059669"/></linearGradient></defs>
  <rect x="2" y="2" width="28" height="28" rx="8" fill="url(#m)"/>
  <path d="M12 24V13c0-4 3-6 10-6-.1 6-2.7 9-6.7 9.3H12" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
6. Ilustraciones de estados (7)

Son para vacío, error y accesos. El trazo usa currentColor; el acento usa var(--accent) con respaldo esmeralda. Tamaño 160×120.

empty-ingresos

svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 120" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <path d="M30 70L44 42a6 6 0 0 1 5.4-3.4h61.2a6 6 0 0 1 5.4 3.4L130 70"/>
  <path d="M30 70v26a6 6 0 0 0 6 6h88a6 6 0 0 0 6-6V70h-30l-6 12H66L60 70z"/>
  <circle cx="80" cy="20" r="10" stroke="var(--accent,#34D399)"/>
  <path d="M80 15v10M76 20h8" stroke="var(--accent,#34D399)"/>
  <path d="M80 34v10" stroke="var(--accent,#34D399)" stroke-dasharray="2 5"/>
</svg>

empty-gastos

svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 120" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <path d="M46 14h68v90l-11.3-7.5L91.3 104 80 96.5 68.7 104 57.3 96.5 46 104z"/>
  <path d="M60 38h40M60 54h40M60 70h22" stroke-dasharray="3 6"/>
  <path d="M128 20v14M121 27h14" stroke="var(--accent,#34D399)"/>
</svg>

empty-resultados (filtros sin resultados)

svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 120" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <circle cx="72" cy="54" r="28"/>
  <path d="M92 74l26 26"/>
  <path d="M60 54h24" stroke="var(--accent,#34D399)"/>
  <path d="M20 102h28M112 22h28" stroke-opacity=".5" stroke-dasharray="3 6"/>
</svg>

error-generico

svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 120" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <path d="M72.9 20.1L28.1 96.8A8 8 0 0 0 35 108.8h90a8 8 0 0 0 6.9-12L87.1 20.1a8 8 0 0 0-14.2 0z"/>
  <path d="M80 48v26M80 90h.01" stroke="var(--accent,#34D399)" stroke-width="4"/>
</svg>

acceso-denegado (403)

svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 120" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <rect x="48" y="52" width="64" height="50" rx="8"/>
  <path d="M60 52V40a20 20 0 0 1 40 0v12"/>
  <circle cx="80" cy="72" r="6" stroke="var(--accent,#34D399)"/>
  <path d="M80 78v10" stroke="var(--accent,#34D399)"/>
</svg>

sesion-expirada

svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 120" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <circle cx="80" cy="60" r="34"/>
  <path d="M80 38v22l14 8"/>
  <circle cx="80" cy="60" r="44" stroke="var(--accent,#34D399)" stroke-dasharray="4 8"/>
</svg>

recurso-no-encontrado (404)

svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 120" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
  <path d="M56 12h38l22 22v70a6 6 0 0 1-6 6H56a6 6 0 0 1-6-6V18a6 6 0 0 1 6-6z"/>
  <path d="M94 12v22h22"/>
  <path d="M70 62a10 10 0 1 1 15 8.6c-3 1.8-5 4-5 7.4M80 92h.01" stroke="var(--accent,#34D399)" stroke-width="4"/>
</svg>
7. Pendiente
Mira el preview. Los que más probablemente necesiten ajuste son cat-donaciones, src-salario, pay-billetera-digital y act-seleccion-masiva, que son los más compuestos.
Dime las claves que no te convencen y las rehago.
Cuando esté aprobado, escribo el prompt de Codex corto: integrar icons.js como componente <Icon>, conectar CATEGORY_DEFAULTS, usar los fondos y mapear las ilustraciones a los estados vacío, 403, 404 y sesión expirada.