<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MOD-11 — HU-H05: Exportación de Gastos por Dimensión y Top de Comercios a XLSX (RF-069.3).
 */
class GastosDimensionExport
{
    public const FORMATO_MONEDA_COP_ENTERO = '"$"#,##0;[Red]"$"\(#,##0\);"-"';
    public const FORMATO_PORCENTAJE = '0.00%';

    public function __construct(
        protected array $datos,
        protected string $nombreUsuario = 'Usuario'
    ) {}

    public function construirSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // ---------------- HOJA 1: MATRIZ POR DIMENSIÓN ----------------
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Gastos por Dimensión');

        $dimFilaNom = $this->datos['parametros']['dimension_fila_nombre'] ?? 'Fila';
        $dimColNom = $this->datos['parametros']['dimension_columna_nombre'] ?? 'Columna';
        $periodoTexto = $this->datos['parametros']['periodo_texto'] ?? '';

        $sheet->setCellValue('A1', "SCFP — Gastos por Dimensión: {$dimFilaNom} vs {$dimColNom}");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Usuario: ' . $this->nombreUsuario);
        $sheet->setCellValue('A3', 'Periodo: ' . $periodoTexto);
        $sheet->setCellValue('A4', 'Fecha de generación: ' . now()->format('Y-m-d H:i:s'));
        if (!empty($this->datos['aviso_etiquetas'])) {
            $sheet->setCellValue('A5', 'Nota: ' . $this->datos['aviso_etiquetas']);
            $sheet->getStyle('A5')->getFont()->getColor()->setRGB('D97706'); // Amber 600
        }
        $sheet->getStyle('A2:A4')->getFont()->setItalic(true)->setSize(10);

        // Encabezados
        $filaInicio = 7;
        $sheet->setCellValue("A{$filaInicio}", $dimFilaNom);

        $columnas = $this->datos['columnas'] ?? [];
        $colIdx = 2; // Col B
        foreach ($columnas as $colNombre) {
            $sheet->setCellValue([$colIdx, $filaInicio], $colNombre);
            $colIdx++;
        }

        $colTotal = $colIdx++;
        $colPorc = $colIdx++;
        $colConteo = $colIdx++;
        $colProm = $colIdx++;

        $sheet->setCellValue([$colTotal, $filaInicio], 'Total COP');
        $sheet->setCellValue([$colPorc, $filaInicio], '% del Total');
        $sheet->setCellValue([$colConteo, $filaInicio], 'Cant. Gastos');
        $sheet->setCellValue([$colProm, $filaInicio], 'Promedio por Gasto');

