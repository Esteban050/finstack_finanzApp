--
-- PostgreSQL database dump
--

-- Dumped from database version 17.4
-- Dumped by pg_dump version 17.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pgcrypto; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pgcrypto WITH SCHEMA public;


--
-- Name: EXTENSION pgcrypto; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pgcrypto IS 'cryptographic functions';


--
-- Name: fn_actualizar_timestamp(); Type: FUNCTION; Schema: public; Owner: -
--

CREATE FUNCTION public.fn_actualizar_timestamp() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.actualizado_en = NOW();
    RETURN NEW;
END;
$$;


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: aceptaciones_politica; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.aceptaciones_politica (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid NOT NULL,
    version_politica character varying(20) NOT NULL,
    fecha_aceptacion timestamp with time zone DEFAULT now() NOT NULL,
    ip_origen inet NOT NULL,
    medio character varying(50) NOT NULL,
    revocada_en timestamp with time zone
);


--
-- Name: bitacora_auditoria; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.bitacora_auditoria (
    id bigint NOT NULL,
    usuario_id uuid,
    accion character varying(60) NOT NULL,
    entidad_afectada character varying(60) NOT NULL,
    entidad_id uuid,
    valor_anterior jsonb,
    valor_nuevo jsonb,
    justificacion text,
    ip_origen inet,
    fecha_evento timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: bitacora_auditoria_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.bitacora_auditoria_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: bitacora_auditoria_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.bitacora_auditoria_id_seq OWNED BY public.bitacora_auditoria.id;


--
-- Name: calendario_tributario; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.calendario_tributario (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    anio_vigencia smallint NOT NULL,
    tipo_obligacion character varying(30) NOT NULL,
    digito_desde character(2) NOT NULL,
    digito_hasta character(2) NOT NULL,
    fecha_vencimiento date NOT NULL,
    creado_por uuid NOT NULL,
    CONSTRAINT calendario_tributario_anio_vigencia_check CHECK (((anio_vigencia >= 2000) AND (anio_vigencia <= 2100))),
    CONSTRAINT calendario_tributario_check CHECK (((digito_hasta ~ '^[0-9]{2}$'::text) AND (digito_hasta >= digito_desde))),
    CONSTRAINT calendario_tributario_digito_desde_check CHECK ((digito_desde ~ '^[0-9]{2}$'::text)),
    CONSTRAINT calendario_tributario_tipo_obligacion_check CHECK (((tipo_obligacion)::text = ANY ((ARRAY['RENTA_PN'::character varying, 'SEGURIDAD_SOCIAL_INDEP'::character varying])::text[])))
);


--
-- Name: categorias; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.categorias (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid,
    categoria_padre_id uuid,
    tipo character varying(15) NOT NULL,
    nombre character varying(40) NOT NULL,
    naturaleza_sugerida character varying(15),
    necesidad_sugerida character varying(15),
    icono character varying(40),
    color_hex character(7),
    es_predeterminada boolean DEFAULT false NOT NULL,
    activa boolean DEFAULT true NOT NULL,
    creado_en timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT categorias_color_hex_check CHECK (((color_hex IS NULL) OR (color_hex ~ '^#[0-9A-Fa-f]{6}$'::text))),
    CONSTRAINT categorias_naturaleza_sugerida_check CHECK (((naturaleza_sugerida IS NULL) OR ((naturaleza_sugerida)::text = ANY ((ARRAY['FIJO'::character varying, 'VARIABLE'::character varying])::text[])))),
    CONSTRAINT categorias_necesidad_sugerida_check CHECK (((necesidad_sugerida IS NULL) OR ((necesidad_sugerida)::text = ANY ((ARRAY['ESENCIAL'::character varying, 'DISCRECIONAL'::character varying])::text[])))),
    CONSTRAINT categorias_nombre_check CHECK (((char_length(TRIM(BOTH FROM nombre)) >= 2) AND (char_length(TRIM(BOTH FROM nombre)) <= 40))),
    CONSTRAINT categorias_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['INGRESO'::character varying, 'GASTO'::character varying])::text[]))),
    CONSTRAINT chk_categoria_propietario CHECK ((((es_predeterminada = true) AND (usuario_id IS NULL)) OR ((es_predeterminada = false) AND (usuario_id IS NOT NULL))))
);


--
-- Name: fuentes_ingreso; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.fuentes_ingreso (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid NOT NULL,
    tipo_fuente_id uuid NOT NULL,
    nombre character varying(60) NOT NULL,
    clasificacion_tributaria character varying(30) NOT NULL,
    pagador_entidad character varying(80),
    activa boolean DEFAULT true NOT NULL,
    creado_en timestamp with time zone DEFAULT now() NOT NULL,
    actualizado_en timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT fuentes_ingreso_clasificacion_tributaria_check CHECK (((clasificacion_tributaria)::text = ANY ((ARRAY['RENTAS_TRABAJO'::character varying, 'RENTAS_CAPITAL'::character varying, 'RENTAS_NO_LABORALES'::character varying, 'PENSIONES'::character varying, 'DIVIDENDOS'::character varying])::text[]))),
    CONSTRAINT fuentes_ingreso_nombre_check CHECK (((char_length(TRIM(BOTH FROM nombre)) >= 3) AND (char_length(TRIM(BOTH FROM nombre)) <= 60)))
);


