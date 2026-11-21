# Plan de corrección del frontend — Luis

Fecha: 20/11/2026. Base: REVISION_FRONTEND_LUIS_2026-11-20.md.

Decisión confirmada: todos los campos de valor usan puntos para miles y coma decimal, sin apóstrofos; los separadores son visuales y el envío conserva el decimal exacto. Tasas, cantidades y precios conservan su precisión; documentos, años, plazos y contadores no se agrupan como dinero.

## Secuencia y control

Cada bloque se implementa, se comprueba con casos dirigidos y se vuelve a revisar en sus pantallas antes de cerrarlo. No se modifican servicios, reglas fiscales ni bases de datos. Las dependencias de backend se documentan y no se sustituyen con fórmulas inventadas.

| Bloque | Correcciones | Comprobación de cierre | Estado |
|---|---|---|---|
| 1. Captura global de valores | F01–F02, F24–F28, F31–F33: agrupación común, todos los controles monetarios del inventario, precisión, errores, cursor y datos canónicos | Pruebas de escritura/pegado/borrado, máximos, cantidades con ocho decimales y auditoría de controles sin integrar; revisión de cada módulo afectado | Implementado y revisado |
| 2. Renta | F05–F23: eliminar avanzados y cálculo fiscal duplicado, usar endpoint existente, validar montos/ajustes, conservar datos, actualizar resultados, arreglar tablas y modales | Casos de renta con 1.234.567,89, resultado invalidado, error recuperable y claro/oscuro/móvil | Implementado y revisado; límites abajo |
| 3. Flujos funcionales | F04, F29, F34–F36, F38, F40, F42: formularios MFA, parámetros rentista, errores independientes de créditos, consultas sin respuestas obsoletas y navegación | Pruebas de regresión de contratos y formularios sin operaciones reales | Implementado y revisado; límites abajo |
| 4. Composición y accesibilidad | F03, F15–F18, F21–F23, F28, F30, F37, F39, F41, F43–F44: acciones, alineación, encabezados, fechas, iconos y recuperación | Recorrido visual por módulo, tamaños 360–1920, temas y teclado; evidencia de antes/después | Implementado y revisado; límites abajo |
| 5. Verificación completa | F45: pruebas, lint sin ampliar exclusiones, build, referencias de iconos y vistas; reevaluar hallazgos del informe | Resultados exactos de comandos, matriz de hallazgos y pendientes de integración explícitos | Implementado y revisado; límites abajo |

## Riesgos y dependencias ya identificados

- Calculadora: retirar avanzados en frontend y enviar solo los montos básicos al endpoint existente. El backend sigue siendo responsable de su estimación y de los cambios pedidos a Garay.
- Seguridad social y endeudamiento: reparar transporte/presentación; el cálculo y los topes pertenecen a Esteban/Garay.
- Monedas: conservar el soporte COP/USD ya implementado; no quitar funcionalidades de backend durante el trabajo de formato.
- Sesión de QA: si las cuentas de demo no están disponibles, usar renderizado con modelos ficticios sin consultas/escrituras y señalar qué recorridos requieren integración real.

## Registro de ejecución

- Inicio: árbol de trabajo sin cambios de implementación; se conserva el informe previo del usuario.
- Se corrigió primero el formateador común y después los controles particulares de cada módulo.

## Resultado de la ejecución

- Formato común: puntos de miles y coma decimal, sin apóstrofos al mostrar. Los controles conservan un decimal canónico para los manejadores y el envío; no se modificó la base de datos. Los pegados históricos con apóstrofos se normalizan visualmente a puntos.
- Cobertura: componentes de ingresos/gastos/recurrentes, créditos, presupuestos/metas, seguridad social/PILA, inversiones y renta; controles particulares de administración también integrados. Años, documentos, plazos y contadores conservan su significado. La regresión busca todos los controles numéricos nativos para detectar nuevos montos sin integrar.
- Precisión: dinero hasta dos decimales, TRM hasta cuatro y cantidades/precios/tasas de inversión según el contrato existente. Inversiones ya no recorta ocho decimales ni convierte texto inválido en otro monto.
- Renta: retiradas las opciones avanzadas de la calculadora; cinco montos básicos enviados al endpoint existente. Resultados se invalidan al editar, se descartan respuestas tardías y los errores permiten corregir sin reiniciar la captura. Información complementaria conserva old(), admite ajustes negativos, suma con precisión exacta y exige explicación cuando corresponde.
- Composición: formulario complementario delante del resumen/historial en móvil, columnas adaptables, tablas con etiquetas, acciones primarias y estados de peligro diferenciados, encabezados H1, etiquetas asociadas y diálogos nativos con foco/restauración. Corregidos modales de renta y administración, más formularios hermanos para 2FA.
- Flujos: TRM con fecha recurrente y protección contra respuestas obsoletas; rentista conserva modalidad/gastos en la petición; PILA rechaza autocompletados de contexto antiguo; error del indicador de deuda no bloquea la lista de créditos.
- Presentación: fechas civiles, porcentajes e iconos comunes; retirados identificadores internos de títulos; formato decimal exacto también en vistas/PDF y ayudas de gráficos.

## Correspondencia con el informe inicial

