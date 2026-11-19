<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MOD-11 — HU-H04: Exportación de Flujo de Caja a formato XLSX (RF-069.3).
 *
 * Guarda los montos como valores numéricos con formato de moneda COP para cálculo en hoja de cálculo.
 */
class FlujoCajaExport
{
    public const FORMATO_MONEDA_COP = '"$"#,##0.00;[Red]"$"\(#,##0.00\);"-"';
    public const FORMATO_MONEDA_COP_ENTERO = '"$"#,##0;[Red]"$"\(#,##0\);"-"';

    public function __construct(
        protected array $reporteData,
        protected string $nombreUsuario = 'Usuario'
    ) {}

    public function construirSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Flujo de Caja');

        // Título del reporte y metadatos
        $sheet->setCellValue('A1', 'SCFP — Reporte de Flujo de Caja Histórico');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Usuario: ' . $this->nombreUsuario);
        $sheet->setCellValue('A3', 'Periodo: ' . ($this->reporteData['parametros']['rango_texto'] ?? ''));
        $sheet->setCellValue('A4', 'Agrupación: ' . ucfirst($this->reporteData['parametros']['agrupacion'] ?? ''));
        $sheet->setCellValue('A5', 'Fecha de generación: ' . now()->format('Y-m-d H:i:s'));
        $sheet->getStyle('A2:A5')->getFont()->setItalic(true)->setSize(10);

        // Encabezados de tabla
        $filaInicio = 7;
        $sheet->setCellValue("A{$filaInicio}", 'Periodo');
        $sheet->setCellValue("B{$filaInicio}", 'Fecha Inicio');
        $sheet->setCellValue("C{$filaInicio}", 'Fecha Fin');
        $sheet->setCellValue("D{$filaInicio}", 'Total Ingresos');
        $sheet->setCellValue("E{$filaInicio}", 'Total Gastos');
        $sheet->setCellValue("F{$filaInicio}", 'Flujo Neto');
        $sheet->setCellValue("G{$filaInicio}", 'Flujo Acumulado');

        $colActual = 'H';
        $fuentes = $this->reporteData['catalogo_fuentes'] ?? [];
        $fuentesColMap = [];
        foreach ($fuentes as $fId => $fNom) {
            $sheet->setCellValue("{$colActual}{$filaInicio}", "Ing: {$fNom}");
            $fuentesColMap[$fId] = $colActual;
            $colActual++;
        }

        $categorias = $this->reporteData['catalogo_categorias'] ?? [];
        $categoriasColMap = [];
        foreach ($categorias as $cId => $cNom) {
            $sheet->setCellValue("{$colActual}{$filaInicio}", "Gas: {$cNom}");
            $categoriasColMap[$cId] = $colActual;
            $colActual++;
        }

        $colFinalEncabezado = chr(ord($colActual) - 1);
        $rangoEncabezado = "A{$filaInicio}:{$colFinalEncabezado}{$filaInicio}";

        $sheet->getStyle($rangoEncabezado)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'], // Slate 800
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Filas de datos por periodo
        $filaActual = $filaInicio + 1;
        $periodos = $this->reporteData['periodos'] ?? [];

        foreach ($periodos as $p) {
            $sheet->setCellValue("A{$filaActual}", $p['etiqueta']);
            $sheet->setCellValue("B{$filaActual}", $p['inicio']);
            $sheet->setCellValue("C{$filaActual}", $p['fin']);

            // Montos numéricos estrictos (RN-069.3)
            $sheet->setCellValueExplicit("D{$filaActual}", (float) $p['total_ingresos'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("E{$filaActual}", (float) $p['total_gastos'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("F{$filaActual}", (float) $p['flujo_neto'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("G{$filaActual}", (float) $p['flujo_acumulado'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

            foreach ($fuentesColMap as $fId => $colLetra) {
                $montoF = (float) ($p['ingresos_por_fuente'][$fId] ?? 0);
                $sheet->setCellValueExplicit("{$colLetra}{$filaActual}", $montoF, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            }

            foreach ($categoriasColMap as $cId => $colLetra) {
                $montoC = (float) ($p['gastos_por_categoria'][$cId] ?? 0);
                $sheet->setCellValueExplicit("{$colLetra}{$filaActual}", $montoC, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            }

            $filaActual++;
        }

        // Fila de totales
        $filaTotal = $filaActual;
        $sheet->setCellValue("A{$filaTotal}", 'TOTAL GENERAL');
        $sheet->setCellValue("B{$filaTotal}", '');
        $sheet->setCellValue("C{$filaTotal}", '');

        $totales = $this->reporteData['totales'] ?? [];
        $sheet->setCellValueExplicit("D{$filaTotal}", (float) ($totales['total_ingresos'] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("E{$filaTotal}", (float) ($totales['total_gastos'] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("F{$filaTotal}", (float) ($totales['balance_neto'] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit("G{$filaTotal}", (float) ($totales['flujo_acumulado_final'] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

        $sheet->getStyle("A{$filaTotal}:{$colFinalEncabezado}{$filaTotal}")->applyFromArray([
            'font' => ['bold' => true],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_THIN],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F1F5F9'],
            ],
        ]);

        // Formato de moneda para columnas de dinero
        $rangoMontos = "D" . ($filaInicio + 1) . ":{$colFinalEncabezado}{$filaTotal}";
        $sheet->getStyle($rangoMontos)->getNumberFormat()->setFormatCode(self::FORMATO_MONEDA_COP_ENTERO);

        // Auto-ajuste de columnas
        foreach (range('A', $colFinalEncabezado) as $colID) {
            $sheet->getColumnDimension($colID)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function descargar(string $nombreArchivo = 'reporte_flujo_caja.xlsx'): StreamedResponse
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
