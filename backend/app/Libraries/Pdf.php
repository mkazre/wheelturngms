<?php

namespace App\Libraries;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Branded PDF rendering (Dompdf). All PDF views extend pdf/layout which carries the Wheelsturn logo.
 */
class Pdf
{
    public static function logoDataUri(string $file = 'logo-sm.png'): string
    {
        static $cache = [];
        if (!isset($cache[$file])) {
            $path = FCPATH . 'assets/img/' . $file;
            $cache[$file] = is_file($path) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($path)) : '';
        }
        return $cache[$file];
    }

    public static function render(string $view, array $data = [], string $orientation = 'portrait'): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('chroot', [FCPATH, WRITEPATH]);
        $options->set('tempDir', WRITEPATH . 'cache');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data + ['logo' => self::logoDataUri()]));
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font   = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text($orientation === 'portrait' ? 500 : 750, $orientation === 'portrait' ? 815 : 570, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 7, [0.45, 0.47, 0.55]);

        return $dompdf->output();
    }

    /** Send a PDF to the browser. $inline=true opens in a tab, false downloads. */
    public static function respond(string $pdf, string $filename, bool $inline = true)
    {
        return service('response')
            ->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"')
            ->setBody($pdf);
    }
}
