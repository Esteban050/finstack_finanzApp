<?php

return [

    /*
    | MOD-08 — Límites de los créditos.
    |
    | Sección 6 del documento de créditos: los valores numéricos son
    | parámetros, no código, porque cambian por norma o por política.
    */
    'creditos' => [
        'plazo_min_meses' => (int) env('SCFP_CREDITO_PLAZO_MIN', 1),
        'plazo_max_meses' => (int) env('SCFP_CREDITO_PLAZO_MAX', 360),

        // Créditos RN-02 (Ley 546 de 1999, mod. Ley 2079 de 2021): vivienda de 5 a 30 años.
        'vivienda_plazo_min_meses' => 60,
        'vivienda_plazo_max_meses' => 360,

        // Tope de cordura en % EA, no la usura: la usura por modalidad y
        // vigencia (Créditos RN-01) vive en la tabla tasas_usura y solo alerta.
        'tasa_ea_max' => (string) env('SCFP_CREDITO_TASA_EA_MAX', '200'),

        // Créditos RF-02: tope del seguro en % mensual sobre el saldo.
        'seguro_porc_mensual_max' => '2',

        // Créditos RN-03 / RN-07: capacidad de pago (alertas, no bloquean).
        'meses_promedio_ingresos' => 3,
        // RF-053: hasta cuántos meses completos atrás se buscan meses con ingresos.
        'meses_busqueda_ingresos' => 12,
        'vivienda_cuota_max_porc_ingresos' => '40',
        'libranza_descuentos_max_porc_ingresos' => '50',
        'vivienda_financiacion_max_porc' => '70',
        'vivienda_vis_financiacion_max_porc' => '80',
    ],

    /*
    | MOD-09 — Presupuestos (RF-055 / RF-056).
    */
    'presupuestos' => [
        // RN-055.3: el sugerido es el promedio redondeado al múltiplo superior.
        'redondeo_sugerido' => '10000',

        // Aviso cuando una línea queda más de este % por debajo de su promedio.
        'aviso_linea_bajo_promedio_porc' => '15',

        // RF-055: hasta cuántos meses adelante se puede configurar.
        'meses_futuros_max' => 12,

        // RF-055: líneas máximas por presupuesto.
        'lineas_max' => 60,
    ],

    /*
    | MOD-09 — Reglas de alerta (RF-057 / RF-059) y notificaciones (RF-058).
    |
    | Valores sugeridos de cada regla (pantalla 04 del mockup). La prioridad
    | es fija por tipo; en las reglas por umbral, un umbral ≥ 100 % es CRITICA.
    */
    'alertas' => [
        'reglas' => [
            // RN-057.1: hasta 3 umbrales entre 50 % y 150 %; por defecto 80 % y 100 %.
            'UMBRAL_LINEA' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['umbrales' => [80, 100]],
                'prioridad' => 'ADVERTENCIA',
                'prioridad_desde_100' => 'CRITICA',
            ],
            'PRESUPUESTO_TOTAL' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['umbrales' => [90, 100]],
                'prioridad' => 'ADVERTENCIA',
                'prioridad_desde_100' => 'CRITICA',
            ],
            // RN-057.3: desde la mitad del mes, proyección al cierre ≥ umbral.
            'PROYECCION_LINEA' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['umbral' => 110],
                'prioridad' => 'ADVERTENCIA',
            ],

            // Ingresos frente a gastos: gastos del mes > ingresos netos recibidos
            // + recurrentes de ingreso pendientes; al cierre, con valores reales.
            'INGRESOS_VS_GASTOS' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => [],
                'prioridad' => 'CRITICA',
            ],
            // Desde la mitad del mes, gasto total proyectado > % del ingreso promedio.
            'GASTO_PROYECTADO_ALTO' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['umbral' => 90],
                'prioridad' => 'ADVERTENCIA',
            ],
            // Tope fijo de gasto mensual, haya o no presupuesto. Lo elige el Usuario.
            'LIMITE_GASTO_MENSUAL' => [
                'activa' => false,
                'enviar_correo' => false,
                'parametros' => ['monto' => null],
                'prioridad' => 'CRITICA',
            ],

            // Hábitos de gasto.
            'GASTO_INUSUAL' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['veces' => 3],
                'prioridad' => 'INFORMATIVA',
            ],
            'GASTOS_HORMIGA' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['monto_max' => 20000, 'tope' => 300000],
                'prioridad' => 'INFORMATIVA',
            ],
            // Referencia 50/30/20: los gustos no deberían pasar del 30 % del ingreso.
            'GASTO_DISCRECIONAL_ALTO' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['umbral' => 30],
                'prioridad' => 'INFORMATIVA',
            ],

            // Topes de la DIAN para declarar renta (1.400 UVT del año, RN-023.2).
            'TOPE_DIAN_TARJETA' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['umbral' => 80],
                'prioridad' => 'ADVERTENCIA',
            ],
            'TOPE_DIAN_COMPRAS' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['umbral' => 80],
                'prioridad' => 'ADVERTENCIA',
            ],

            // H06: el saldo de capital de una tarjeta llega a este % de su cupo (no es el tope de la DIAN).
            'USO_CUPO_TARJETA' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['umbral' => 80],
                'prioridad' => 'ADVERTENCIA',
            ],

            // Obligaciones: el indicador de RF-053 sube de franja frente al mes anterior.
            'ENDEUDAMIENTO' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['umbrales' => [30, 40]],
                'prioridad' => 'ADVERTENCIA',
            ],
            // RN-059.2: cuotas por vencer o vencidas y recurrentes por confirmar.
            'VENCIMIENTOS' => [
                'activa' => true,
                'enviar_correo' => false,
                'parametros' => ['dias_antes' => 3],
                'prioridad' => 'CRITICA',
            ],

            // RN-059.4 / CA-059.2: siempre activa y por correo; no se modifica.
            'SEGURIDAD_CUENTA' => [
                'activa' => true,
                'enviar_correo' => true,
                'parametros' => [],
                'prioridad' => 'CRITICA',
                'editable' => false,
            ],
        ],

        // Gasto inusual: meses de historia y gastos previos mínimos de la subcategoría.
        'gasto_inusual_meses' => 6,
        'gasto_inusual_minimo_gastos' => 5,

        // RN-057.1: rango de los umbrales por % de ejecución.
        'umbral_min' => 50,
        'umbral_max' => 150,
        'umbrales_max' => 3,

        // RN-057.3: rango del umbral de proyección.
        'proyeccion_min' => 100,
        'proyeccion_max' => 200,

        // RN-053.2: cortes del indicador de endeudamiento que el usuario puede fijar.
        'endeudamiento_umbral_min' => 10,
        'endeudamiento_umbral_max' => 80,

        // Las notificaciones archivadas se borran a los 180 días.
        'dias_archivadas' => 180,
    ],

];
