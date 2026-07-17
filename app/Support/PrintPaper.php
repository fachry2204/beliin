<?php

namespace App\Support;

use App\Models\CompanySetting;

class PrintPaper
{
    public static function cssSize(?CompanySetting $setting): string
    {
        $paper = $setting?->printer_paper_size ?: 'a5';
        $orientation = $setting?->printer_orientation ?: 'portrait';

        $named = [
            'a4' => 'A4',
            'a5' => 'A5',
            'letter' => 'Letter',
            'legal' => 'Legal',
        ];

        if (isset($named[$paper])) {
            return $named[$paper].' '.$orientation;
        }

        [$width, $height] = self::dimensions($paper);
        if ($orientation === 'landscape') {
            [$width, $height] = [$height, $width];
        }

        return $width.'mm '.$height.'mm';
    }

    public static function cssMargin(?CompanySetting $setting): string
    {
        if (in_array($setting?->printer_paper_size, ['thermal_80', 'thermal_58'], true)) {
            return '3mm';
        }

        return $setting?->printer_type === 'dot_matrix' ? '4mm 7mm 7mm' : '8mm';
    }

    public static function dompdfPaper(?CompanySetting $setting): string|array
    {
        $paper = $setting?->printer_paper_size ?: 'a5';
        if (in_array($paper, ['a4', 'a5', 'letter', 'legal'], true)) {
            return $paper;
        }

        [$width, $height] = self::dimensions($paper);
        if (($setting?->printer_orientation ?: 'portrait') === 'landscape') {
            [$width, $height] = [$height, $width];
        }

        $pointsPerMillimeter = 72 / 25.4;

        return [0, 0, $width * $pointsPerMillimeter, $height * $pointsPerMillimeter];
    }

    public static function dompdfOrientation(?CompanySetting $setting): string
    {
        return in_array($setting?->printer_paper_size, ['continuous_9_5x11', 'thermal_80', 'thermal_58'], true)
            ? 'portrait'
            : ($setting?->printer_orientation ?: 'portrait');
    }

    private static function dimensions(string $paper): array
    {
        return match ($paper) {
            'continuous_9_5x11' => [241.3, 279.4],
            'thermal_80' => [80, 297],
            'thermal_58' => [58, 297],
            default => [148, 210],
        };
    }
}
