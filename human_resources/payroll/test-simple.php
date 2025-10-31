<?php
echo "SUCCESS! PHP is working in payroll directory!";
echo "<br>Current file: " . __FILE__;
echo "<br>Directory: " . __DIR__;
echo "<br>Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'not set');
echo "<br>Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'not set');
