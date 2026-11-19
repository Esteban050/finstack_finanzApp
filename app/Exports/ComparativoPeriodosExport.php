<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MOD-11 — HU-H06: Exportación de Comparativo entre Periodos a XLSX (RF-069.3, RF-070).
 */
class ComparativoPeriodosExport
{
    public const FORMATO_MONEDA_COP_ENTERO = '"$"#,##0;[Red]"$"\(#,##0\);"-"';
    public const FORMATO_PORCENTAJE = '+0.00%;-0.00%;0.00%';

    public function __construct(
        protected array $datos,
        protected string $nombreUsuario = 'Usuario'
    ) {}

    public function construirSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Comparativo de Periodos');

        $textoA = $this->datos['periodo_a']['texto'] ?? 'Periodo A';
        $textoB = $this->datos['periodo_b']['texto'] ?? 'Periodo B';

        // Título del reporte
        $sheet->setCellValue('A1', 'SCFP — Comparativo Financiero entre Periodos');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Usuario: ' . $this->nombreUsuario);
        $sheet->setCellValue('A3', "Periodo A (Base): {$textoA}");
        $sheet->setCellValue('A4', "Periodo B (Comparado): {$textoB}");
        $sheet->setCellValue('A5', 'Fecha de generación: ' . now()->format('Y-m-d H:i:s'));
        $sheet->getStyle('A2:A5')->getFont()->setItalic(true)->setSize(10);

        // SECCIÓN 1: RESUMEN GENERAL
        $sheet->setCellValue('A7', 'RESUMEN DE INDICADORES CLAVE');
        $sheet->getStyle('A7')->getFont()->setBold(true)->setSize(11);

        $sheet->setCellValue('A8', 'Indicador');
        $sheet->setCellValue('B8', "Periodo A ({$textoA})");
        $sheet->setCellValue('C8', "Periodo B ({$textoB})");
        $sheet->setCellValue('D8', 'Variación ($)');
        $sheet->setCellValue('E8', 'Variación (%)');

        $sheet->getStyle('A8:E8')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $resumen = $this->datos['resumen'] ?? [];
        $filasResumen = [
            ['Ingresos netos', $resumen['ingresos'] ?? []],
            ['Gastos totales', $resumen['gastos'] ?? []],
            ['Balance', $resumen['balance'] ?? []],
        ];

        $filaR = 9;
        foreach ($filasResumen as [$nombreInd, $data]) {
            $sheet->setCellValue("A{$filaR}", $nombreInd);
            $sheet->setCellValueExplicit("B{$filaR}", (float) ($data['monto_a'] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("C{$filaR}", (float) ($data['monto_b'] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("D{$filaR}", (float) ($data['variacion_pesos'] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

            if (($data['variacion_porc_texto'] ?? '') === 'N/A') {
                $sheet->setCellValue("E{$filaR}", 'N/A');
            } else {
                $sheet->setCellValueExplicit("E{$filaR}", ((float) ($data['variacion_porc'] ?? 0)) / 100, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->getStyle("E{$filaR}")->getNumberFormat()->setFormatCode(self::FORMATO_PORCENTAJE);
            }
            $filaR++;
        }

        // Fila presupuesto (en puntos porcentuales)
        $pres = $resumen['presupuesto'] ?? [];
        $sheet->setCellValue("A{$filaR}", 'Ejecución del presupuesto');
        $sheet->setCellValue("B{$filaR}", $pres['periodo_a'] ?? 'Sin presupuesto');
        $sheet->setCellValue("C{$filaR}", $pres['periodo_b'] ?? 'Sin presupuesto');
        $sheet->setCellValue("D{$filaR}", $pres['variacion_pp_texto'] ?? 'N/A');
        $sheet->setCellValue("E{$filaR}", '-');
        $filaR++;

        $sheet->getStyle("B9:D11")->getNumberFormat()->setFormatCode(self::FORMATO_MONEDA_COP_ENTERO);

        // SECCIÓN 2: DETALLE COMPARATIVO POR CATEGORÍA
        $filaCatInicio = $filaR + 2;
        $sheet->setCellValue("A{$filaCatInicio}", 'COMPARATIVO DE GASTOS POR CATEGORÍA (Ordenado por variación en pesos)');
        $sheet->getStyle("A{$filaCatInicio}")->getFont()->setBold(true)->setSize(11);

        $filaEncCat = $filaCatInicio + 1;
        $sheet->setCellValue("A{$filaEncCat}", 'Categoría');
        $sheet->setCellValue("B{$filaEncCat}", "Periodo A ({$textoA})");
        $sheet->setCellValue("C{$filaEncCat}", "Periodo B ({$textoB})");
        $sheet->setCellValue("D{$filaEncCat}", 'Variación ($)');
        $sheet->setCellValue("E{$filaEncCat}", 'Variación (%)');

        $sheet->getStyle("A{$filaEncCat}:E{$filaEncCat}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $filaC = $filaEncCat + 1;
        $categorias = $this->datos['tabla_categorias'] ?? [];

        foreach ($categorias as $cat) {
            $sheet->setCellValue("A{$filaC}", $cat['nombre']);
            $sheet->setCellValueExplicit("B{$filaC}", (float) $cat['periodo_a'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("C{$filaC}", (float) $cat['periodo_b'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("D{$filaC}", (float) $cat['variacion_pesos'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

            if ($cat['variacion_porc_texto'] === 'N/A') {
                $sheet->setCellValue("E{$filaC}", 'N/A');
            } else {
                $sheet->setCellValueExplicit("E{$filaC}", ((float) ($cat['variacion_porc'] ?? 0)) / 100, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $sheet->getStyle("E{$filaC}")->getNumberFormat()->setFormatCode(self::FORMATO_PORCENTAJE);
            }

            $filaC++;
        }

        $sheet->getStyle("B" . ($filaEncCat + 1) . ":D" . max($filaC - 1, $filaEncCat + 1))->getNumberFormat()->setFormatCode(self::FORMATO_MONEDA_COP_ENTERO);

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function descargar(string $nombreArchivo = 'comparativo_periodos.xlsx'): StreamedResponse
    {
        $spreadsheet = $this->construirSpreadsheet();

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $nombreArchivo . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
