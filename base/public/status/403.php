<?php
http_response_code(403);
$code=403; $title='Forbidden'; $message='You do not have permission to access this resource.';
include __DIR__.'/template.php';
