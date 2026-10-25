1. Fuente de verdad: docs/ (RF v1.1, RNF v1.1), con las decisiones explícitas del Product Owner como actualización de alcance. No agregues funcionalidades, campos, pantallas ni textos que no estén en los requisitos o en esas decisiones.
2. Terminología: "Gasto" (nunca "Costo"), "Ingreso", "Fuente de ingreso", "Medio de pago", "Naturaleza", "Necesidad", "Relevancia tributaria".
3. Dinero: prohibida aritmética con float/Number sobre montos; usa strings decimales y una librería decimal. Rango de monto: >0 y ≤ 999.999.999.999,99. Moneda única: COP, formato $ 1.234.567 (miles '.', decimales ',' solo si aplica); sin conversión de monedas. Si el contrato exige el campo moneda, enviar siempre COP; si no lo exige, omitirlo.
4. Fechas: mostrar DD/MM/AAAA; zona America/Bogota; evita corrimientos de día por UTC.
5. Validación: el servidor es la autoridad; el cliente replica las reglas para UX. Error junto al campo nombrando la regla; obligatorios con "*". Mapea errores del servidor a campos. Nunca muestres trazas, SQL ni nombres de servidor (RNF-012).
6. Toda vista tiene estados: carga (skeleton), vacío, error y éxito. Eliminar siempre con ConfirmDialog (RNG-07).
7. Listados: paginación en servidor, 20 por defecto (10/20/50/100), orden por fecha desc; los totales del conjunto filtrado completo vienen del servidor; filtros en query string.
8. Seguridad: nada de tokens en localStorage/sessionStorage; sesión por cookie HttpOnly; nada de innerHTML con datos del usuario; 401 → login con "Tu sesión expiró por inactividad"; 403 → acceso denegado; 404 → "Recurso no encontrado".
9. Responsive 360–1920 px sin scroll horizontal (tablas colapsan a tarjetas en móvil).
10. WCAG 2.1 AA: contraste ≥4.5:1 en texto y ≥3:1 en UI; cuerpo de 16 px y ninguna cadena visible <13 px; foco visible; todo operable con teclado; labels asociados; la información no depende solo del color; gráficos con alternativa tabular; errores con aria-live.
11. UI en español de Colombia.
12. Íconos: solo <Icon name="..."/> de src/ui/icons. Prohibido crear SVG ad hoc, usar imágenes raster como íconos o logos de terceros.
13. Dependencias nuevas: solo una librería decimal y charts si no existen. Justifica cualquier otra.
14. Cada tarea termina con: archivos tocados, TODO(contrato) pendientes, requisitos cubiertos (RN-xxx.x) y comandos de verificación ejecutados.

## Convenciones del repositorio

La raíz del front es `resources/`; `docs/` está en la raíz del repositorio.
Blade utiliza `<x-icon name="..." />`, equivalente local de `<Icon>`; el registro existente vive en `resources/js/ui/icons`. No modificar el kit.
Los componentes del sistema de diseño se usan como `<x-ui.*>`; los componentes Blade existentes se reutilizan donde corresponda.

## Dirección de diseño

Interfaz sobria, calmada y legible para tomar decisiones con dinero real. Fondos neutros frío-oscuros, superficies sólidas y acento esmeralda. El verde se reserva para acentos, estados positivos, brillo y cuadrícula. Sin neón ni gradientes decorativos. La paleta `--cat-*` conserva su uso semántico.

Tokens clave en `resources/css/design-system.css`: `--ui-bg`, `--ui-surface`, `--ui-raised`, `--ui-text`, `--ui-muted`, `--ui-border` para tarjetas y separadores, `--ui-border-strong` para controles; `--accent` es el acento compartido y `--accent-contrast` el texto oscuro sobre él. Tipografía base de 16 px, etiquetas de 14 px, metadatos de 13 px, títulos de 30–40 px y montos destacados de 32–48 px. Números tabulares. Escala de espacio de 4/8/12/16/24/32/48/64 px, radios de 8/14 px y sombras solo en elementos flotantes.

Jerarquía: un título principal por vista, un monto o resultado dominante cuando corresponda y acciones primarias contenidas. Agrupar por significado; usar divisores y espacio antes de añadir tarjetas. Las tarjetas no deben tener todas la misma importancia. Formularios en una columna legible o grupos de dos columnas cuando el ancho lo permita; etiquetas sobre los controles y ayudas debajo. Controles de al menos 48 px, áreas táctiles de al menos 44 px y tablas con filas holgadas que se convierten en tarjetas en móvil. Reservar el color para selección, estados y datos categóricos; acompañarlo siempre de texto. Foco visible y movimiento reducido respetado.

No mostrar códigos internos en ayudas ni mensajes: escribir en español claro y conservar los códigos solo en comentarios y documentación.

La guía de desarrollo `/dev/styleguide` se sirve únicamente con Vite. Sus ejemplos están identificados como ficticios; no realizan operaciones de negocio ni se incluyen en producción.

Las pantallas nuevas tienen libertad de presentación (composición, jerarquía, agrupación de campos) dentro de este sistema; el contenido y las reglas son innegociables.