| Hallazgos | Implementación / resultado |
|---|---|
| F01–F02 | Formateador común y adaptador de controles nativos; pruebas de cobertura y precisión. |
| F03–F04 | Jerarquía de hover y navegación atrás/adelante con revalidación de la página. |
| F05–F09 | Marcadores vacíos retirados; montos con centavos y límites; calculadora simplificada conectada al servidor; sin avanzados. |
| F10 | La calculadora se presenta como simulación de cinco topes; enlace a evaluación completa del perfil. Unificar cinco/seis criterios requiere contrato del backend. |
| F11–F17 | Invalidación de resultados, conservación de datos/errores, notas y ajustes, disposición adaptable y etiquetas en filas dinámicas. |
| F18 | Etiqueta asociada y año de ruta incluido; catálogo autoritativo de vigencias pendiente de backend. |
| F19–F23 | Clase de servicio correctamente resuelta; sin fecha límite inventada; diálogos nativos y temas legibles. |
| F24–F27 | Precisión de inversiones, validación estricta, cálculos decimales y validador compartido también en registro nativo. |
| F28–F30 | Etiquetas de controles, una confirmación para eliminación y entrada de nueva inversión destacada. |
| F31–F35 | TRM validada/localizada, consultas actualizadas y contrato/contexto rentista-PILA. |
| F36–F41 | Formularios MFA válidos, controles y grupos accesibles, validación de perfil ampliada, encabezados y navegación administrativa, peticiones comunes. |
| F42 | Fallos independientes de lista e indicador; reintento del indicador. Fórmula de endeudamiento sigue en backend. |
| F43–F44 | Fechas/porcentajes, iconos, recuperación de errores, tamaño de controles y composición compartida corregidos en el recorrido disponible. |
| F45 | Pruebas ampliadas, lint sin ampliar excepciones, compilación, iconos, vistas y revisión visual posterior. |

## Verificación y evidencia

- Batería completa: **92 pruebas aprobadas, 0 fallos**. Incluye las seis clases de inversión renderizadas con modelos ficticios y consultas a BD bloqueadas.
- Lint: sintaxis de 70 archivos JS, reglas de seguridad, CSS y referencias Blade aprobadas.
- Build de producción aprobado; advertencia de tamaño del paquete principal (~501 kB) pendiente de optimización de carga, sin bloquear la compilación.
- check-icons: 87 iconos y referencias válidas. check-styleguide: herramientas de desarrollo excluidas de producción. view:cache aprobado. diff --check sin errores de espacios.
- Segunda revisión en navegador: 16 pantallas con sus vistas reales y datos ficticios a 360 px y 1280 px; sin desbordamiento horizontal de página. Cinco pantallas adicionales comprobadas a 1920 px. Claro y oscuro, campos con centavos, ajustes negativos, modales y pertenencia de campos MFA al formulario.
- Caso visible: 60.000.000 menos 1.234.567,89 = 58.765.432,11. Caso inválido: 12abc34 conserva el texto, señala el error y permite corregirlo.
- Evidencia local (carpeta ignorada por Git): qa-evidence/correccion-luis/renta-calculadora-despues.jpg, renta-ajuste-movil-oscuro.jpg y renta-modal-movil-oscuro.jpg.

## Límites pendientes de integración

1. No se obtuvo una sesión autenticada funcional con la cuenta demo existente. La revisión visual usa el shell y Blade reales con modelos ficticios; no prueba guardados reales, correo MFA, descargas autenticadas, permisos del servidor ni persistencia. No se crearon usuarios ni se alteraron contraseñas o registros.
2. Esteban/Garay deben revisar topes de seguridad social, tarjeta de crédito y fórmulas/contratos del servidor. La ruta del indicador existe; el frontend ahora permite recuperar el listado aunque falle el indicador.
3. Renta conserva cinco topes en el endpoint básico y seis criterios en la evaluación completa. El frontend aclara el alcance; una equivalencia total exige decisión y contrato del servidor.
4. La simulación informativa de beneficios de la pantalla de estimación conserva sus reglas existentes, traducidas a decimales exactos. No se verificó legislación ni se cambiaron tarifas. Necesita un endpoint autoritativo propio para eliminar esa duplicación adicional.
5. Vigencias disponibles: el año solicitado ya no desaparece del selector. La lista completa debe suministrarse desde los parámetros del servidor.
6. La verificación visual cubre el inventario accesible y estados ficticios descritos, no certifica todas las combinaciones de datos reales o navegadores. La revisión inicial permanece como evidencia histórica, no como lista de errores todavía presentes.

## Sincronización antes de publicar

Se consultó origin/main y se integraron mediante pull --rebase los cinco commits hasta c9823c1. El único conflicto fue resources/js/api/index.js: se conservaron tanto origen_monto del remoto como es_rentista/gastos de la simulación. La nueva interfaz de ingresos registrados de seguridad social se conserva. Se agregó comprobación de compatibilidad para INGRESOS/MANUAL. El diff propio contra esa base remota no modifica app/, routes/, database/, config/ ni Composer. Los cambios PHP propios corresponden a vistas/presentación y al renderizador de la guía. No se ejecutaron migraciones ni pruebas remotas que fuerzan el uso de la base PostgreSQL local.
