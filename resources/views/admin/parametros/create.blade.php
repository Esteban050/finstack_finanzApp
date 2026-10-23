<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Registrar Nueva Vigencia Normativa
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.parametros.store') }}" class="p-8 bg-white shadow sm:rounded-xl space-y-6">
                @csrf

                @if ($errors->any())
                    <div class="p-4 bg-red-100 border border-red-300 text-red-700 text-sm rounded-lg">
                        <p class="font-bold mb-2">Por favor corrige los siguientes errores:</p>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <h3 class="text-md font-bold text-gray-900 border-b pb-2">1. Identificación y Acto Administrativo</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="anio_vigencia" value="Año de Vigencia *" />
                        <x-text-input id="anio_vigencia" type="number" name="anio_vigencia" :value="old('anio_vigencia', date('Y'))" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="acto_administrativo" value="Acto Administrativo (Fuente Oficial) *" />
                        <x-text-input id="acto_administrativo" type="text" name="acto_administrativo" :value="old('acto_administrativo', 'Res. DIAN 000193 de 2024')" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="fecha_acto" value="Fecha del Acto *" />
                        <x-text-input id="fecha_acto" type="date" name="fecha_acto" :value="old('fecha_acto', date('Y-m-d'))" required class="w-full mt-1" />
                    </div>
                </div>

                <h3 class="text-md font-bold text-gray-900 border-b pb-2 pt-4">2. Unidades Base (Valores Oficiales)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="valor_uvt" value="Valor UVT ($) *" />
                        <x-text-input id="valor_uvt" type="number" step="0.01" name="valor_uvt" :value="old('valor_uvt', 49799.00)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="valor_smmlv" value="Valor SMMLV ($) *" />
                        <x-text-input id="valor_smmlv" type="number" step="0.01" name="valor_smmlv" :value="old('valor_smmlv', 1423500.00)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="ipc_anual" value="IPC Anual DANE (%) *" />
                        <x-text-input id="ipc_anual" type="number" step="0.01" name="ipc_anual" :value="old('ipc_anual', 5.20)" required class="w-full mt-1" />
                    </div>
                </div>

                <h3 class="text-md font-bold text-gray-900 border-b pb-2 pt-4">3. Tarifas y Porcentajes de Seguridad Social</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <x-input-label for="tarifa_salud_indep" value="Salud Indep. (%) *" />
                        <x-text-input id="tarifa_salud_indep" type="number" step="0.01" name="tarifa_salud_indep" :value="old('tarifa_salud_indep', 12.5)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tarifa_pension_indep" value="Pensión Indep. (%) *" />
                        <x-text-input id="tarifa_pension_indep" type="number" step="0.01" name="tarifa_pension_indep" :value="old('tarifa_pension_indep', 16.0)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tarifa_salud_emp" value="Salud Empleado (%) *" />
                        <x-text-input id="tarifa_salud_emp" type="number" step="0.01" name="tarifa_salud_emp" :value="old('tarifa_salud_emp', 4.0)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tarifa_pension_emp" value="Pensión Empleado (%) *" />
                        <x-text-input id="tarifa_pension_emp" type="number" step="0.01" name="tarifa_pension_emp" :value="old('tarifa_pension_emp', 4.0)" required class="w-full mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <x-input-label for="porc_ibc_indep" value="% IBC Indep. (Ley 1955) *" />
                        <x-text-input id="porc_ibc_indep" type="number" step="0.01" name="porc_ibc_indep" :value="old('porc_ibc_indep', 40.0)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="porc_ibc_integral" value="% IBC Salario Integral *" />
                        <x-text-input id="porc_ibc_integral" type="number" step="0.01" name="porc_ibc_integral" :value="old('porc_ibc_integral', 70.0)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="ibc_min_smmlv" value="IBC Mínimo (en SMMLV) *" />
                        <x-text-input id="ibc_min_smmlv" type="number" step="0.01" name="ibc_min_smmlv" :value="old('ibc_min_smmlv', 1.0)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="ibc_max_smmlv" value="IBC Máximo (en SMMLV) *" />
                        <x-text-input id="ibc_max_smmlv" type="number" step="0.01" name="ibc_max_smmlv" :value="old('ibc_max_smmlv', 25.0)" required class="w-full mt-1" />
                    </div>
                </div>

                <h3 class="text-md font-bold text-gray-900 border-b pb-2 pt-4">4. Topes de Renta y Exenciones (en UVT)</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="tope_patrimonio_uvt" value="Tope Patrimonio (UVT) *" />
                        <x-text-input id="tope_patrimonio_uvt" type="number" name="tope_patrimonio_uvt" :value="old('tope_patrimonio_uvt', 4500)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tope_ingresos_uvt" value="Tope Ingresos (UVT) *" />
                        <x-text-input id="tope_ingresos_uvt" type="number" name="tope_ingresos_uvt" :value="old('tope_ingresos_uvt', 1400)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tope_tc_uvt" value="Tope Compras Tarjeta Crédito (UVT) *" />
                        <x-text-input id="tope_tc_uvt" type="number" name="tope_tc_uvt" :value="old('tope_tc_uvt', 1400)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tope_compras_uvt" value="Tope Compras y Consumos (UVT) *" />
                        <x-text-input id="tope_compras_uvt" type="number" name="tope_compras_uvt" :value="old('tope_compras_uvt', 1400)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tope_consignaciones_uvt" value="Tope Consignaciones (UVT) *" />
                        <x-text-input id="tope_consignaciones_uvt" type="number" name="tope_consignaciones_uvt" :value="old('tope_consignaciones_uvt', 1400)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="limite_renta_exenta_25_uvt" value="Renta Exenta 25% Límite (UVT) *" />
                        <x-text-input id="limite_renta_exenta_25_uvt" type="number" name="limite_renta_exenta_25_uvt" :value="old('limite_renta_exenta_25_uvt', 790)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="porc_limite_global_exentas" value="% Límite Global Exentas *" />
                        <x-text-input id="porc_limite_global_exentas" type="number" step="0.01" name="porc_limite_global_exentas" :value="old('porc_limite_global_exentas', 40.0)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="limite_global_exentas_uvt" value="Límite Global Anual (UVT) *" />
                        <x-text-input id="limite_global_exentas_uvt" type="number" name="limite_global_exentas_uvt" :value="old('limite_global_exentas_uvt', 1340)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="tarifa_gmf" value="Tarifa GMF 4x1000 (%) *" />
                        <x-text-input id="tarifa_gmf" type="number" step="0.0001" name="tarifa_gmf" :value="old('tarifa_gmf', 0.4000)" required class="w-full mt-1" />
                    </div>
                    <div>
                        <x-input-label for="exencion_gmf_uvt" value="Exención GMF Mensual (UVT) *" />
                        <x-text-input id="exencion_gmf_uvt" type="number" name="exencion_gmf_uvt" :value="old('exencion_gmf_uvt', 350)" required class="w-full mt-1" />
                    </div>
                </div>

                <h3 class="text-md font-bold text-gray-900 border-b pb-2 pt-4">5. Auditoría y Justificación Normativa</h3>
                <div>
                    <x-input-label for="justificacion_cambio" value="Justificación del Registro / Modificación (mínimo 20 caracteres) *" />
                    <textarea id="justificacion_cambio" name="justificacion_cambio" rows="3" required class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" placeholder="Indicar el decreto o resolución que sustenta la actualización...">{{ old('justificacion_cambio') }}</textarea>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-6 border-t">
                    <a href="{{ route('admin.parametros.index') }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </a>
                    <x-primary-button>
                        Guardar y Activar Vigencia
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
