<?php
/**
 * Página de inicio/login
 */

require_once __DIR__ . '/../config.php';

// Redirigir si ya está autenticado
if (isAuthenticated()) {
    header('Location: map.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Login</title>

    <!-- Tailwind CSS y DaisyUI -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />

    <style>
        body {
            background-color: #f0f4f8;
            background-size: cover;
            background-position: center;
        }

        .login-container {
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.7);
        }

        [data-theme="dark"] .login-container {
            background-color: rgba(30, 41, 59, 0.8);
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="login-container rounded-lg shadow-xl p-6 w-full max-w-md">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-primary"><?php echo APP_NAME; ?></h1>
            <p class="text-sm opacity-70">Versión <?php echo APP_VERSION; ?></p>
        </div>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>
                    <?php
                    switch ($_GET['error']) {
                        case 'auth':
                            echo 'Credenciales incorrectas. Inténtalo de nuevo.';
                            break;
                        case 'server':
                            echo 'Error de conexión con el servidor. Inténtalo más tarde.';
                            break;
                        default:
                            echo 'Error desconocido. Inténtalo de nuevo.';
                    }
                    ?>
                </span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="form-control">
                <label class="label">
                    <span class="label-text">Correo electrónico</span>
                </label>
                <input  name="email" placeholder="usuario@ejemplo.com" class="input input-bordered" required value="<?php echo DEFAULT_EMAIL; ?>">
            </div>

            <div class="form-control">
                <label class="label">
                    <span class="label-text">Contraseña</span>
                </label>
                <input type="password" name="password" placeholder="••••••••" class="input input-bordered" required value="<?php echo DEFAULT_PASSWORD; ?>">
            </div>

            <div class="form-control mt-6">
                <button type="submit" class="btn btn-primary">Iniciar sesión</button>
            </div>
        </form>

        <div class="divider my-8">O</div>

        <div class="flex justify-center">
            <label class="swap swap-rotate">
                <input type="checkbox" id="theme-toggle" />
                <svg class="swap-on fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,4.93,6.34Zm12,.29a1,1,0,0,0,.7-.29l.71-.71a1,1,0,1,0-1.41-1.41L17,5.64a1,1,0,0,0,0,1.41A1,1,0,0,0,17.66,7.34ZM21,11H20a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-9,8a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V20A1,1,0,0,0,12,19ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/></svg>
                <svg class="swap-off fill-current w-6 h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z"/></svg>
            </label>
        </div>
    </div>

    <script>
        // Cambiar tema claro/oscuro
        const themeToggle = document.getElementById('theme-toggle');

        // Verificar preferencia guardada
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            themeToggle.checked = true;
        }

        themeToggle.addEventListener('change', function() {
            if (this.checked) {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            }
        });
    </script>
</body>
</html>
