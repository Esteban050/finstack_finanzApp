<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriaPredeterminadaSeeder extends Seeder
{
    /**
     * RF-015 RN-015.1 & RN-015.2: Catálogo inicial mínimo de categorías predeterminadas
     */
    public function run(): void
    {
        // 1. Fuentes de Ingreso (RN-015.2)
        $fuentesIngreso = [
            'Salario' => ['Salario Base', 'Horas Extras / Bonificaciones'],
            'Honorarios/Servicios' => ['Contratos de Prestación', 'Consultorías'],
            'Arrendamientos' => ['Inmuebles Habitacionales', 'Inmuebles Comerciales'],
            'Rendimientos financieros' => ['Intereses Bancarios', 'CDTs / Bonos'],
            'Dividendos' => ['Acciones Locales', 'Acciones Extranjeras'],
            'Pensión' => ['Pensión Vejez', 'Pensión Sobreviviente'],
            'Venta de activos' => ['Venta de Inmuebles', 'Venta de Vehículos'],
            'Negocio propio' => ['Venta de Bienes', 'Servicios Profesionales'],
            'Otros' => ['Regalos / Premios', 'Reembolsos'],
        ];

        foreach ($fuentesIngreso as $padreNombre => $subcategorias) {
            $catPadre = Categoria::firstOrCreate(
                [
                    'nombre' => $padreNombre,
                    'tipo' => 'INGRESO',
                    'usuario_id' => null,
                    'categoria_padre_id' => null,
                ],
                [
                    'es_predeterminada' => true,
                    'activa' => true,
                    'creado_en' => now(),
                ]
            );

            foreach ($subcategorias as $subNombre) {
                Categoria::firstOrCreate(
                    [
                        'nombre' => $subNombre,
                        'tipo' => 'INGRESO',
                        'usuario_id' => null,
                        'categoria_padre_id' => $catPadre->id,
                    ],
                    [
                        'es_predeterminada' => true,
                        'activa' => true,
                        'creado_en' => now(),
                    ]
                );
            }
        }

        // 2. Gastos (RN-015.1)
        $gastos = [
            'Vivienda' => ['Arriendo / Cuota hipotecaria', 'Mantenimiento / Reparaciones'],
            'Alimentación' => ['Supermercado', 'Restaurantes'],
            'Transporte' => ['Combustible / Parqueadero', 'Transporte Público / Taxis'],
            'Salud' => ['Medicamentos', 'Citas y Copagos'],
            'Educación' => ['Matrículas / Pensiones', 'Cursos y Materiales'],
            'Servicios públicos' => ['Agua y Energía', 'Gas domiciliario'],
            'Telecomunicaciones' => ['Internet Hogar', 'Plan Celular'],
            'Entretenimiento' => ['Streaming y Cine', 'Viajes y Paseos'],
            'Vestuario' => ['Ropa y Calzado', 'Accesorios'],
            'Obligaciones financieras' => ['Tarjetas de Crédito', 'Créditos de Consumo'],
            'Impuestos' => ['Predial / Vehicular', 'Renta / IVA'],
            'Seguridad social' => ['Aporte PILA independiente', 'Aportes voluntarios'],
            'Seguros' => ['Seguro de Vida', 'Seguro de Vehículo'],
            'Mascotas' => ['Alimento para mascotas', 'Veterinaria y Cuidados'],
            'Donaciones' => ['Caridad', 'Ayuda familiar'],
            'Otros' => ['Gastos Imprevistos', 'Comisiones bancarias'],
        ];

        foreach ($gastos as $padreNombre => $subcategorias) {
            $catPadre = Categoria::firstOrCreate(
                [
                    'nombre' => $padreNombre,
                    'tipo' => 'GASTO',
                    'usuario_id' => null,
                    'categoria_padre_id' => null,
                ],
                [
                    'naturaleza_sugerida' => 'FIJO',
                    'necesidad_sugerida' => 'ESENCIAL',
                    'es_predeterminada' => true,
                    'activa' => true,
                    'creado_en' => now(),
                ]
            );

            foreach ($subcategorias as $subNombre) {
                Categoria::firstOrCreate(
                    [
                        'nombre' => $subNombre,
                        'tipo' => 'GASTO',
                        'usuario_id' => null,
                        'categoria_padre_id' => $catPadre->id,
                    ],
                    [
                        'es_predeterminada' => true,
                        'activa' => true,
                        'creado_en' => now(),
                    ]
                );
            }
        }
    }
}
