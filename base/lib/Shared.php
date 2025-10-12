<?php
declare(strict_types=1);

namespace Modules\Base;

/**
 * File: _shared/lib/Shared.php
 * Purpose: Shared constants and base helpers for the Consignments module.
 * Author: Ecigdis CIS Developer Bot
 * Last Modified: 2025-10-11
 * Dependencies: Loaded via Kernel::boot() which includes app.php
 */
/**
 * Shared module constants and helpers entry.
 */
final class Shared
{
    public const VERSION = '1.0.0';

    public static function basePath(): string
    {
        return __DIR__ . '/../..';
    }
}
