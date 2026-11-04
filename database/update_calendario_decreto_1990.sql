-- Actualización oficial de Calendario Tributario para Seguridad Social Independientes
-- Normativa: Decreto 1990 de 2016 (Artículo 3.2.2.1 del Decreto 780 de 2016)
-- Aplica para vigencias 2025, 2026 y 2027

-- 1. Eliminar registros de prueba previos para SEGURIDAD_SOCIAL_INDEP
DELETE FROM public.calendario_tributario 
WHERE tipo_obligacion = 'SEGURIDAD_SOCIAL_INDEP' 
  AND anio_vigencia IN (2025, 2026, 2027);

-- 2. Insertar los 15 rangos oficiales por vigencia
DO $$
DECLARE
    v_admin_id uuid;
    v_anio int;
BEGIN
    SELECT id INTO v_admin_id FROM public.usuarios WHERE rol = 'ADMIN' LIMIT 1;
    IF v_admin_id IS NULL THEN
        SELECT id INTO v_admin_id FROM public.usuarios LIMIT 1;
    END IF;

    FOREACH v_anio IN ARRAY ARRAY[2025, 2026, 2027]
    LOOP
        INSERT INTO public.calendario_tributario (id, anio_vigencia, tipo_obligacion, digito_desde, digito_hasta, fecha_vencimiento, creado_por) VALUES
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '00', '07', make_date(v_anio, 1, 2), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '08', '14', make_date(v_anio, 1, 3), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '15', '21', make_date(v_anio, 1, 4), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '22', '28', make_date(v_anio, 1, 5), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '29', '35', make_date(v_anio, 1, 6), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '36', '42', make_date(v_anio, 1, 7), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '43', '49', make_date(v_anio, 1, 8), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '50', '56', make_date(v_anio, 1, 9), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '57', '63', make_date(v_anio, 1, 10), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '64', '69', make_date(v_anio, 1, 11), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '70', '75', make_date(v_anio, 1, 12), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '76', '81', make_date(v_anio, 1, 13), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '82', '87', make_date(v_anio, 1, 14), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '88', '93', make_date(v_anio, 1, 15), v_admin_id),
        (gen_random_uuid(), v_anio, 'SEGURIDAD_SOCIAL_INDEP', '94', '99', make_date(v_anio, 1, 16), v_admin_id);
    END LOOP;
END $$;

