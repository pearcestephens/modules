<?php
require_once 'lib/Services/AI/UniversalAIRouter.php';
use CIS\Consignments\Services\AI\UniversalAIRouter;

$ai = new UniversalAIRouter();
$response = $ai->chat("What are the key files in the consignments module?");
print_r($response);
