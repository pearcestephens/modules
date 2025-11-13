<?php
// Legacy typo path shim: /modules/consignments/stock-tranasfers/
// Redirect to the canonical location.
// Preserve query string.
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: /modules/consignments/stock-transfers/' . $qs, true, 301);
exit;
