<!doctype html>
<html lang="es-CO">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><title>FinanzApp · Guía de estilo</title>
    <script type="module" src="/resources/js/dev-styleguide.js"></script>
</head>
<body class="scfp-theme" data-styleguide>
<x-ui.app-shell title="Guía de estilo · Desarrollo">
    <header class="sg-intro">
        <p class="sg-eyebrow">Sistema de diseño / Fase A</p>
        <h1>Claridad para lo importante.</h1>
        <p class="sg-lead">Una base tranquila, legible y precisa para el control financiero personal.</p>
        <p class="sg-notice">Solo desarrollo. Todos los datos son ficticios. Los ejemplos no se guardan ni consultan información real.</p>
        <nav class="sg-index" aria-label="Índice de la guía"><a href="#tokens">Tokens</a><a href="#acciones">Acciones</a><a href="#formulario">Formulario</a><a href="#tabla">Tabla</a><a href="#estados">Estados</a><a href="#interaccion">Interacción</a></nav>
    </header>

    <section class="sg-section" id="tokens" aria-labelledby="tokens-title">
        <div class="sg-section-heading"><p class="sg-eyebrow">01 / Fundamentos</p><h2 id="tokens-title">Menos ruido. Más lectura.</h2><p>El acento orienta; los números y las palabras llevan la información.</p></div>
        <div class="sg-color-grid" data-token-swatches></div>
        <div class="sg-type-grid">
            <div><p class="sg-eyebrow">Monto destacado · 32–48 px</p><p class="sg-amount" data-money="4450000">$ 4.450.000</p><p class="ui-help">Cifras tabulares · ejemplo visual</p></div>
            <div><p class="sg-eyebrow">Título · 30–40 px</p><p class="sg-type-title">Una jerarquía clara</p><p>Texto base de 16 px, interlineado generoso y líneas de longitud contenida.</p><p class="ui-help">Metadatos de 13 px. Etiquetas y controles de 14–16 px.</p></div>
        </div>
        <h3>Escala de espacio</h3><div class="sg-spacing" data-spacing-swatches></div>
        <p class="ui-help">Radios de 8 y 14 px. Las sombras se reservan para mensajes flotantes y diálogos.</p>
        <details class="sg-contrast"><summary>Contraste medido con los tokens actuales</summary><div data-contrast-report></div></details>
    </section>

    <section class="sg-section" id="acciones" aria-labelledby="actions-title">
        <div class="sg-section-heading"><p class="sg-eyebrow">02 / Acciones y señalización</p><h2 id="actions-title">Una acción principal por grupo.</h2><p>Normal, foco de teclado, deshabilitado y carga. Recorre los ejemplos con Tab.</p></div>
        <div class="sg-example-grid">
            <x-ui.card title="Jerarquía de botones"><div class="ui-inline"><x-ui.button>Guardar</x-ui.button><x-ui.button variant="secondary">Cancelar</x-ui.button><x-ui.button variant="ghost">Volver</x-ui.button><x-ui.button variant="danger">Eliminar</x-ui.button></div></x-ui.card>
            <x-ui.card title="Estados de los controles"><div class="ui-inline"><x-ui.button data-focus-preview>Foco visible</x-ui.button><x-ui.button disabled>Deshabilitado</x-ui.button><x-ui.button aria-busy="true" disabled>Guardando…</x-ui.button></div></x-ui.card>
        </div>
        <div class="sg-example-grid">
            <x-ui.card title="Estados con texto"><div class="ui-inline"><x-ui.badge>Inactiva</x-ui.badge><x-ui.badge tone="success">Activa</x-ui.badge><x-ui.badge tone="warning">Pendiente</x-ui.badge><x-ui.badge tone="danger">Error</x-ui.badge></div><p class="ui-help">El significado se mantiene al quitar el color.</p></x-ui.card>
            <x-ui.card title="Ayuda contextual"><div class="ui-inline"><span>Retención en la fuente</span><x-ui.help-tip label="Ayuda sobre retención en la fuente" text="Parte del ingreso que el pagador descuenta como anticipo de impuestos." /></div><p class="ui-help">Se abre con clic, toque, Enter o Espacio.</p></x-ui.card>
        </div>
    </section>

    <section class="sg-section" id="formulario" aria-labelledby="form-title">
        <div class="sg-section-heading"><p class="sg-eyebrow">03 / Formularios</p><h2 id="form-title">Cada campo tiene su espacio.</h2><p>Ejemplo de componentes y validaciones. No registra ingresos ni gastos.</p></div>
        <div class="sg-form-layout">
            <x-ui.card title="Formulario de muestra">
                <form id="sg-form" novalidate>
                    <x-ui.field name="sg-description" label="Descripción" help="Máximo 255 caracteres."><input id="sg-description" name="descripcion" type="text" maxlength="255" aria-describedby="sg-description-help sg-description-error"></x-ui.field>
                    <x-ui.money-input name="sg-amount" label="Monto" value="1250000" />
                    <div class="sg-two-fields"><x-ui.date-input name="sg-date" value="2026-03-15" /><x-ui.select name="sg-payment" label="Medio de pago" :required="true" :options="['' => 'Selecciona una opción', 'EFECTIVO' => 'Efectivo', 'TRANSFERENCIA' => 'Transferencia']" /></div>
                    <x-ui.tag-input name="sg-tags" :value="['ejemplo', 'desarrollo']" />
                    <div class="sg-form-footer"><p class="ui-help">Los campos con * son obligatorios.</p><x-ui.button type="submit">Validar ejemplo</x-ui.button></div>
                    <p id="sg-form-result" role="status" aria-live="polite" hidden></p>
                </form>
            </x-ui.card>
            <div class="sg-form-aside">
                <x-ui.card title="Estados del campo">
                <x-ui.field name="sg-error" label="Monto con error" :required="true" error="El monto debe ser mayor que $ 0 y no superar $ 999.999.999.999,99."><input id="sg-error" value="0" aria-invalid="true" aria-describedby="sg-error-error"></x-ui.field>
                <x-ui.field name="sg-disabled" label="Campo deshabilitado"><input id="sg-disabled" value="No disponible" disabled></x-ui.field>
                </x-ui.card>
            </div>
        </div>
        <details class="sg-details"><summary>Más estados de MoneyInput, DateInput, Select y TagInput</summary><div class="sg-example-grid">
            <x-ui.card title="MoneyInput deshabilitado"><x-ui.money-input name="sg-money-disabled" value="1234567.89" disabled /></x-ui.card>
            <x-ui.card title="DateInput deshabilitado"><x-ui.date-input name="sg-date-disabled" value="2026-03-15" disabled /></x-ui.card>
            <x-ui.card title="Select deshabilitado"><x-ui.select name="sg-select-disabled" label="Medio de pago" :options="['EFECTIVO' => 'Efectivo']" disabled /></x-ui.card>
            <x-ui.card title="TagInput deshabilitado"><x-ui.tag-input name="sg-tags-disabled" :value="['ejemplo']" disabled /></x-ui.card>
            <x-ui.card title="MoneyInput vacío"><x-ui.money-input name="sg-money-empty" /></x-ui.card>
            <x-ui.card title="DateInput y Select con error"><x-ui.date-input name="sg-invalid-date" /><x-ui.select name="sg-invalid-payment" label="Medio de pago" :required="true" :options="['' => 'Selecciona una opción']" /><x-ui.button variant="secondary" data-show-field-errors>Mostrar errores de ejemplo</x-ui.button></x-ui.card>
        </div></details>
    </section>

    <section class="sg-section" id="tabla" aria-labelledby="table-title">
        <div class="sg-section-heading"><p class="sg-eyebrow">04 / Datos</p><h2 id="table-title">Los importes, en primer plano.</h2><p>En móvil cada fila se transforma en una tarjeta con sus etiquetas.</p></div>
        @php
            $columns = ['date' => ['label' => 'Fecha', 'format' => 'date'], 'detail' => ['label' => 'Descripción'], 'cop' => ['label' => 'Monto COP', 'format' => 'money']];
            $rows = [['date' => '2026-03-15', 'detail' => 'Registro ficticio A', 'cop' => '5000000'], ['date' => '2026-03-14', 'detail' => 'Registro ficticio B', 'cop' => '1250000']];
        @endphp
        <x-ui.card>
        <x-ui.data-table :columns="$columns" :rows="$rows" caption="Datos ficticios · todos los montos están en pesos colombianos.">
            <dl class="sg-table-totals" aria-label="Totales de ejemplo"><div><dt>Total COP de ejemplo</dt><dd data-money="6250000">$ 6.250.000</dd></div></dl>
        </x-ui.data-table>
        <x-ui.pagination :paginator="new SgPaginator()" />
        </x-ui.card>
        <details class="sg-details"><summary>Tabla vacía, en carga y con error</summary><div class="sg-example-grid">
            <x-ui.card title="Vacío"><x-ui.data-table :columns="$columns" :rows="[]" caption="Ejemplo vacío" /></x-ui.card>
            <x-ui.card title="Carga"><x-ui.data-table :columns="$columns" :rows="[]" state="loading" caption="Ejemplo de carga" /></x-ui.card>
            <x-ui.card title="Error"><x-ui.data-table :columns="$columns" :rows="[]" state="error" caption="Ejemplo de error" /></x-ui.card>
            <x-ui.card title="Paginación sin resultados"><x-ui.pagination :paginator="new SgPaginator(1, 0)" /></x-ui.card>
        </div></details>
    </section>

    <section class="sg-section" id="estados" aria-labelledby="states-title">
        <div class="sg-section-heading"><p class="sg-eyebrow">05 / Estados</p><h2 id="states-title">Explicar qué está pasando.</h2><p>Sin depender solo del color y respetando la preferencia de movimiento reducido.</p></div>
        <div class="sg-example-grid">
            <x-ui.card title="Carga"><x-ui.skeleton :lines="4" /></x-ui.card>
            <x-ui.card title="Éxito"><x-ui.view-state message="Ejemplo validado correctamente."><x-ui.badge tone="success">Completado</x-ui.badge></x-ui.view-state></x-ui.card>
            <x-ui.card title="Vacío"><x-ui.empty-state title="No se encontraron resultados" description="Estado vacío de ejemplo." /></x-ui.card>
            <x-ui.card title="Error"><x-ui.view-state state="error"><x-ui.button variant="secondary" data-demo-retry>Reintentar ejemplo</x-ui.button></x-ui.view-state></x-ui.card>
        </div>
    </section>

    <section class="sg-section" id="interaccion" aria-labelledby="interaction-title">
        <div class="sg-section-heading"><p class="sg-eyebrow">06 / Interacción</p><h2 id="interaction-title">Confirmar, informar y continuar.</h2><p>El diálogo captura el foco; Escape cancela y devuelve el foco al botón de origen.</p></div>
        <div class="ui-inline"><form data-confirm-delete id="sg-delete"><x-ui.button type="submit" variant="danger">Abrir ConfirmDialog</x-ui.button></form><x-ui.button variant="secondary" data-demo-toast>Mostrar Toast</x-ui.button></div>
        <p id="sg-delete-result" class="ui-help" role="status" aria-live="polite"></p>
        <p class="ui-help">AppShell: la barra lateral permanece visible en escritorio; debajo de 768 px usa el botón de menú para abrir el drawer.</p>
    </section>
    <section class="sg-section" id="income-components" aria-labelledby="income-components-title">
        <div class="sg-section-heading"><p class="sg-eyebrow">07 / Componentes de ingresos</p><h2 id="income-components-title">Resumen, fuente y programación</h2><p>Datos ficticios. Estos formularios prueban los componentes reales y no realizan operaciones contra el backend.</p></div>
        @php
            $exampleSources = [(object) ['id' => '00000000-0000-4000-8000-000000000001', 'nombre' => 'Fuente de ejemplo', 'activa' => true, 'clasificacion_tributaria' => 'RENTAS_TRABAJO'], (object) ['id' => '00000000-0000-4000-8000-000000000005', 'nombre' => 'Fuente inactiva de ejemplo', 'activa' => false, 'clasificacion_tributaria' => 'RENTAS_CAPITAL']];
            $exampleTypes = [(object) ['id' => '00000000-0000-4000-8000-000000000002', 'nombre' => 'Salario', 'icono' => 'cat-otros', 'color_hex' => '#34D399'], (object) ['id' => '00000000-0000-4000-8000-000000000003', 'nombre' => 'Arrendamientos', 'icono' => 'cat-vivienda', 'color_hex' => '#38BDF8']];
            $exampleClassifications = ['RENTAS_TRABAJO', 'RENTAS_CAPITAL', 'RENTAS_NO_LABORALES', 'PENSIONES', 'DIVIDENDOS'];
            $exampleRecord = ['fuente_id' => $exampleSources[0]->id, 'monto_original' => '5000000', 'retencion_fuente' => '150000', 'aportes_descontados' => '400000'];
        @endphp
        <form id="sg-income-example" novalidate>
            <x-income.form :sources="$exampleSources" :classifications="$exampleClassifications" :record="$exampleRecord" prefix="sg-income">
                <x-ui.card><x-ui.recurrence-form :periodicities="['SEMANAL', 'QUINCENAL', 'MENSUAL', 'BIMESTRAL', 'TRIMESTRAL', 'SEMESTRAL', 'ANUAL']" :modes="['AUTOMATICO', 'REQUIERE_CONFIRMACION']" prefix="sg-recurring" /></x-ui.card>
            </x-income.form>
            <div class="income-actions"><x-ui.button type="submit">Validar componentes de ingresos</x-ui.button></div><p id="sg-income-result" role="status" aria-live="polite"></p>
        </form>
        <form id="sg-income-delete" data-confirm-delete data-confirm-date="2026-03-15" data-confirm-source="Fuente de ejemplo" data-confirm-amount="5000000"><x-ui.button type="submit" variant="secondary">Probar confirmación de ingreso</x-ui.button></form>
        @php
            $exampleIncomes = [(object) ['id' => '00000000-0000-4000-8000-000000000004', 'fecha_recepcion' => '2026-03-15', 'fuente' => $exampleSources[0], 'clasificacion_tributaria' => 'RENTAS_TRABAJO', 'monto_bruto_cop' => '5000000', 'retencion_fuente_cop' => '150000', 'aportes_descontados_cop' => '400000', 'monto_neto_cop' => '4450000', 'estado' => 'CONFIRMADO']];
            $exampleTotals = (object) ['bruto' => '5000000', 'retencion' => '150000', 'neto' => '4450000'];
        @endphp
        <p class="ui-help">Listado de ejemplo: importes ficticios y acciones sin persistencia.</p>
        <x-income.table :incomes="$exampleIncomes" :totals="$exampleTotals" :paginator="new SgPaginator(1, 1)" />
        <details class="sg-details"><summary>Formulario de fuente de ingreso</summary><x-ui.card>
            <form id="sg-source-example" novalidate><x-income.source-form :types="$exampleTypes" :classifications="$exampleClassifications" prefix="sg-source" /><x-ui.button type="submit">Validar fuente de ejemplo</x-ui.button><p id="sg-source-result" role="status" aria-live="polite"></p></form>
        </x-ui.card></details>
    </section>
</x-ui.app-shell>
</body>
</html>
