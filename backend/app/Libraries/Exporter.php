<?php

namespace App\Libraries;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Exports any tabular report to branded Excel (.xlsx) or PDF.
 *
 * $columns: ['key' => ['label' => 'Amount', 'type' => 'money|number|date|text']]
 */
class Exporter
{
    public static function export(string $format, string $title, array $columns, array $rows, array $meta = [], array $totals = [])
    {
        $slug = url_title($title, '-', true) . '-' . date('Ymd-His');
        if ($format === 'xlsx') {
            return self::xlsx($title, $columns, $rows, $meta, $totals, $slug . '.xlsx');
        }
        $pdf = Pdf::render('pdf/report', compact('title', 'columns', 'rows', 'meta', 'totals'), count($columns) > 6 ? 'landscape' : 'portrait');
        return Pdf::respond($pdf, $slug . '.pdf', false);
    }

    private static function xlsx(string $title, array $columns, array $rows, array $meta, array $totals, string $filename)
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[^\w\s-]/u', '', $title), 0, 31) ?: 'Report');
        $book->getProperties()->setCreator('Wheelsturn GMS')->setTitle($title);

        $logo = FCPATH . 'assets/img/logo-sm.png';
        if (is_file($logo)) {
            $d = new Drawing();
            $d->setPath($logo)->setHeight(48)->setCoordinates('A1')->setWorksheet($sheet);
        }
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(max(1, count($columns)));
        $sheet->getRowDimension(1)->setRowHeight(40);
        $sheet->setCellValue('A3', $title);
        $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF0B0D2E');
        $r = 4;
        $meta = ['Generated' => date('d M Y H:i')] + $meta;
        foreach ($meta as $k => $v) {
            $sheet->setCellValue("A{$r}", $k . ': ' . $v);
            $sheet->getStyle("A{$r}")->getFont()->setSize(9)->getColor()->setARGB('FF64748B');
            $r++;
        }
        $r++;
        $headerRow = $r;
        $c = 1;
        foreach ($columns as $col) {
            $sheet->setCellValue([$c++, $r], $col['label']);
        }
        $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0B0D2E']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($r)->setRowHeight(22);

        foreach ($rows as $row) {
            $r++;
            $c = 1;
            foreach ($columns as $key => $col) {
                $v = $row[$key] ?? '';
                $type = $col['type'] ?? 'text';
                if (in_array($type, ['money', 'number'], true) && $v !== '' && $v !== null) {
                    $sheet->setCellValue([$c, $r], (float) $v);
                    $sheet->getStyle([$c, $r])->getNumberFormat()->setFormatCode($type === 'money' ? '"R" #,##0.00;[Red]-"R" #,##0.00' : '#,##0.##');
                } else {
                    $sheet->setCellValueExplicit([$c, $r], (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $c++;
            }
            if ($r % 2 === 0) {
                $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
            }
        }
        if ($totals) {
            $r++;
            $c = 1;
            foreach ($columns as $key => $col) {
                if (array_key_exists($key, $totals)) {
                    $sheet->setCellValue([$c, $r], is_numeric($totals[$key]) ? (float) $totals[$key] : $totals[$key]);
                    if (($col['type'] ?? '') === 'money') {
                        $sheet->getStyle([$c, $r])->getNumberFormat()->setFormatCode('"R" #,##0.00');
                    }
                }
                $c++;
            }
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->setBold(true);
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getBorders()->getTop()->setBorderStyle('medium');
        }
        for ($i = 1; $i <= count($columns); $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$r}");
        $sheet->freezePane('A' . ($headerRow + 1));

        $tmp = tempnam(WRITEPATH . 'cache', 'xlsx');
        (new Xlsx($book))->save($tmp);
        $body = (string) file_get_contents($tmp);
        @unlink($tmp);

        return service('response')
            ->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($body);
    }
}
