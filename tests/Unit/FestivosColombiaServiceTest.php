<?php

namespace Tests\Unit;

use App\Services\FestivosColombiaService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * El Domingo de Pascua se calcula sin la extensión calendar de PHP
 * (easter_days no existe en el servidor de Railway).
 */
class FestivosColombiaServiceTest extends TestCase
{
    public static function pascuas(): array
    {
        return [
            '2024' => [2024, '2024-03-31'],
            '2025' => [2025, '2025-04-20'],
            '2026' => [2026, '2026-04-05'],
            '2027' => [2027, '2027-03-28'],
            '2038' => [2038, '2038-04-25'],
        ];
    }

    #[DataProvider('pascuas')]
    public function test_calcula_el_domingo_de_pascua(int $anio, string $esperado): void
    {
        $this->assertSame($esperado, FestivosColombiaService::domingoPascua($anio)->toDateString());
    }

    public function test_festivos_moviles_de_2026(): void
    {
        $festivos = FestivosColombiaService::festivos(2026);

        foreach (['2026-04-02', '2026-04-03', '2026-05-18', '2026-06-08', '2026-06-15'] as $fecha) {
            $this->assertContains($fecha, $festivos, "Falta el festivo {$fecha}");
        }
    }
}
