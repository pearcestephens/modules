<?php
http_response_code(405);
$code=405; $title='Method Not Allowed'; $message='The requested method is not allowed for this resource.';
include __DIR__.'/template.php';