--
-- Name: gastos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.gastos (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid NOT NULL,
    categoria_id uuid NOT NULL,
    subcategoria_id uuid NOT NULL,
    plantilla_recurrencia_id uuid,
    fecha date NOT NULL,
    moneda character(3) DEFAULT 'COP'::bpchar NOT NULL,
    monto_original numeric(18,2) NOT NULL,
    tasa_cambio_usada numeric(18,4) DEFAULT 1.0000 NOT NULL,
    monto_cop numeric(18,2) NOT NULL,
    naturaleza character varying(15) NOT NULL,
    necesidad character varying(15) NOT NULL,
    medio_pago character varying(25) NOT NULL,
    relevancia_tributaria character varying(25) DEFAULT 'NO_DEDUCIBLE'::character varying NOT NULL,
    subtipo_deduccion character varying(30),
    etiquetas character varying(30)[] DEFAULT '{}'::character varying[] NOT NULL,
    comercio_beneficiario character varying(80),
    origen character varying(20) DEFAULT 'MANUAL'::character varying NOT NULL,
    estado character varying(20) DEFAULT 'CONFIRMADO'::character varying NOT NULL,
    descripcion character varying(255),
    creado_en timestamp with time zone DEFAULT now() NOT NULL,
    actualizado_en timestamp with time zone DEFAULT now() NOT NULL,
    eliminado_en timestamp with time zone,
    CONSTRAINT chk_gasto_deduccion_coherencia CHECK (((((relevancia_tributaria)::text = 'NO_DEDUCIBLE'::text) AND (subtipo_deduccion IS NULL)) OR (((relevancia_tributaria)::text = 'POTENCIALMENTE_DEDUCIBLE'::text) AND (subtipo_deduccion IS NOT NULL)))),
    CONSTRAINT gastos_estado_check CHECK (((estado)::text = ANY ((ARRAY['CONFIRMADO'::character varying, 'PENDIENTE'::character varying])::text[]))),
    CONSTRAINT gastos_etiquetas_check CHECK ((cardinality(etiquetas) <= 10)),
    CONSTRAINT gastos_medio_pago_check CHECK (((medio_pago)::text = ANY ((ARRAY['EFECTIVO'::character varying, 'TARJETA_DEBITO'::character varying, 'TARJETA_CREDITO'::character varying, 'TRANSFERENCIA'::character varying, 'BILLETERA_DIGITAL'::character varying, 'OTRO'::character varying])::text[]))),
    CONSTRAINT gastos_monto_cop_check CHECK (((monto_cop > (0)::numeric) AND (monto_cop <= 999999999999.99))),
    CONSTRAINT gastos_monto_original_check CHECK (((monto_original > (0)::numeric) AND (monto_original <= 999999999999.99))),
    CONSTRAINT gastos_naturaleza_check CHECK (((naturaleza)::text = ANY ((ARRAY['FIJO'::character varying, 'VARIABLE'::character varying])::text[]))),
    CONSTRAINT gastos_necesidad_check CHECK (((necesidad)::text = ANY ((ARRAY['ESENCIAL'::character varying, 'DISCRECIONAL'::character varying])::text[]))),
    CONSTRAINT gastos_origen_check CHECK (((origen)::text = ANY ((ARRAY['MANUAL'::character varying, 'RECURRENTE'::character varying, 'IMPORTADO'::character varying])::text[]))),
    CONSTRAINT gastos_relevancia_tributaria_check CHECK (((relevancia_tributaria)::text = ANY ((ARRAY['NO_DEDUCIBLE'::character varying, 'POTENCIALMENTE_DEDUCIBLE'::character varying])::text[]))),
    CONSTRAINT gastos_subtipo_deduccion_check CHECK (((subtipo_deduccion IS NULL) OR ((subtipo_deduccion)::text = ANY ((ARRAY['INTERESES_VIVIENDA'::character varying, 'MEDICINA_PREPAGADA'::character varying, 'DEPENDIENTES'::character varying, 'AFC_VOLUNTARIOS'::character varying, 'GMF'::character varying, 'OTROS'::character varying])::text[])))),
    CONSTRAINT gastos_tasa_cambio_usada_check CHECK ((tasa_cambio_usada > (0)::numeric))
);


