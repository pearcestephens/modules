<?php
http_response_code(500);
$code=500; $title='Server Error'; $message='An unexpected error occurred on the server.';
include __DIR__.'/template.php';
