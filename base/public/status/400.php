<?php
http_response_code(400);
$code=400; $title='Bad Request'; $message='Your request could not be processed due to malformed syntax or missing parameters.';
include __DIR__.'/template.php';