--
-- Name: historial_contrasenas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.historial_contrasenas (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid NOT NULL,
    password_hash text NOT NULL,
    creado_en timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: ingresos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.ingresos (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid NOT NULL,
    fuente_id uuid NOT NULL,
    plantilla_recurrencia_id uuid,
    fecha_recepcion date NOT NULL,
    clasificacion_tributaria character varying(30) NOT NULL,
    moneda character(3) DEFAULT 'COP'::bpchar NOT NULL,
    monto_original numeric(18,2) NOT NULL,
    tasa_cambio_usada numeric(18,4) DEFAULT 1.0000 NOT NULL,
    monto_bruto_cop numeric(18,2) NOT NULL,
    retencion_fuente_cop numeric(18,2) DEFAULT 0.00 NOT NULL,
    aportes_descontados_cop numeric(18,2) DEFAULT 0.00 NOT NULL,
    monto_neto_cop numeric(18,2) GENERATED ALWAYS AS (((monto_bruto_cop - retencion_fuente_cop) - aportes_descontados_cop)) STORED,
    origen character varying(20) DEFAULT 'MANUAL'::character varying NOT NULL,
    estado character varying(20) DEFAULT 'CONFIRMADO'::character varying NOT NULL,
    descripcion character varying(255),
    creado_en timestamp with time zone DEFAULT now() NOT NULL,
    actualizado_en timestamp with time zone DEFAULT now() NOT NULL,
    eliminado_en timestamp with time zone,
    CONSTRAINT chk_ingreso_neto_positivo CHECK ((((monto_bruto_cop - retencion_fuente_cop) - aportes_descontados_cop) >= (0)::numeric)),
    CONSTRAINT chk_ingreso_retencion_bruto CHECK ((retencion_fuente_cop <= monto_bruto_cop)),
    CONSTRAINT ingresos_aportes_descontados_cop_check CHECK ((aportes_descontados_cop >= (0)::numeric)),
    CONSTRAINT ingresos_clasificacion_tributaria_check CHECK (((clasificacion_tributaria)::text = ANY ((ARRAY['RENTAS_TRABAJO'::character varying, 'RENTAS_CAPITAL'::character varying, 'RENTAS_NO_LABORALES'::character varying, 'PENSIONES'::character varying, 'DIVIDENDOS'::character varying])::text[]))),
    CONSTRAINT ingresos_estado_check CHECK (((estado)::text = ANY ((ARRAY['CONFIRMADO'::character varying, 'PENDIENTE'::character varying])::text[]))),
    CONSTRAINT ingresos_monto_bruto_cop_check CHECK (((monto_bruto_cop > (0)::numeric) AND (monto_bruto_cop <= 999999999999.99))),
    CONSTRAINT ingresos_monto_original_check CHECK (((monto_original > (0)::numeric) AND (monto_original <= 999999999999.99))),
    CONSTRAINT ingresos_origen_check CHECK (((origen)::text = ANY ((ARRAY['MANUAL'::character varying, 'RECURRENTE'::character varying, 'IMPORTADO'::character varying])::text[]))),
    CONSTRAINT ingresos_retencion_fuente_cop_check CHECK ((retencion_fuente_cop >= (0)::numeric)),
    CONSTRAINT ingresos_tasa_cambio_usada_check CHECK ((tasa_cambio_usada > (0)::numeric))
);


--
-- Name: liquidaciones_seguridad_social; Type: TABLE; Schema: public; Owner: -
-- RF-033 RN-033.7. Creada por la migración 2026_10_28_000001_create_seguridad_social_tables.
--

CREATE TABLE public.liquidaciones_seguridad_social (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid NOT NULL,
    periodo character(7) NOT NULL,
    origen_ingreso character varying(15) NOT NULL,
    ingreso_base numeric(18,2) NOT NULL,
    ibc numeric(18,2) NOT NULL,
    salud numeric(18,2) NOT NULL,
    pension numeric(18,2) NOT NULL,
    fsp numeric(18,2) NOT NULL,
    arl numeric(18,2) NOT NULL,
    total numeric(18,2) NOT NULL,
    parametro_normativo_id uuid NOT NULL,
    parametros_usados jsonb NOT NULL,
    creado_en timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    actualizado_en timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT chk_liquidacion_periodo CHECK ((periodo ~ '^[0-9]{4}-(0[1-9]|1[0-2])$'::text)),
    CONSTRAINT chk_liquidacion_origen CHECK (((origen_ingreso)::text = ANY ((ARRAY['REGISTRADO'::character varying, 'MANUAL'::character varying])::text[]))),
    CONSTRAINT chk_liquidacion_montos CHECK (((ingreso_base >= (0)::numeric) AND (ibc > (0)::numeric) AND (salud >= (0)::numeric) AND (pension >= (0)::numeric) AND (fsp >= (0)::numeric) AND (arl >= (0)::numeric) AND (total >= (0)::numeric)))
);


--
-- Name: pagos_seguridad_social; Type: TABLE; Schema: public; Owner: -
-- RF-035. Creada por la migración 2026_10_28_000001_create_seguridad_social_tables.
--

CREATE TABLE public.pagos_seguridad_social (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid NOT NULL,
    periodo character(7) NOT NULL,
    fecha_pago date NOT NULL,
    numero_planilla character varying(20),
    es_correccion boolean DEFAULT false NOT NULL,
    salud numeric(18,2) NOT NULL,
    pension numeric(18,2) NOT NULL,
    fsp numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    arl numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    intereses_mora numeric(18,2) DEFAULT '0'::numeric NOT NULL,
    gasto_id uuid,
    creado_en timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    actualizado_en timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    eliminado_en timestamp with time zone,
    total numeric(18,2) GENERATED ALWAYS AS (((((salud + pension) + fsp) + arl) + intereses_mora)) STORED,
    CONSTRAINT chk_pago_periodo CHECK ((periodo ~ '^[0-9]{4}-(0[1-9]|1[0-2])$'::text)),
    CONSTRAINT chk_pago_montos CHECK (((salud >= (0)::numeric) AND (pension >= (0)::numeric) AND (fsp >= (0)::numeric) AND (arl >= (0)::numeric) AND (intereses_mora >= (0)::numeric))),
    CONSTRAINT chk_pago_total CHECK ((total > (0)::numeric))
);


--
-- Name: parametros_normativos; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.parametros_normativos (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    anio_vigencia smallint NOT NULL,
    version smallint DEFAULT 1 NOT NULL,
    es_activa boolean DEFAULT true NOT NULL,
    acto_administrativo character varying(120) NOT NULL,
    fecha_acto date NOT NULL,
    valor_uvt numeric(18,2) NOT NULL,
    valor_smmlv numeric(18,2) NOT NULL,
    tarifa_salud_indep numeric(5,2) NOT NULL,
    tarifa_pension_indep numeric(5,2) NOT NULL,
    tarifa_salud_emp numeric(5,2) NOT NULL,
    tarifa_pension_emp numeric(5,2) NOT NULL,
    porc_ibc_indep numeric(5,2) NOT NULL,
    porc_ibc_integral numeric(5,2) NOT NULL,
    ibc_min_smmlv numeric(5,2) NOT NULL,
    ibc_max_smmlv numeric(5,2) NOT NULL,
    tope_patrimonio_uvt integer NOT NULL,
    tope_ingresos_uvt integer NOT NULL,
    tope_tc_uvt integer NOT NULL,
    tope_compras_uvt integer NOT NULL,
    tope_consignaciones_uvt integer NOT NULL,
    limite_renta_exenta_25_uvt integer NOT NULL,
    porc_limite_global_exentas numeric(5,2) NOT NULL,
    limite_global_exentas_uvt integer NOT NULL,
    tarifa_gmf numeric(6,4) NOT NULL,
    exencion_gmf_uvt integer NOT NULL,
    ipc_anual numeric(5,2) NOT NULL,
    justificacion_cambio text,
    creado_por uuid NOT NULL,
    creado_en timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT parametros_normativos_anio_vigencia_check CHECK (((anio_vigencia >= 2000) AND (anio_vigencia <= 2100))),
    CONSTRAINT parametros_normativos_check CHECK ((ibc_max_smmlv >= ibc_min_smmlv)),
    CONSTRAINT parametros_normativos_exencion_gmf_uvt_check CHECK ((exencion_gmf_uvt >= 0)),
    CONSTRAINT parametros_normativos_ibc_min_smmlv_check CHECK ((ibc_min_smmlv > (0)::numeric)),
    CONSTRAINT parametros_normativos_ipc_anual_check CHECK (((ipc_anual >= (0)::numeric) AND (ipc_anual <= (100)::numeric))),
    CONSTRAINT parametros_normativos_justificacion_cambio_check CHECK (((justificacion_cambio IS NULL) OR (char_length(TRIM(BOTH FROM justificacion_cambio)) >= 20))),
    CONSTRAINT parametros_normativos_limite_global_exentas_uvt_check CHECK ((limite_global_exentas_uvt > 0)),
    CONSTRAINT parametros_normativos_limite_renta_exenta_25_uvt_check CHECK ((limite_renta_exenta_25_uvt > 0)),
    CONSTRAINT parametros_normativos_porc_ibc_indep_check CHECK (((porc_ibc_indep >= (0)::numeric) AND (porc_ibc_indep <= (100)::numeric))),
    CONSTRAINT parametros_normativos_porc_ibc_integral_check CHECK (((porc_ibc_integral >= (0)::numeric) AND (porc_ibc_integral <= (100)::numeric))),
    CONSTRAINT parametros_normativos_porc_limite_global_exentas_check CHECK (((porc_limite_global_exentas >= (0)::numeric) AND (porc_limite_global_exentas <= (100)::numeric))),
    CONSTRAINT parametros_normativos_tarifa_gmf_check CHECK (((tarifa_gmf >= (0)::numeric) AND (tarifa_gmf <= (100)::numeric))),
    CONSTRAINT parametros_normativos_tarifa_pension_emp_check CHECK (((tarifa_pension_emp >= (0)::numeric) AND (tarifa_pension_emp <= (100)::numeric))),
    CONSTRAINT parametros_normativos_tarifa_pension_indep_check CHECK (((tarifa_pension_indep >= (0)::numeric) AND (tarifa_pension_indep <= (100)::numeric))),
    CONSTRAINT parametros_normativos_tarifa_salud_emp_check CHECK (((tarifa_salud_emp >= (0)::numeric) AND (tarifa_salud_emp <= (100)::numeric))),
    CONSTRAINT parametros_normativos_tarifa_salud_indep_check CHECK (((tarifa_salud_indep >= (0)::numeric) AND (tarifa_salud_indep <= (100)::numeric))),
    CONSTRAINT parametros_normativos_tope_compras_uvt_check CHECK ((tope_compras_uvt > 0)),
    CONSTRAINT parametros_normativos_tope_consignaciones_uvt_check CHECK ((tope_consignaciones_uvt > 0)),
    CONSTRAINT parametros_normativos_tope_ingresos_uvt_check CHECK ((tope_ingresos_uvt > 0)),
    CONSTRAINT parametros_normativos_tope_patrimonio_uvt_check CHECK ((tope_patrimonio_uvt > 0)),
    CONSTRAINT parametros_normativos_tope_tc_uvt_check CHECK ((tope_tc_uvt > 0)),
    CONSTRAINT parametros_normativos_valor_smmlv_check CHECK ((valor_smmlv > (0)::numeric)),
    CONSTRAINT parametros_normativos_valor_uvt_check CHECK ((valor_uvt > (0)::numeric)),
    CONSTRAINT parametros_normativos_version_check CHECK ((version >= 1))
);


--
-- Name: perfiles_tributarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.perfiles_tributarios (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid NOT NULL,
    fecha_nacimiento date NOT NULL,
    ciudad_residencia character varying(80) NOT NULL,
    telefono character varying(10),
    ultimos_digitos_nit character(2),
    personas_a_cargo smallint DEFAULT 0 NOT NULL,
    ocupaciones text[] NOT NULL,
    es_salario_integral boolean DEFAULT false NOT NULL,
    clase_riesgo_arl smallint,
    arl_obligatoria boolean,
    responsable_iva boolean DEFAULT false NOT NULL,
    vigente_desde date DEFAULT CURRENT_DATE NOT NULL,
    vigente_hasta date,
    creado_en timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT chk_fechas_vigencia_perfil CHECK (((vigente_hasta IS NULL) OR (vigente_hasta >= vigente_desde))),
    CONSTRAINT perfiles_tributarios_clase_riesgo_arl_check CHECK (((clase_riesgo_arl IS NULL) OR ((clase_riesgo_arl >= 1) AND (clase_riesgo_arl <= 5)))),
    CONSTRAINT perfiles_tributarios_ocupaciones_check CHECK ((cardinality(ocupaciones) >= 1)),
    CONSTRAINT perfiles_tributarios_personas_a_cargo_check CHECK (((personas_a_cargo >= 0) AND (personas_a_cargo <= 10))),
    CONSTRAINT perfiles_tributarios_telefono_check CHECK (((telefono IS NULL) OR (char_length((telefono)::text) = 10))),
    CONSTRAINT perfiles_tributarios_ultimos_digitos_nit_check CHECK (((ultimos_digitos_nit IS NULL) OR (ultimos_digitos_nit ~ '^[0-9]{2}$'::text)))
);


--
-- Name: plantillas_recurrencia; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plantillas_recurrencia (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid NOT NULL,
    tipo_movimiento character varying(10) NOT NULL,
    periodicidad character varying(15) NOT NULL,
    fecha_inicio date NOT NULL,
    fecha_fin date,
    max_ocurrencias smallint,
    ocurrencias_generadas smallint DEFAULT 0 NOT NULL,
    modo_confirmacion character varying(25) NOT NULL,
    es_monto_estimado boolean DEFAULT false NOT NULL,
    datos_plantilla jsonb NOT NULL,
    proxima_ejecucion date NOT NULL,
    activa boolean DEFAULT true NOT NULL,
    creado_en timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT chk_fechas_recurrencia CHECK (((fecha_fin IS NULL) OR (fecha_fin >= fecha_inicio))),
    CONSTRAINT plantillas_recurrencia_max_ocurrencias_check CHECK (((max_ocurrencias IS NULL) OR ((max_ocurrencias >= 1) AND (max_ocurrencias <= 360)))),
    CONSTRAINT plantillas_recurrencia_modo_confirmacion_check CHECK (((modo_confirmacion)::text = ANY ((ARRAY['AUTOMATICO'::character varying, 'REQUIERE_CONFIRMACION'::character varying])::text[]))),
    CONSTRAINT plantillas_recurrencia_ocurrencias_generadas_check CHECK ((ocurrencias_generadas >= 0)),
    CONSTRAINT plantillas_recurrencia_periodicidad_check CHECK (((periodicidad)::text = ANY ((ARRAY['SEMANAL'::character varying, 'QUINCENAL'::character varying, 'MENSUAL'::character varying, 'BIMESTRAL'::character varying, 'TRIMESTRAL'::character varying, 'SEMESTRAL'::character varying, 'ANUAL'::character varying])::text[]))),
    CONSTRAINT plantillas_recurrencia_tipo_movimiento_check CHECK (((tipo_movimiento)::text = ANY ((ARRAY['INGRESO'::character varying, 'GASTO'::character varying])::text[])))
);


--
-- Name: sesiones_usuario; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sesiones_usuario (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid NOT NULL,
    token_jti character varying(128) NOT NULL,
    ip_origen inet NOT NULL,
    user_agent text,
    recordar_dispositivo_until timestamp with time zone,
    ultima_actividad timestamp with time zone DEFAULT now() NOT NULL,
    expira_en timestamp with time zone NOT NULL,
    revocada boolean DEFAULT false NOT NULL,
    creado_en timestamp with time zone DEFAULT now() NOT NULL
);


--
-- Name: tarifas_escalonadas; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tarifas_escalonadas (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    parametro_normativo_id uuid NOT NULL,
    tipo_tabla character varying(20) NOT NULL,
    rango_desde numeric(12,2) NOT NULL,
    rango_hasta numeric(12,2),
    tarifa_porc numeric(6,3) NOT NULL,
    impuesto_base_uvt numeric(12,2) DEFAULT 0 NOT NULL,
    CONSTRAINT chk_rango_escalonado CHECK (((rango_hasta IS NULL) OR (rango_hasta >= rango_desde))),
    CONSTRAINT tarifas_escalonadas_impuesto_base_uvt_check CHECK ((impuesto_base_uvt >= (0)::numeric)),
    CONSTRAINT tarifas_escalonadas_rango_desde_check CHECK ((rango_desde >= (0)::numeric)),
    CONSTRAINT tarifas_escalonadas_tarifa_porc_check CHECK (((tarifa_porc >= (0)::numeric) AND (tarifa_porc <= (100)::numeric))),
    CONSTRAINT tarifas_escalonadas_tipo_tabla_check CHECK (((tipo_tabla)::text = ANY ((ARRAY['RENTA_ART241'::character varying, 'FSP'::character varying, 'ARL'::character varying])::text[])))
);


--
-- Name: tasas_cambio; Type: TABLE; Schema: public; Owner: -
-- RF-017 (TRM). Creada por la migración 2026_10_27_000001_create_tasas_cambio_table.
--

CREATE TABLE public.tasas_cambio (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    moneda character(3) DEFAULT 'USD'::bpchar NOT NULL,
    valor numeric(18,4) NOT NULL,
    vigencia_desde date NOT NULL,
    vigencia_hasta date NOT NULL,
    fuente character varying(20) NOT NULL,
    justificacion text,
    registrado_por uuid,
    consultado_en timestamp with time zone NOT NULL,
    creado_en timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    actualizado_en timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT tasas_cambio_moneda_check CHECK (((moneda ~ '^[A-Z]{3}$'::text) AND (moneda <> 'COP'::bpchar))),
    CONSTRAINT tasas_cambio_valor_check CHECK ((valor > (0)::numeric)),
    CONSTRAINT chk_tasas_cambio_vigencia CHECK ((vigencia_hasta >= vigencia_desde)),
    CONSTRAINT tasas_cambio_fuente_check CHECK (((fuente)::text = ANY ((ARRAY['DATOS_ABIERTOS'::character varying, 'MANUAL'::character varying])::text[]))),
    CONSTRAINT chk_tasas_cambio_justificacion CHECK ((((fuente)::text <> 'MANUAL'::text) OR (char_length(TRIM(BOTH FROM justificacion)) >= 20)))
);


--
-- Name: tokens_seguridad; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.tokens_seguridad (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    usuario_id uuid NOT NULL,
    tipo character varying(30) NOT NULL,
    token_hash text NOT NULL,
    intentos smallint DEFAULT 0 NOT NULL,
    reenvios_contador smallint DEFAULT 0 NOT NULL,
    expira_en timestamp with time zone NOT NULL,
    usado_en timestamp with time zone,
    ip_origen inet,
    creado_en timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT tokens_seguridad_intentos_check CHECK (((intentos >= 0) AND (intentos <= 5))),
    CONSTRAINT tokens_seguridad_reenvios_contador_check CHECK (((reenvios_contador >= 0) AND (reenvios_contador <= 5))),
    CONSTRAINT tokens_seguridad_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['VERIFICACION_CORREO'::character varying, 'RECUPERACION_PASS'::character varying, 'MFA_CODIGO'::character varying, 'CANCELAR_SUPRESION'::character varying])::text[])))
);


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.usuarios (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    nombres character varying(60) NOT NULL,
    apellidos character varying(60) NOT NULL,
    tipo_documento character varying(5) NOT NULL,
    numero_documento character varying(15) NOT NULL,
    correo character varying(254) NOT NULL,
    password_hash text NOT NULL,
    rol character varying(20) DEFAULT 'USUARIO'::character varying NOT NULL,
    estado character varying(30) DEFAULT 'PENDIENTE_VERIFICACION'::character varying NOT NULL,
    intentos_fallidos smallint DEFAULT 0 NOT NULL,
    bloqueado_hasta timestamp with time zone,
    mfa_activo boolean DEFAULT false NOT NULL,
    fecha_solicitud_supresion timestamp with time zone,
    ultima_exportacion_en timestamp with time zone,
    ultimo_acceso timestamp with time zone,
    creado_en timestamp with time zone DEFAULT now() NOT NULL,
    actualizado_en timestamp with time zone DEFAULT now() NOT NULL,
    CONSTRAINT usuarios_apellidos_check CHECK (((char_length(TRIM(BOTH FROM apellidos)) >= 2) AND (char_length(TRIM(BOTH FROM apellidos)) <= 60))),
    CONSTRAINT usuarios_estado_check CHECK (((estado)::text = ANY ((ARRAY['PENDIENTE_VERIFICACION'::character varying, 'ACTIVA'::character varying, 'BLOQUEADA'::character varying, 'INACTIVA'::character varying, 'PENDIENTE_SUPRESION'::character varying])::text[]))),
    CONSTRAINT usuarios_intentos_fallidos_check CHECK ((intentos_fallidos >= 0)),
    CONSTRAINT usuarios_nombres_check CHECK (((char_length(TRIM(BOTH FROM nombres)) >= 2) AND (char_length(TRIM(BOTH FROM nombres)) <= 60))),
    CONSTRAINT usuarios_numero_documento_check CHECK (((char_length(TRIM(BOTH FROM numero_documento)) >= 5) AND (char_length(TRIM(BOTH FROM numero_documento)) <= 15))),
    CONSTRAINT usuarios_rol_check CHECK (((rol)::text = ANY ((ARRAY['USUARIO'::character varying, 'ADMIN'::character varying])::text[]))),
    CONSTRAINT usuarios_tipo_documento_check CHECK (((tipo_documento)::text = ANY ((ARRAY['CC'::character varying, 'CE'::character varying, 'PA'::character varying, 'PPT'::character varying])::text[])))
);


