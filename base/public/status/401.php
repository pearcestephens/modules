<?php
http_response_code(401);
$code=401; $title='Unauthorized'; $message='Authentication is required to access this resource.';
include __DIR__.'/template.php';
