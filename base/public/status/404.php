<?php
http_response_code(404);
$code=404; $title='Not Found'; $message='The requested resource could not be found.';
include __DIR__.'/template.php';
