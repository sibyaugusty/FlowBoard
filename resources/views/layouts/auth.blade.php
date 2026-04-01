<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Login') — FlowBoard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('css/flowboard.css') }}" rel="stylesheet">
</head>
<body class="fb-auth-body">
    <div class="fb-auth-container">
        <div class="fb-auth-card">
            <div class="fb-auth-header text-center">
                <div class="fb-auth-logo">
                    <i class="bi bi-kanban"></i>
                </div>
                <h1 class="fb-auth-title">FlowBoard</h1>
                <p class="fb-auth-subtitle">@yield('subtitle', 'Manage your projects with ease')</p>
            </div>

            {{-- Flash Messages --}}
            @if (session('success'))
                <div class="fb-flash fb-flash-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="fb-flash fb-flash-error">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