--
-- Name: bitacora_auditoria id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bitacora_auditoria ALTER COLUMN id SET DEFAULT nextval('public.bitacora_auditoria_id_seq'::regclass);


--
-- Name: aceptaciones_politica aceptaciones_politica_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aceptaciones_politica
    ADD CONSTRAINT aceptaciones_politica_pkey PRIMARY KEY (id);


--
-- Name: bitacora_auditoria bitacora_auditoria_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bitacora_auditoria
    ADD CONSTRAINT bitacora_auditoria_pkey PRIMARY KEY (id);


--
-- Name: calendario_tributario calendario_tributario_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendario_tributario
    ADD CONSTRAINT calendario_tributario_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id);


--
-- Name: fuentes_ingreso fuentes_ingreso_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fuentes_ingreso
    ADD CONSTRAINT fuentes_ingreso_pkey PRIMARY KEY (id);


--
-- Name: gastos gastos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_pkey PRIMARY KEY (id);


--
-- Name: historial_contrasenas historial_contrasenas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.historial_contrasenas
    ADD CONSTRAINT historial_contrasenas_pkey PRIMARY KEY (id);


--
-- Name: ingresos ingresos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ingresos
    ADD CONSTRAINT ingresos_pkey PRIMARY KEY (id);


--
-- Name: liquidaciones_seguridad_social liquidaciones_seguridad_social_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.liquidaciones_seguridad_social
    ADD CONSTRAINT liquidaciones_seguridad_social_pkey PRIMARY KEY (id);


