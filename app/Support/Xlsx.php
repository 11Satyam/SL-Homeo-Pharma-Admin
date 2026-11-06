<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/** Minimal single-sheet .xlsx writer, so reports need no extra Composer package on the server. Falls back to CSV without ext-zip. */
final class Xlsx
{
    public static function download(string $name, array $headers, array $rows): Response
    {
        return class_exists(ZipArchive::class) ? self::xlsx($name, $headers, $rows) : self::csv($name, $headers, $rows);
    }

    private static function xlsx(string $name, array $headers, array $rows): Response
    {
        $sheet = self::row($headers).implode('', array_map(self::row(...), $rows));
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $ns = 'http://schemas.openxmlformats.org/';
        $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml';
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $head.'<Types xmlns="'.$ns.'package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="'.$mime.'.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="'.$mime.'.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', $head.'<Relationships xmlns="'.$ns.'package/2006/relationships"><Relationship Id="rId1" Type="'.$ns.'officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $head.'<workbook xmlns="'.$ns.'spreadsheetml/2006/main" xmlns:r="'.$ns.'officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $head.'<Relationships xmlns="'.$ns.'package/2006/relationships"><Relationship Id="rId1" Type="'.$ns.'officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $head.'<worksheet xmlns="'.$ns.'spreadsheetml/2006/main"><sheetData>'.$sheet.'</sheetData></worksheet>');
        $zip->close();

        return response()->download($path, $name.'.xlsx', ['Content-Type' => $mime.'.sheet'])->deleteFileAfterSend();
    }

    private static function row(array $cells): string
    {
        $out = '';
        foreach ($cells as $c) {
            if (is_int($c) || is_float($c)) {
                $out .= '<c><v>'.$c.'</v></c>';
            } else {
                // Inline strings are never evaluated, so a value starting with "=" stays text.
                $text = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $c) ?? '';
                $out .= '<c t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
            }
        }

        return '<row>'.$out.'</row>';
    }

    private static function csv(string $name, array $headers, array $rows): Response
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $r) {
                fputcsv($out, array_map(fn ($c) => is_string($c) && preg_match('/^[=+\-@]/', $c) ? "'".$c : $c, $r));
            }
        }, $name.'.csv');
    }
}