        $colFinalLetra = Coordinate::stringFromColumnIndex($colProm);
        $sheet->getStyle("A{$filaInicio}:{$colFinalLetra}{$filaInicio}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Filas de datos
        $filaActual = $filaInicio + 1;
        $filas = $this->datos['filas'] ?? [];

        foreach ($filas as $f) {
            $sheet->setCellValue("A{$filaActual}", $f['nombre']);

            $cIdx = 2;
            foreach ($columnas as $colNom) {
                $monto = (float) ($f['columnas'][$colNom] ?? 0);
                $sheet->setCellValueExplicit([$cIdx, $filaActual], $monto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $cIdx++;
            }

            $sheet->setCellValueExplicit([$colTotal, $filaActual], (float) $f['total'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit([$colPorc, $filaActual], ((float) $f['porcentaje']) / 100, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit([$colConteo, $filaActual], (int) $f['cantidad_gastos'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit([$colProm, $filaActual], (float) $f['promedio_por_gasto'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

            $filaActual++;
        }

        // Fila de totales
        $filaTotal = $filaActual;
        $sheet->setCellValue("A{$filaTotal}", 'TOTAL GENERAL');

        $cIdx = 2;
        $totalesCols = $this->datos['totales_columnas'] ?? [];
        foreach ($columnas as $colNom) {
            $sheet->setCellValueExplicit([$cIdx, $filaTotal], (float) ($totalesCols[$colNom] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $cIdx++;
        }

        $sheet->setCellValueExplicit([$colTotal, $filaTotal], (float) ($this->datos['total_general'] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit([$colPorc, $filaTotal], 1.0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $sheet->setCellValueExplicit([$colConteo, $filaTotal], (int) ($this->datos['conteo_total_gastos'] ?? 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $promGeneral = ($this->datos['conteo_total_gastos'] ?? 0) > 0
            ? (float) bcdiv((string) ($this->datos['total_general'] ?? 0), (string) $this->datos['conteo_total_gastos'], 2)
            : 0;
        $sheet->setCellValueExplicit([$colProm, $filaTotal], $promGeneral, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

        $sheet->getStyle("A{$filaTotal}:{$colFinalLetra}{$filaTotal}")->applyFromArray([
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

        // Formatos
        $colTotalLetra = Coordinate::stringFromColumnIndex($colTotal);
        $colPorcLetra = Coordinate::stringFromColumnIndex($colPorc);
        $colPromLetra = Coordinate::stringFromColumnIndex($colProm);

        $sheet->getStyle("B" . ($filaInicio + 1) . ":{$colTotalLetra}{$filaTotal}")->getNumberFormat()->setFormatCode(self::FORMATO_MONEDA_COP_ENTERO);
        $sheet->getStyle("{$colPorcLetra}" . ($filaInicio + 1) . ":{$colPorcLetra}{$filaTotal}")->getNumberFormat()->setFormatCode(self::FORMATO_PORCENTAJE);
        $sheet->getStyle("{$colPromLetra}" . ($filaInicio + 1) . ":{$colPromLetra}{$filaTotal}")->getNumberFormat()->setFormatCode(self::FORMATO_MONEDA_COP_ENTERO);

        // Auto-ajuste
        for ($i = 1; $i <= $colProm; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        // ---------------- HOJA 2: TOP 10 COMERCIOS ----------------
        $sheetComercios = $spreadsheet->createSheet();
        $sheetComercios->setTitle('Top Comercios');

        $sheetComercios->setCellValue('A1', 'SCFP — Comercios donde más gastaste (Top 10)');
        $sheetComercios->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheetComercios->setCellValue('A2', 'Periodo: ' . $periodoTexto);
        $sheetComercios->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        $sheetComercios->setCellValue('A4', '#');
        $sheetComercios->setCellValue('B4', 'Comercio / Beneficiario');
        $sheetComercios->setCellValue('C4', 'Total Gastado');
        $sheetComercios->setCellValue('D4', '% del Gasto Total');
        $sheetComercios->setCellValue('E4', 'Transacciones');

        $sheetComercios->getStyle('A4:E4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $filaC = 5;
        $topComercios = $this->datos['top_comercios'] ?? [];
        foreach ($topComercios as $idx => $tc) {
            $sheetComercios->setCellValue("A{$filaC}", $idx + 1);
            $sheetComercios->setCellValue("B{$filaC}", $tc['comercio']);
            $sheetComercios->setCellValueExplicit("C{$filaC}", (float) $tc['monto'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheetComercios->setCellValueExplicit("D{$filaC}", ((float) $tc['porcentaje']) / 100, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $sheetComercios->setCellValueExplicit("E{$filaC}", (int) $tc['conteo'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            $filaC++;
        }

        $sheetComercios->getStyle("C5:C" . max($filaC - 1, 5))->getNumberFormat()->setFormatCode(self::FORMATO_MONEDA_COP_ENTERO);
        $sheetComercios->getStyle("D5:D" . max($filaC - 1, 5))->getNumberFormat()->setFormatCode(self::FORMATO_PORCENTAJE);

        foreach (range('A', 'E') as $c) {
            $sheetComercios->getColumnDimension($c)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    public function descargar(string $nombreArchivo = 'gastos_por_dimension.xlsx'): StreamedResponse
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
