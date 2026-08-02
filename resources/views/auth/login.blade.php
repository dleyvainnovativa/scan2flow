<!DOCTYPE html>
<html lang="es" data-theme="{{ request()->cookie('dm-theme', 'light') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Iniciar sesión · Gestor Documental</title>

    <script>
        (function () {
            try { var t = localStorage.getItem('dm-theme'); if (t) document.documentElement.setAttribute('data-theme', t); } catch (e) {}
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    @vite(['resources/js/app.js'])
</head>
<body>
<div class="d-flex align-items-center justify-content-center" style="min-height: 100vh; padding: 1.5rem;">
    <div class="dm-card" style="width: 100%; max-width: 400px;">
        <div class="dm-card__body p-4">
            <div class="text-center mb-4">
                <i class="fa-solid fa-folder-tree" style="font-size: 2rem; color: var(--dm-primary);"></i>
                <h1 style="font-size: 1.25rem; font-weight: 700; margin-top: .75rem;">Gestor Documental</h1>
                <p class="text-muted small mb-0">Inicia sesión para continuar</p>
            </div>

            <div id="login-error" class="alert alert-danger d-none" role="alert"></div>

            <div class="mb-3">
                <label for="email" class="form-label">Correo electrónico</label>
                <input type="email" id="email" class="form-control" autocomplete="username" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <input type="password" id="password" class="form-control" autocomplete="current-password" required>
            </div>

            <button id="login-btn" class="btn btn-primary w-100">
                <i class="fa-solid fa-right-to-bracket me-1"></i> Entrar
            </button>
        </div>
    </div>
</div>

{{-- Firebase JS SDK (modular, via CDN) + login logic --}}
<script type="module">
    import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-app.js';
    import { getAuth, signInWithEmailAndPassword, setPersistence, browserSessionPersistence }
        from 'https://www.gstatic.com/firebasejs/10.12.2/firebase-auth.js';

    const firebaseConfig = @json(config('firebase.web'));
    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);

    const btn = document.getElementById('login-btn');
    const errBox = document.getElementById('login-error');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    function showError(msg) {
        errBox.textContent = msg;
        errBox.classList.remove('d-none');
    }

    async function doLogin() {
        errBox.classList.add('d-none');
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        if (!email || !password) { showError('Ingresa correo y contraseña.'); return; }

        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="dm-spinner"></span>';

        try {
            await setPersistence(auth, browserSessionPersistence);
            const cred = await signInWithEmailAndPassword(auth, email, password);
            const idToken = await cred.user.getIdToken();

            // Hand the token to Laravel to open a server session.
            const res = await fetch('{{ route('login.store') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ id_token: idToken }),
            });
            const body = await res.json();

            if (!res.ok) { showError(body.message || 'No se pudo iniciar sesión.'); return; }
            window.location.href = body.redirect;
        } catch (e) {
            const map = {
                'auth/invalid-credential': 'Correo o contraseña incorrectos.',
                'auth/user-not-found': 'Correo o contraseña incorrectos.',
                'auth/wrong-password': 'Correo o contraseña incorrectos.',
                'auth/too-many-requests': 'Demasiados intentos. Intenta más tarde.',
                'auth/user-disabled': 'Tu cuenta está desactivada.',
            };
            showError(map[e.code] || 'No se pudo iniciar sesión.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }

    btn.addEventListener('click', doLogin);
    document.getElementById('password').addEventListener('keydown', (e) => { if (e.key === 'Enter') doLogin(); });
</script>
</body>
</html>
