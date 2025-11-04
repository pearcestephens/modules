<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ $_SESSION['csrf_token'] ?? '' }}">

    <title>@yield('title', 'CIS - Central Information System')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">

    <!-- Bootstrap 5.3.2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6.7.1 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">

    <!-- CIS Core CSS -->
    <link rel="stylesheet" href="/modules/base/public/assets/css/core.css">

    @yield('styles')

    <style>
        :root {
            --cis-primary: #8B5CF6;
            --cis-secondary: #6366F1;
            --cis-success: #10B981;
            --cis-danger: #EF4444;
            --cis-warning: #F59E0B;
            --cis-info: #3B82F6;
            --cis-dark: #1F2937;
            --cis-light: #F3F4F6;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #F9FAFB;
            color: #111827;
        }
    </style>
</head>
<body class="@yield('body-class')">

    @yield('content')

    <!-- jQuery 3.7.1 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap 5.3.2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- CIS Core JS -->
    <script src="/modules/base/public/assets/js/core.js"></script>

    @yield('scripts')

</body>
</html>