--
-- Name: liquidaciones_seguridad_social uq_liquidacion_usuario_periodo; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.liquidaciones_seguridad_social
    ADD CONSTRAINT uq_liquidacion_usuario_periodo UNIQUE (usuario_id, periodo);


--
-- Name: pagos_seguridad_social pagos_seguridad_social_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_seguridad_social
    ADD CONSTRAINT pagos_seguridad_social_pkey PRIMARY KEY (id);


--
-- Name: parametros_normativos parametros_normativos_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.parametros_normativos
    ADD CONSTRAINT parametros_normativos_pkey PRIMARY KEY (id);


--
-- Name: perfiles_tributarios perfiles_tributarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.perfiles_tributarios
    ADD CONSTRAINT perfiles_tributarios_pkey PRIMARY KEY (id);


--
-- Name: plantillas_recurrencia plantillas_recurrencia_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plantillas_recurrencia
    ADD CONSTRAINT plantillas_recurrencia_pkey PRIMARY KEY (id);


--
-- Name: sesiones_usuario sesiones_usuario_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sesiones_usuario
    ADD CONSTRAINT sesiones_usuario_pkey PRIMARY KEY (id);


--
-- Name: sesiones_usuario sesiones_usuario_token_jti_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sesiones_usuario
    ADD CONSTRAINT sesiones_usuario_token_jti_key UNIQUE (token_jti);


