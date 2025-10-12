<?php
declare(strict_types=1);

namespace Transfers\Lib;

final class Validation
{
    public static function in(array $val, array $allowed): bool
    {
        return in_array($val, $allowed, true);
    }
    public static function positiveInt($v): int
    {
        $x = (int)$v;
        if ($x < 0) throw new \InvalidArgumentException('Expected positive integer');
        return $x;
    }
    public static function nonEmpty(string $s, string $name): string
    {
        $s = trim($s);
        if ($s === '') throw new \InvalidArgumentException("$name is required");
        return $s;
    }
}
