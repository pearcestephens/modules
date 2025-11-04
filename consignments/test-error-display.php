<?php
require_once __DIR__ . '/bootstrap.php';

// This should trigger a pretty error page
throw new Exception("TEST ERROR - If you see this in a nice formatted page, ErrorHandler is working!");
