<?php
http_response_code(502);
$code=502; $title='Bad Gateway'; $message='The server received an invalid response from an upstream server.';
include __DIR__.'/template.php';
