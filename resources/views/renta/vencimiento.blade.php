@extends('layouts.app')

@section('title', 'Vencimiento y recordatorios · Declaración ' . $anio)

@section('content')
<div class="finance-page" x-data="{ showModal: false }">
    <header class="income-page-header">
        <div>
            <h1>Vencimiento de la declaración · {{ $anio }}</h1>
            <p>Fecha límite según los dos últimos dígitos de tu documento y el calendario tributario vigente.</p>
        </div>
        <div class="ui-inline">
            @if($vencimiento['estado_declaracion'] !== 'PRESENTADA')
                <button type="button" class="ui-button ui-button--primary" @click="showModal = true">
                    Marcar declaración presentada
                </button>
            @else
                <span class="ui-badge ui-badge--success" style="font-size:0.9rem;padding:0.4rem 0.8rem">
                    ✓ Declaración presentada el {{ $vencimiento['fecha_presentacion_formateada'] }}
                </span>
            @endif
        </div>
    </header>

    <div class="mk-grid-2">
        {{-- TARJETA DE FECHA LÍMITE --}}
        <div class="ui-card">
            <p class="mk-meta">Fecha límite de presentación</p>
            <p class="mk-amount">{{ $vencimiento['fecha_limite_formateada'] }}</p>
            <p class="mk-muted mk-small">
                @if($vencimiento['dias_restantes'] > 0)
                    Faltan <strong>{{ $vencimiento['dias_restantes'] }} días</strong>.
                @elseif($vencimiento['dias_restantes'] === 0)
                    <strong>¡Vence hoy!</strong>
                @else
                    Venció hace <strong>{{ abs($vencimiento['dias_restantes']) }} días</strong>.
                @endif
                Últimos dígitos del documento: <strong>{{ $vencimiento['ultimos_digitos'] }}</strong>.
                Resultado de la última evaluación:
                @if($vencimiento['estado_evaluacion'] === 'OBLIGADO')
                    <span class="ui-badge ui-badge--danger">Obligado</span>
                @elseif($vencimiento['estado_evaluacion'] === 'INCOMPLETO')
                    <span class="ui-badge ui-badge--warning">Incompleto</span>
                @else
                    <span class="ui-badge ui-badge--success">No obligado</span>
                @endif
            </p>

            @if($vencimiento['estado_declaracion'] === 'PRESENTADA')
                <div class="mk-alert info" style="margin-top:1.25rem">
                    <span class="mk-icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                    </span>
                    <div>
                        <strong>Declaración registrada como presentada</strong>
                        <p style="margin:0">Fecha: {{ $vencimiento['fecha_presentacion_formateada'] }} @if($vencimiento['numero_radicado_dian']) · No. radicado DIAN: <strong>{{ $vencimiento['numero_radicado_dian'] }}</strong> @endif</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- RECORDATORIOS PROGRAMADOS --}}
        <div class="ui-card">
            <h2>Recordatorios programados</h2>
            <ol class="mk-tl">
                @foreach($vencimiento['recordatorios'] as $rec)
                    <li @class([
                        'done' => $rec['enviado'],
                        'next' => $rec['programado'],
                        'cancelled' => $rec['cancelado'],
                    ])>
                        <div>
                            <p class="mk-strong mk-small">{{ $rec['etiqueta'] }}</p>
                            <p class="mk-meta">
                                @if($rec['enviado'])
                                    Enviado al centro de notificaciones y al correo
                                @elseif($rec['programado'])
                                    Programado para despacho automático
                                @else
                                    Cancelado (declaración presentada o no obligado)
                                @endif
                            </p>
                        </div>
                        <span class="mk-meta">{{ $rec['fecha_formateada'] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </div>

    <div class="mk-alert info" style="margin-top:1.5rem">
        <span class="mk-icon">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.5h.01"/></svg>
        </span>
        <div>
            <strong>Solo recibes recordatorios si la última evaluación es "Obligado" o "Incompleto".</strong>
            <p>Si el resultado cambia a "No obligado" o si marcas tu declaración como presentada, todos los recordatorios pendientes se cancelan inmediatamente.</p>
        </div>
    </div>

    <p class="ui-help finance-legal">Valores estimados con fines informativos. No constituyen asesoría tributaria, contable ni financiera.</p>

    {{-- MODAL DIALOG: MARCAR PRESENTADA --}}
    <div x-show="showModal"
         x-cloak
         class="mk-modal-overlay"
         style="position:fixed;inset:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;"
         @keydown.escape.window="showModal = false">
        <div class="mk-dialog" role="dialog" aria-labelledby="dlg06" style="background:var(--color-bg-surface, #fff);border-radius:12px;padding:1.5rem;max-width:480px;width:90%;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);">
            <h2 id="dlg06" class="mk-h2" style="margin-top:0">Marcar la declaración como presentada</h2>
            <p class="mk-muted mk-small">Año gravable {{ $anio }}. Después de confirmar no recibirás más recordatorios de este año gravable.</p>

            <form action="{{ route('renta.marcarPresentada', ['anio' => $anio]) }}" method="POST">
                @csrf

                <div class="ui-field" style="margin:1.25rem 0 0">
                    <label for="fecha_presentacion">Fecha de presentación <span aria-hidden="true" style="color:var(--color-danger, #e11d48)">*</span></label>
                    <input type="date"
                           id="fecha_presentacion"
                           name="fecha_presentacion"
                           class="ui-input"
                           required
                           max="{{ now()->toDateString() }}"
                           value="{{ now()->toDateString() }}"
                           style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:6px">
                    <p class="ui-help" style="margin-top:0.25rem">No puede ser una fecha futura (RN-047.2).</p>
                </div>

                <div class="ui-field" style="margin:1rem 0 0">
                    <label for="numero_radicado_dian">Número de radicado / autoadhesivo DIAN (Opcional)</label>
                    <input type="text"
                           id="numero_radicado_dian"
                           name="numero_radicado_dian"
                           class="ui-input"
                           placeholder="Ej. 9100012345678"
                           maxlength="100"
                           style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:6px">
                    <p class="ui-help" style="margin-top:0.25rem">Número asignado por la DIAN en el formulario 210.</p>
                </div>

                <footer style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1.5rem">
                    <button type="button" class="ui-button ui-button--ghost" @click="showModal = false">Cancelar</button>
                    <button type="submit" class="ui-button ui-button--primary">Confirmar presentación</button>
                </footer>
            </form>
        </div>
    </div>
</div>
@endsection

