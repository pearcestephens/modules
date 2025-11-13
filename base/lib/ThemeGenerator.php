<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * THEME GENERATOR - Color Theory Engine
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Professional color scheme generator using color theory principles
 * Generates harmonious color palettes from any base hue
 *
 * @version 2.0.0
 * @author Ecigdis Limited
 */

class ThemeGenerator {

    /**
     * Generate theme from base hue using color theory
     *
     * @param int $baseHue Hue value (0-360)
     * @param string $scheme Color scheme type
     * @return array Theme colors
     */
    public static function generateTheme(int $baseHue, string $scheme = 'complementary'): array {
        $baseHue = $baseHue % 360;

        switch ($scheme) {
            case 'complementary':
                return self::generateComplementary($baseHue);
            case 'analogous':
                return self::generateAnalogous($baseHue);
            case 'triadic':
                return self::generateTriadic($baseHue);
            case 'split-complementary':
                return self::generateSplitComplementary($baseHue);
            case 'tetradic':
                return self::generateTetradic($baseHue);
            case 'monochromatic':
                return self::generateMonochromatic($baseHue);
            default:
                return self::generateComplementary($baseHue);
        }
    }

    /**
     * Generate complementary color scheme (opposite on color wheel)
     */
    private static function generateComplementary(int $hue): array {
        $complement = ($hue + 180) % 360;

        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'primaryHover' => self::hslToHex($hue, 80, 45),
            'secondary' => self::hslToHex($complement, 70, 50),
            'secondaryHover' => self::hslToHex($complement, 80, 45),
            'accent' => self::hslToHex($hue, 80, 45),
            'success' => '#10b981',
            'successHover' => '#059669',
            'danger' => '#dc2626',
            'dangerHover' => '#b91c1c',
            'warning' => '#d97706',
            'warningHover' => '#b45309',
            'info' => self::hslToHex($complement, 70, 55),
            'infoHover' => self::hslToHex($complement, 80, 50),
            'toastSuccessBg' => '#f0fdf4',
            'toastSuccessBorder' => '#059669',
            'toastSuccessText' => '#065f46',
            'toastErrorBg' => '#fef2f2',
            'toastErrorBorder' => '#dc2626',
            'toastErrorText' => '#991b1b',
            'toastWarningBg' => '#fffbeb',
            'toastWarningBorder' => '#d97706',
            'toastWarningText' => '#92400e',
            'toastInfoBg' => self::hslToHex($complement, 15, 97),
            'toastInfoBorder' => self::hslToHex($complement, 70, 50),
            'toastInfoText' => self::hslToHex($complement, 80, 30),
            'modalHeaderBg' => '#f9fafb',
            'modalBodyBg' => '#ffffff',
            'modalOverlay' => 'rgba(15, 23, 42, 0.75)',
        ];
    }

    /**
     * Generate analogous color scheme (adjacent colors)
     */
    private static function generateAnalogous(int $hue): array {
        $hue2 = ($hue + 30) % 360;
        $hue3 = ($hue - 30 + 360) % 360;

        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'primaryHover' => self::hslToHex($hue, 80, 45),
            'secondary' => self::hslToHex($hue2, 70, 50),
            'secondaryHover' => self::hslToHex($hue2, 80, 45),
            'accent' => self::hslToHex($hue3, 70, 50),
            'success' => '#10b981',
            'successHover' => '#059669',
            'danger' => '#dc2626',
            'dangerHover' => '#b91c1c',
            'warning' => '#d97706',
            'warningHover' => '#b45309',
            'info' => self::hslToHex($hue2, 70, 55),
            'infoHover' => self::hslToHex($hue2, 80, 50),
            'toastSuccessBg' => '#f0fdf4',
            'toastSuccessBorder' => '#059669',
            'toastSuccessText' => '#065f46',
            'toastErrorBg' => '#fef2f2',
            'toastErrorBorder' => '#dc2626',
            'toastErrorText' => '#991b1b',
            'toastWarningBg' => '#fffbeb',
            'toastWarningBorder' => '#d97706',
            'toastWarningText' => '#92400e',
            'toastInfoBg' => self::hslToHex($hue2, 15, 97),
            'toastInfoBorder' => self::hslToHex($hue2, 70, 50),
            'toastInfoText' => self::hslToHex($hue2, 80, 30),
            'modalHeaderBg' => '#f9fafb',
            'modalBodyBg' => '#ffffff',
            'modalOverlay' => 'rgba(15, 23, 42, 0.75)',
        ];
    }

    /**
     * Generate triadic color scheme (120° apart)
     */
    private static function generateTriadic(int $hue): array {
        $hue2 = ($hue + 120) % 360;
        $hue3 = ($hue + 240) % 360;

        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'primaryHover' => self::hslToHex($hue, 80, 45),
            'secondary' => self::hslToHex($hue2, 70, 50),
            'secondaryHover' => self::hslToHex($hue2, 80, 45),
            'accent' => self::hslToHex($hue3, 70, 50),
            'success' => '#10b981',
            'successHover' => '#059669',
            'danger' => '#dc2626',
            'dangerHover' => '#b91c1c',
            'warning' => '#d97706',
            'warningHover' => '#b45309',
            'info' => self::hslToHex($hue3, 70, 55),
            'infoHover' => self::hslToHex($hue3, 80, 50),
            'toastSuccessBg' => '#f0fdf4',
            'toastSuccessBorder' => '#059669',
            'toastSuccessText' => '#065f46',
            'toastErrorBg' => '#fef2f2',
            'toastErrorBorder' => '#dc2626',
            'toastErrorText' => '#991b1b',
            'toastWarningBg' => '#fffbeb',
            'toastWarningBorder' => '#d97706',
            'toastWarningText' => '#92400e',
            'toastInfoBg' => self::hslToHex($hue3, 15, 97),
            'toastInfoBorder' => self::hslToHex($hue3, 70, 50),
            'toastInfoText' => self::hslToHex($hue3, 80, 30),
            'modalHeaderBg' => '#f9fafb',
            'modalBodyBg' => '#ffffff',
            'modalOverlay' => 'rgba(15, 23, 42, 0.75)',
        ];
    }

    /**
     * Generate split-complementary (complement +/- 30°)
     */
    private static function generateSplitComplementary(int $hue): array {
        $complement = ($hue + 180) % 360;
        $split1 = ($complement + 30) % 360;
        $split2 = ($complement - 30 + 360) % 360;

        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'primaryHover' => self::hslToHex($hue, 80, 45),
            'secondary' => self::hslToHex($split1, 70, 50),
            'secondaryHover' => self::hslToHex($split1, 80, 45),
            'accent' => self::hslToHex($split2, 70, 50),
            'success' => '#10b981',
            'successHover' => '#059669',
            'danger' => '#dc2626',
            'dangerHover' => '#b91c1c',
            'warning' => '#d97706',
            'warningHover' => '#b45309',
            'info' => self::hslToHex($split1, 70, 55),
            'infoHover' => self::hslToHex($split1, 80, 50),
            'toastSuccessBg' => '#f0fdf4',
            'toastSuccessBorder' => '#059669',
            'toastSuccessText' => '#065f46',
            'toastErrorBg' => '#fef2f2',
            'toastErrorBorder' => '#dc2626',
            'toastErrorText' => '#991b1b',
            'toastWarningBg' => '#fffbeb',
            'toastWarningBorder' => '#d97706',
            'toastWarningText' => '#92400e',
            'toastInfoBg' => self::hslToHex($split1, 15, 97),
            'toastInfoBorder' => self::hslToHex($split1, 70, 50),
            'toastInfoText' => self::hslToHex($split1, 80, 30),
            'modalHeaderBg' => '#f9fafb',
            'modalBodyBg' => '#ffffff',
            'modalOverlay' => 'rgba(15, 23, 42, 0.75)',
        ];
    }

    /**
     * Generate tetradic color scheme (90° apart)
     */
    private static function generateTetradic(int $hue): array {
        $hue2 = ($hue + 90) % 360;
        $hue3 = ($hue + 180) % 360;
        $hue4 = ($hue + 270) % 360;

        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'primaryHover' => self::hslToHex($hue, 80, 45),
            'secondary' => self::hslToHex($hue2, 70, 50),
            'secondaryHover' => self::hslToHex($hue2, 80, 45),
            'accent' => self::hslToHex($hue3, 70, 50),
            'success' => '#10b981',
            'successHover' => '#059669',
            'danger' => '#dc2626',
            'dangerHover' => '#b91c1c',
            'warning' => '#d97706',
            'warningHover' => '#b45309',
            'info' => self::hslToHex($hue4, 70, 55),
            'infoHover' => self::hslToHex($hue4, 80, 50),
            'toastSuccessBg' => '#f0fdf4',
            'toastSuccessBorder' => '#059669',
            'toastSuccessText' => '#065f46',
            'toastErrorBg' => '#fef2f2',
            'toastErrorBorder' => '#dc2626',
            'toastErrorText' => '#991b1b',
            'toastWarningBg' => '#fffbeb',
            'toastWarningBorder' => '#d97706',
            'toastWarningText' => '#92400e',
            'toastInfoBg' => self::hslToHex($hue4, 15, 97),
            'toastInfoBorder' => self::hslToHex($hue4, 70, 50),
            'toastInfoText' => self::hslToHex($hue4, 80, 30),
            'modalHeaderBg' => '#f9fafb',
            'modalBodyBg' => '#ffffff',
            'modalOverlay' => 'rgba(15, 23, 42, 0.75)',
        ];
    }

    /**
     * Generate monochromatic scheme (same hue, different saturation/lightness)
     */
    private static function generateMonochromatic(int $hue): array {
        return [
            'primary' => self::hslToHex($hue, 70, 50),
            'primaryHover' => self::hslToHex($hue, 80, 45),
            'secondary' => self::hslToHex($hue, 50, 60),
            'secondaryHover' => self::hslToHex($hue, 60, 55),
            'accent' => self::hslToHex($hue, 80, 40),
            'success' => '#10b981',
            'successHover' => '#059669',
            'danger' => '#dc2626',
            'dangerHover' => '#b91c1c',
            'warning' => '#d97706',
            'warningHover' => '#b45309',
            'info' => self::hslToHex($hue, 60, 60),
            'infoHover' => self::hslToHex($hue, 70, 55),
            'toastSuccessBg' => '#f0fdf4',
            'toastSuccessBorder' => '#059669',
            'toastSuccessText' => '#065f46',
            'toastErrorBg' => '#fef2f2',
            'toastErrorBorder' => '#dc2626',
            'toastErrorText' => '#991b1b',
            'toastWarningBg' => '#fffbeb',
            'toastWarningBorder' => '#d97706',
            'toastWarningText' => '#92400e',
            'toastInfoBg' => self::hslToHex($hue, 15, 97),
            'toastInfoBorder' => self::hslToHex($hue, 70, 50),
            'toastInfoText' => self::hslToHex($hue, 80, 30),
            'modalHeaderBg' => '#f9fafb',
            'modalBodyBg' => '#ffffff',
            'modalOverlay' => 'rgba(15, 23, 42, 0.75)',
        ];
    }

    /**
     * Convert HSL to HEX
     */
    private static function hslToHex(int $h, int $s, int $l): string {
        $h = $h / 360;
        $s = $s / 100;
        $l = $l / 100;

        if ($s == 0) {
            $r = $g = $b = $l;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $r = self::hueToRgb($p, $q, $h + 1/3);
            $g = self::hueToRgb($p, $q, $h);
            $b = self::hueToRgb($p, $q, $h - 1/3);
        }

        return sprintf("#%02x%02x%02x", round($r * 255), round($g * 255), round($b * 255));
    }

    /**
     * Helper for HSL to RGB conversion
     */
    private static function hueToRgb($p, $q, $t) {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
        return $p;
    }

    /**
     * Generate CSS variables from theme
     */
    public static function generateCSS(array $colors): string {
        $css = ":root {\n";
        foreach ($colors as $key => $value) {
            $cssVar = '--vu-' . self::camelToKebab($key);
            $css .= "    $cssVar: $value;\n";
        }
        $css .= "}\n";
        return $css;
    }

    /**
     * Convert camelCase to kebab-case
     */
    private static function camelToKebab($string): string {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $string));
    }
}
