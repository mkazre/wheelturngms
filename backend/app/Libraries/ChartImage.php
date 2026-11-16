<?php

namespace App\Libraries;

/**
 * Renders simple, brand-styled PNG charts with GD so they can be embedded in emails
 * (email clients cannot run JavaScript chart libraries). Drawn at 2x and downsampled for smooth edges.
 */
class ChartImage
{
    public const PALETTE = ['#9b1631', '#1c205c', '#ef1c25', '#525bb5', '#f59e0b', '#10b981', '#8b5cf6', '#06b6d4', '#64748b', '#ec4899'];

    private static function font(bool $bold = false): string
    {
        return ROOTPATH . 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans' . ($bold ? '-Bold' : '') . '.ttf';
    }

    private static function color($img, string $hex, int $alpha = 0)
    {
        $hex = ltrim($hex, '#');
        return imagecolorallocatealpha($img, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha);
    }

    private static function finish($big, int $w, int $h): string
    {
        $out = imagecreatetruecolor($w, $h);
        imagecopyresampled($out, $big, 0, 0, 0, 0, $w, $h, $w * 2, $h * 2);
        ob_start();
        imagepng($out, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($big);
        imagedestroy($out);
        return $png;
    }

    /** Doughnut chart with legend. $data = [['label' => .., 'value' => ..], ...] */
    public static function doughnut(array $data, int $w = 520, int $h = 240): string
    {
        $s = 2;
        $img = imagecreatetruecolor($w * $s, $h * $s);
        imagefill($img, 0, 0, self::color($img, '#ffffff'));
        $total = array_sum(array_column($data, 'value'));
        $cx = 120 * $s;
        $cy = (int) ($h * $s / 2);
        $d = 200 * $s;
        if ($total <= 0) {
            imagefilledellipse($img, $cx, $cy, $d, $d, self::color($img, '#e2e8f0'));
        } else {
            $start = -90.0;
            foreach (array_values($data) as $i => $row) {
                $sweep = $row['value'] / $total * 360;
                if ($sweep <= 0) {
                    continue;
                }
                imagefilledarc($img, $cx, $cy, $d, $d, (int) round($start), (int) round($start + $sweep), self::color($img, self::PALETTE[$i % 10]), IMG_ARC_PIE);
                $start += $sweep;
            }
        }
        imagefilledellipse($img, $cx, $cy, (int) ($d * 0.58), (int) ($d * 0.58), self::color($img, '#ffffff'));
        $label = 'R' . number_format($total / 1000, 1) . 'k';
        $box = imagettfbbox(15 * $s, 0, self::font(true), $label);
        imagettftext($img, 15 * $s, 0, $cx - (int) (($box[2] - $box[0]) / 2), $cy + 8 * $s, self::color($img, '#0b0d2e'), self::font(true), $label);

        $y = max(30 * $s, $cy - count($data) * 13 * $s);
        foreach (array_values($data) as $i => $row) {
            if ($i > 7) {
                break;
            }
            $pct = $total > 0 ? round($row['value'] / $total * 100) : 0;
            imagefilledrectangle($img, 250 * $s, $y - 9 * $s, 261 * $s, $y + 2 * $s, self::color($img, self::PALETTE[$i % 10]));
            imagettftext($img, 10 * $s, 0, 270 * $s, $y, self::color($img, '#334155'), self::font(), mb_strimwidth($row['label'], 0, 22, '…'));
            imagettftext($img, 10 * $s, 0, 430 * $s, $y, self::color($img, '#0b0d2e'), self::font(true), $pct . '%');
            $y += 26 * $s;
        }
        return self::finish($img, $w, $h);
    }

    /** Vertical bar chart. $labels = [...], $values = [...] */
    public static function bars(array $labels, array $values, int $w = 540, int $h = 220, string $color = '#9b1631'): string
    {
        $s = 2;
        $img = imagecreatetruecolor($w * $s, $h * $s);
        imagefill($img, 0, 0, self::color($img, '#ffffff'));
        $max = max(1, max($values ?: [1]));
        $left = 50 * $s;
        $bottom = ($h - 28) * $s;
        $top = 14 * $s;
        $plotH = $bottom - $top;
        $grid = self::color($img, '#e2e8f0');
        $muted = self::color($img, '#64748b');
        for ($g = 0; $g <= 4; $g++) {
            $gy = (int) ($bottom - $plotH * $g / 4);
            imageline($img, $left, $gy, $w * $s - 10 * $s, $gy, $grid);
            $v = $max * $g / 4;
            imagettftext($img, 8 * $s, 0, 4 * $s, $gy + 4 * $s, $muted, self::font(), 'R' . ($v >= 1000 ? round($v / 1000, 1) . 'k' : round($v)));
        }
        $n = max(1, count($values));
        $slot = ($w * $s - $left - 10 * $s) / $n;
        $bw = min(46 * $s, $slot * 0.62);
        $bar = self::color($img, $color);
        foreach (array_values($values) as $i => $v) {
            $x = (int) ($left + $slot * $i + ($slot - $bw) / 2);
            $bh = (int) ($plotH * $v / $max);
            if ($bh > 0) {
                imagefilledrectangle($img, $x, $bottom - $bh, (int) ($x + $bw), $bottom, $bar);
            }
            $lbl = (string) ($labels[$i] ?? '');
            $box = imagettfbbox(8 * $s, 0, self::font(), $lbl);
            imagettftext($img, 8 * $s, 0, (int) ($x + $bw / 2 - ($box[2] - $box[0]) / 2), $bottom + 16 * $s, $muted, self::font(), $lbl);
        }
        return self::finish($img, $w, $h);
    }
}