--
-- Name: tarifas_escalonadas tarifas_escalonadas_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tarifas_escalonadas
    ADD CONSTRAINT tarifas_escalonadas_pkey PRIMARY KEY (id);


--
-- Name: tasas_cambio tasas_cambio_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasas_cambio
    ADD CONSTRAINT tasas_cambio_pkey PRIMARY KEY (id);


--
-- Name: tasas_cambio uq_tasas_cambio_moneda_desde; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasas_cambio
    ADD CONSTRAINT uq_tasas_cambio_moneda_desde UNIQUE (moneda, vigencia_desde);


--
-- Name: tokens_seguridad tokens_seguridad_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tokens_seguridad
    ADD CONSTRAINT tokens_seguridad_pkey PRIMARY KEY (id);


--
-- Name: parametros_normativos uq_parametro_anio_version; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.parametros_normativos
    ADD CONSTRAINT uq_parametro_anio_version UNIQUE (anio_vigencia, version);


--
-- Name: usuarios uq_usuarios_documento; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT uq_usuarios_documento UNIQUE (tipo_documento, numero_documento);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- Name: idx_aceptaciones_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_aceptaciones_usuario ON public.aceptaciones_politica USING btree (usuario_id, fecha_aceptacion DESC);


--
-- Name: idx_bitacora_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_bitacora_fecha ON public.bitacora_auditoria USING btree (fecha_evento DESC);


