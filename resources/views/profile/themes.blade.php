@extends('layouts.app')
@section('title', 'Temas')

@section('content')
    <header class="ui-page-header">
        <a href="{{ route('profile.show') }}">Volver a Mi perfil</a>
        <h1>Temas</h1>
        <p>Elige una paleta para cada modo. Tus preferencias se guardan en este navegador.</p>
    </header>

    <div class="theme-settings">
        <x-ui.card title="Tus temas">
            <form data-theme-settings>
                <fieldset class="ui-field-group" disabled>
                    @foreach(['light' => 'Modo claro', 'dark' => 'Modo oscuro'] as $mode => $label)
                        <fieldset class="ui-field-group theme-palette-group">
                            <legend>{{ $label }}</legend>
                            <div class="theme-palette-grid">
                                @foreach(config('themes.palettes') as $palette => $name)
                                    <label class="theme-choice">
                                        <input class="sr-only" type="radio" name="{{ $mode }}" value="{{ $palette }}"
                                               data-palette-label="{{ $name }}" @checked($palette === 'orange')>
                                        <span class="theme-choice-card">
                                            <span class="theme-swatches" data-theme="{{ $mode }}" data-palette="{{ $palette }}" aria-hidden="true">
                                                <span></span><span></span><span></span>
                                            </span>
                                            <span class="theme-choice-label">{{ $name }}<x-icon name="ui-exito" :size="16" class="theme-choice-check" /></span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                    <x-ui.button type="submit" variant="primary">Guardar temas</x-ui.button>
                </fieldset>
                <p class="ui-help theme-status" role="status" data-theme-status></p>
            </form>
            <noscript><p class="ui-help">Activa JavaScript para previsualizar y guardar los temas.</p></noscript>
        </x-ui.card>

        <x-ui.card title="Vista previa" class="theme-demo-panel">
            <div class="theme-preview-controls">
                <div class="ui-inline" role="group" aria-label="Modo de la vista previa">
                    <button type="button" class="ui-button ui-button--ghost" data-preview-mode="light" aria-pressed="false" disabled>Claro</button>
                    <button type="button" class="ui-button ui-button--ghost" data-preview-mode="dark" aria-pressed="false" disabled>Oscuro</button>
                </div>
                <span class="ui-help" aria-live="polite" data-theme-preview-label></span>
            </div>

            <div class="theme-preview" data-theme-preview data-theme="light" data-palette="orange" aria-hidden="true">
                <div class="theme-preview-nav">
                    <strong><x-application-logo /> FinanzApp</strong>
                    <span class="theme-preview-current"><x-icon name="ui-resumen" :size="16" /> Resumen</span>
                    <span><x-icon name="act-ingreso" :size="16" /> Ingresos</span>
                    <span><x-icon name="act-gasto" :size="16" /> Gastos</span>
                    <span><x-icon name="cls-etiqueta" :size="16" /> Categorías</span>
                </div>
                <div class="theme-preview-content">
                    <div class="theme-demo-heading">
                        <div><span class="ui-help">Este mes</span><strong>Resumen financiero</strong></div>
                        <span class="ui-button ui-button--primary">Registrar gasto</span>
                    </div>

                    <div class="theme-demo-totals">
                        <div class="ui-card theme-demo-balance"><span class="ui-help">Balance</span><strong>$ 1.250.000</strong></div>
                        <div class="ui-card"><span class="ui-help">Ingresos</span><strong>$ 3.400.000</strong></div>
                        <div class="ui-card"><span class="ui-help">Gastos</span><strong>$ 2.150.000</strong></div>
                    </div>

                    <div class="ui-card theme-demo-chart">
                        <strong>Ingresos y gastos</strong>
                        <div class="theme-demo-legend">
                            <span><i></i> Ingresos</span><span><i></i> Gastos</span>
                        </div>
                        <div class="theme-demo-plot">
                            @foreach([[85, 54], [46, 36], [68, 60], [71, 48]] as [$income, $expense])
                                <div class="theme-demo-week">
                                    <div class="theme-demo-bars" style="--income-height: {{ $income }}%; --expense-height: {{ $expense }}%"><i></i><i></i></div>
                                    <span class="ui-help">Sem. {{ $loop->iteration }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="ui-card theme-demo-movements">
                        <strong>Movimientos recientes</strong>
                        <div>
                            <span><x-icon name="act-ingreso" :size="16" /> Nómina</span><span class="theme-demo-positive">+ $ 2.700.000</span>
                        </div>
                        <div>
                            <span><x-icon name="act-gasto" :size="16" /> Mercado</span><span>− $ 180.000</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.card>
    </div>
@endsection
