<?php
http_response_code(503);
$code=503; $title='Service Unavailable'; $message='The server is currently unavailable. Please try again later.';
include __DIR__.'/template.php';
