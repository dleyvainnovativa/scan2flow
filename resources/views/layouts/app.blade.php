<!DOCTYPE html>
<html lang="es" data-theme="{{ request()->cookie('dm-theme', 'light') }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Gestor Documental')</title>

    {{-- Apply persisted theme before paint to avoid a flash of the wrong theme --}}
    <script>
        (function() {
            try {
                var t = localStorage.getItem('dm-theme');
                if (t) document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>

    {{-- DM ready-stub: defines window.onDM BEFORE any inline @push('scripts')
         runs. app.js (loaded by Vite as a deferred module) executes later and
         calls window.__dmReady() to flush queued callbacks. This guarantees
         inline scripts can safely do window.onDM(({ toast }) => { ... }). --}}
    <script>
        (function() {
            var queue = [];
            window.onDM = function(cb) {
                if (window.DM) {
                    cb(window.DM);
                } // already loaded → run now
                else {
                    queue.push(cb);
                } // not yet → queue
            };
            window.__dmReady = function(DM) {
                window.onDM = function(cb) {
                    cb(DM);
                }; // switch to immediate mode
                queue.forEach(function(cb) {
                    try {
                        cb(DM);
                    } catch (e) {
                        console.error(e);
                    }
                });
                queue = [];
            };
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/css/theme.css','resources/js/app.js'])
    @stack('head')
</head>

<body @hasSection('page') data-page="@yield('page')" @endif>
    <a href="#dm-main-content" class="dm-skip-link">Saltar al contenido</a>

    <div class="dm-app">
        @include('partials.sidebar')
        <div class="dm-sidebar-backdrop"></div>

        <div class="dm-main">
            @include('partials.topbar')
            <main id="dm-main-content" class="dm-content" tabindex="-1">
                @yield('content')
            </main>
        </div>
    </div>

    @includeIf('partials.flash')
    @stack('scripts')
</body>

</html>