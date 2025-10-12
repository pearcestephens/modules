<?php
declare(strict_types=1);

// Base dir is modules/consignments
$BASE = __DIR__ . '/..';

function readf(string $p): string {
  $s = @file_get_contents($p);
  if ($s === false) throw new RuntimeException("Missing: $p");
  return $s;
}

@mkdir($BASE . '/assets/js', 0775, true);

// ---- core.bundle.js (order matters: helpers first, then deps)
$core = [];
$core[] = readf($BASE . '/js/core/api.js');
$core[] = readf($BASE . '/js/core/ui.js');
$core[] = readf($BASE . '/js/core/storage.js');
$core[] = readf($BASE . '/js/core/table.js');
$core[] = readf($BASE . '/js/core/validation.js');
$core[] = readf($BASE . '/js/core/scanner.js');
file_put_contents($BASE . '/assets/js/core.bundle.js', implode("\n;\n", $core));

// ---- pack.bundle.js
$pack = [];
$pack[] = "/* expects core.bundle.js loaded first */";
$pack[] = readf($BASE . '/js/pack/table-pack.js');
$pack[] = readf($BASE . '/js/pack/shipping.js');
$pack[] = readf($BASE . '/js/pack/actions-pack.js');
$pack[] = readf($BASE . '/js/pack/products.js');
$pack[] = readf($BASE . '/js/pack/printers.js');
$pack[] = readf($BASE . '/js/pack/init.js');
file_put_contents($BASE . '/assets/js/pack.bundle.js', implode("\n;\n", $pack));

// ---- receive.bundle.js
$recv = [];
$recv[] = "/* expects core.bundle.js loaded first */";
$recv[] = readf($BASE . '/js/receive/table-receive.js');
$recv[] = readf($BASE . '/js/receive/filters.js');
$recv[] = readf($BASE . '/js/receive/confidence.js');
$recv[] = readf($BASE . '/js/receive/actions-receive.js');
$recv[] = readf($BASE . '/js/receive/init.js');
file_put_contents($BASE . '/assets/js/receive.bundle.js', implode("\n;\n", $recv));

echo json_encode(['ok' => true], JSON_UNESCAPED_SLASHES) . PHP_EOL;
