<?php
require_once "../shared/bootstrap.php";
$_GET["action"] = "get_employee_mapping";
$_SERVER["REQUEST_METHOD"] = "GET";
ob_start();
include "index.php";
$output = ob_get_clean();
echo $output;
?>
