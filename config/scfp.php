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
        'vivienda_cuota_max_porc_ingresos' => '40',
        'libranza_descuentos_max_porc_ingresos' => '50',
        'vivienda_financiacion_max_porc' => '70',
        'vivienda_vis_financiacion_max_porc' => '80',
    ],

];