--
-- Name: idx_bitacora_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_bitacora_usuario ON public.bitacora_auditoria USING btree (usuario_id);


--
-- Name: idx_calendario_busqueda; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_calendario_busqueda ON public.calendario_tributario USING btree (anio_vigencia, tipo_obligacion, digito_desde, digito_hasta);


--
-- Name: idx_gastos_dimensiones; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_gastos_dimensiones ON public.gastos USING btree (usuario_id, categoria_id, subcategoria_id, naturaleza, necesidad, medio_pago) WHERE (eliminado_en IS NULL);


--
-- Name: idx_gastos_etiquetas_gin; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_gastos_etiquetas_gin ON public.gastos USING gin (etiquetas) WHERE (eliminado_en IS NULL);


--
-- Name: idx_gastos_usuario_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_gastos_usuario_fecha ON public.gastos USING btree (usuario_id, fecha DESC) WHERE (eliminado_en IS NULL);


--
-- Name: idx_historial_pass_usuario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_historial_pass_usuario ON public.historial_contrasenas USING btree (usuario_id, creado_en DESC);


--
-- Name: idx_ingresos_fuente; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ingresos_fuente ON public.ingresos USING btree (usuario_id, fuente_id) WHERE (eliminado_en IS NULL);


--
-- Name: idx_ingresos_usuario_fecha; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_ingresos_usuario_fecha ON public.ingresos USING btree (usuario_id, fecha_recepcion DESC) WHERE (eliminado_en IS NULL);


--
-- Name: idx_plantillas_job_diario; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_plantillas_job_diario ON public.plantillas_recurrencia USING btree (proxima_ejecucion) WHERE (activa = true);


--
-- Name: idx_sesiones_activas; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_sesiones_activas ON public.sesiones_usuario USING btree (usuario_id) WHERE (revocada = false);


--
-- Name: idx_tarifas_escalonadas_param; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tarifas_escalonadas_param ON public.tarifas_escalonadas USING btree (parametro_normativo_id, tipo_tabla, rango_desde);


--
-- Name: idx_tokens_usuario_tipo; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX idx_tokens_usuario_tipo ON public.tokens_seguridad USING btree (usuario_id, tipo) WHERE (usado_en IS NULL);


--
-- Name: uq_categorias_nombre_nivel; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_categorias_nombre_nivel ON public.categorias USING btree (COALESCE(usuario_id, '00000000-0000-0000-0000-000000000000'::uuid), COALESCE(categoria_padre_id, '00000000-0000-0000-0000-000000000000'::uuid), tipo, lower((nombre)::text));


--
-- Name: uq_fuentes_ingreso_usuario_nombre; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_fuentes_ingreso_usuario_nombre ON public.fuentes_ingreso USING btree (usuario_id, lower((nombre)::text));


--
-- Name: uq_parametro_normativo_activo_anio; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_parametro_normativo_activo_anio ON public.parametros_normativos USING btree (anio_vigencia) WHERE (es_activa = true);


--
-- Name: uq_pago_periodo; Type: INDEX; Schema: public; Owner: -
-- CA-035.2: un solo pago por periodo, salvo las planillas de corrección.
--

CREATE UNIQUE INDEX uq_pago_periodo ON public.pagos_seguridad_social USING btree (usuario_id, periodo) WHERE ((es_correccion = false) AND (eliminado_en IS NULL));


--
-- Name: uq_perfil_tributario_vigente; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_perfil_tributario_vigente ON public.perfiles_tributarios USING btree (usuario_id) WHERE (vigente_hasta IS NULL);


--
-- Name: uq_usuarios_correo_lower; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX uq_usuarios_correo_lower ON public.usuarios USING btree (lower((correo)::text));


--
-- Name: fuentes_ingreso trg_fuentes_ingreso_actualizado; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_fuentes_ingreso_actualizado BEFORE UPDATE ON public.fuentes_ingreso FOR EACH ROW EXECUTE FUNCTION public.fn_actualizar_timestamp();


--
-- Name: gastos trg_gastos_actualizado; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_gastos_actualizado BEFORE UPDATE ON public.gastos FOR EACH ROW EXECUTE FUNCTION public.fn_actualizar_timestamp();


