<!DOCTYPE html>
<html lang="es" data-theme="{{ request()->cookie('dm-theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · Gestor Documental</title>
    <script>
        (function () { try { var t = localStorage.getItem('dm-theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {} })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/js/app.js'])
</head>
<body>
<div class="d-flex align-items-center justify-content-center text-center" style="min-height: 100vh; padding: 1.5rem;">
    <div style="max-width: 420px;">
        <div style="font-size: 3.5rem; color: var(--dm-primary);">@yield('icon')</div>
        <div class="mono" style="font-size: 2.5rem; font-weight: 700; letter-spacing: -.02em; margin-top: .5rem;">@yield('code')</div>
        <h1 style="font-size: 1.15rem; font-weight: 600; margin-top: .5rem;">@yield('heading')</h1>
        <p class="text-muted mt-2">@yield('message')</p>
        <a href="{{ url('/') }}" class="btn btn-primary mt-3">
            <i class="fa-solid fa-house me-1"></i> Volver al inicio
        </a>
    </div>
</div>
</body>
</html>