--
-- Name: ingresos trg_ingresos_actualizado; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_ingresos_actualizado BEFORE UPDATE ON public.ingresos FOR EACH ROW EXECUTE FUNCTION public.fn_actualizar_timestamp();


--
-- Name: usuarios trg_usuarios_actualizado; Type: TRIGGER; Schema: public; Owner: -
--

CREATE TRIGGER trg_usuarios_actualizado BEFORE UPDATE ON public.usuarios FOR EACH ROW EXECUTE FUNCTION public.fn_actualizar_timestamp();


--
-- Name: aceptaciones_politica aceptaciones_politica_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.aceptaciones_politica
    ADD CONSTRAINT aceptaciones_politica_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: bitacora_auditoria bitacora_auditoria_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.bitacora_auditoria
    ADD CONSTRAINT bitacora_auditoria_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: calendario_tributario calendario_tributario_creado_por_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.calendario_tributario
    ADD CONSTRAINT calendario_tributario_creado_por_fkey FOREIGN KEY (creado_por) REFERENCES public.usuarios(id) ON DELETE RESTRICT;


--
-- Name: categorias categorias_categoria_padre_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_categoria_padre_id_fkey FOREIGN KEY (categoria_padre_id) REFERENCES public.categorias(id) ON DELETE CASCADE;


--
-- Name: categorias categorias_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.categorias
    ADD CONSTRAINT categorias_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: fuentes_ingreso fuentes_ingreso_tipo_fuente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fuentes_ingreso
    ADD CONSTRAINT fuentes_ingreso_tipo_fuente_id_fkey FOREIGN KEY (tipo_fuente_id) REFERENCES public.categorias(id) ON DELETE RESTRICT;


--
-- Name: fuentes_ingreso fuentes_ingreso_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.fuentes_ingreso
    ADD CONSTRAINT fuentes_ingreso_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: gastos gastos_categoria_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_categoria_id_fkey FOREIGN KEY (categoria_id) REFERENCES public.categorias(id) ON DELETE RESTRICT;


--
-- Name: gastos gastos_plantilla_recurrencia_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_plantilla_recurrencia_id_fkey FOREIGN KEY (plantilla_recurrencia_id) REFERENCES public.plantillas_recurrencia(id) ON DELETE SET NULL;


--
-- Name: gastos gastos_subcategoria_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_subcategoria_id_fkey FOREIGN KEY (subcategoria_id) REFERENCES public.categorias(id) ON DELETE RESTRICT;


--
-- Name: gastos gastos_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.gastos
    ADD CONSTRAINT gastos_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: historial_contrasenas historial_contrasenas_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.historial_contrasenas
    ADD CONSTRAINT historial_contrasenas_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: ingresos ingresos_fuente_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ingresos
    ADD CONSTRAINT ingresos_fuente_id_fkey FOREIGN KEY (fuente_id) REFERENCES public.fuentes_ingreso(id) ON DELETE RESTRICT;


--
-- Name: ingresos ingresos_plantilla_recurrencia_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ingresos
    ADD CONSTRAINT ingresos_plantilla_recurrencia_id_fkey FOREIGN KEY (plantilla_recurrencia_id) REFERENCES public.plantillas_recurrencia(id) ON DELETE SET NULL;


--
-- Name: ingresos ingresos_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.ingresos
    ADD CONSTRAINT ingresos_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: liquidaciones_seguridad_social liquidaciones_seguridad_social_parametro_normativo_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.liquidaciones_seguridad_social
    ADD CONSTRAINT liquidaciones_seguridad_social_parametro_normativo_id_foreign FOREIGN KEY (parametro_normativo_id) REFERENCES public.parametros_normativos(id);


--
-- Name: liquidaciones_seguridad_social liquidaciones_seguridad_social_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.liquidaciones_seguridad_social
    ADD CONSTRAINT liquidaciones_seguridad_social_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: pagos_seguridad_social pagos_seguridad_social_gasto_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_seguridad_social
    ADD CONSTRAINT pagos_seguridad_social_gasto_id_foreign FOREIGN KEY (gasto_id) REFERENCES public.gastos(id) ON DELETE SET NULL;


--
-- Name: pagos_seguridad_social pagos_seguridad_social_usuario_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.pagos_seguridad_social
    ADD CONSTRAINT pagos_seguridad_social_usuario_id_foreign FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: parametros_normativos parametros_normativos_creado_por_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.parametros_normativos
    ADD CONSTRAINT parametros_normativos_creado_por_fkey FOREIGN KEY (creado_por) REFERENCES public.usuarios(id) ON DELETE RESTRICT;


--
-- Name: perfiles_tributarios perfiles_tributarios_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.perfiles_tributarios
    ADD CONSTRAINT perfiles_tributarios_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: plantillas_recurrencia plantillas_recurrencia_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plantillas_recurrencia
    ADD CONSTRAINT plantillas_recurrencia_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: sesiones_usuario sesiones_usuario_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sesiones_usuario
    ADD CONSTRAINT sesiones_usuario_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- Name: tarifas_escalonadas tarifas_escalonadas_parametro_normativo_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tarifas_escalonadas
    ADD CONSTRAINT tarifas_escalonadas_parametro_normativo_id_fkey FOREIGN KEY (parametro_normativo_id) REFERENCES public.parametros_normativos(id) ON DELETE CASCADE;


--
-- Name: tasas_cambio tasas_cambio_registrado_por_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tasas_cambio
    ADD CONSTRAINT tasas_cambio_registrado_por_foreign FOREIGN KEY (registrado_por) REFERENCES public.usuarios(id) ON DELETE SET NULL;


--
-- Name: tokens_seguridad tokens_seguridad_usuario_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.tokens_seguridad
    ADD CONSTRAINT tokens_seguridad_usuario_id_fkey FOREIGN KEY (usuario_id) REFERENCES public.usuarios(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

