<?php
require_once __DIR__ . '/config.php';

function safeRedirect(string $redirect, string $fallback = '/index.php'): string {
    $redirect = trim($redirect);
    if ($redirect === '') {
        return $fallback;
    }

    $parts = parse_url($redirect);
    if ($parts === false) {
        return $fallback;
    }

    if (isset($parts['scheme'], $parts['host']) || str_starts_with($redirect, '//')) {
        return $fallback;
    }

    if (!str_starts_with($redirect, '/')) {
        return $fallback;
    }

    return $redirect;
}

// Already logged in
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error    = '';
$email    = '';
$redirect = $_GET['r'] ?? '/index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = safeRedirect($_POST['redirect'] ?? '/index.php');

    if (!$email || !$password) {
        $error = 'Completá todos los campos.';
    } else {
        try {
            $pdo  = getDB();
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario']    = [
                    'id'     => $user['id'],
                    'nombre' => $user['nombre'],
                    'email'  => $user['email'],
                    'rol'    => $user['rol'],
                ];
                // Update last_login
                $pdo->prepare("UPDATE usuarios SET last_login = NOW() WHERE id = ?")
                    ->execute([$user['id']]);

                header('Location: ' . $redirect);
                exit;
            } else {
                $error = 'Email o contraseña incorrectos.';
            }
        } catch (Exception $e) {
            $error = 'Error al conectar con la base de datos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — SmartAdmin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:       #080b10;
            --surface:  #0e1320;
            --card:     #121929;
            --border:   #1e2d45;
            --bl:       #253450;
            --accent:   #2563eb;
            --al:       #3b7ff5;
            --aglow:    rgba(37,99,235,0.2);
            --text:     #e8edf5;
            --muted:    #4a5f7a;
            --sub:      #7a90b0;
            --green:    #10b981;
            --red:      #ef4444;
            --r:        8px;
            --rl:       12px;
            --tr:       0.18s cubic-bezier(0.4,0,0.2,1);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            font-family: 'Syne', sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
        }

        /* Background pattern */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(37,99,235,0.12), transparent),
                linear-gradient(rgba(30,45,69,0.25) 1px, transparent 1px),
                linear-gradient(90deg, rgba(30,45,69,0.25) 1px, transparent 1px);
            background-size: 100% 100%, 40px 40px, 40px 40px;
            pointer-events: none;
            z-index: 0;
        }

        .login-wrap {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: var(--card);
            border: 1px solid var(--bl);
            border-radius: 16px;
            padding: 44px 40px;
            box-shadow: 0 32px 80px rgba(0,0,0,0.5), 0 0 0 1px rgba(37,99,235,0.08);
            animation: fadeUp 0.4s cubic-bezier(0.34,1.2,0.64,1);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .login-brand {
            text-align: center;
            margin-bottom: 36px;
        }

        .login-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px; height: 56px;
            background: linear-gradient(135deg, var(--accent), var(--al));
            border-radius: 14px;
            font-size: 28px;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px var(--aglow);
        }

        .login-brand-name {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--text);
        }

        .login-brand-sub {
            font-size: 12px;
            color: var(--sub);
            font-family: 'Space Mono', monospace;
            margin-top: 4px;
            letter-spacing: 0.04em;
        }

        .login-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 6px;
        }

        .login-sub {
            font-size: 13px;
            color: var(--sub);
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 7px;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 15px;
            color: var(--muted);
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--r);
            color: var(--text);
            padding: 11px 13px 11px 40px;
            font-family: 'Syne', sans-serif;
            font-size: 14px;
            transition: border-color var(--tr), box-shadow var(--tr);
            outline: none;
        }

        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--aglow);
        }

        .form-control::placeholder { color: var(--muted); }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 15px;
            padding: 4px;
            transition: color var(--tr);
        }

        .password-toggle:hover { color: var(--sub); }

        .error-msg {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            border-left: 3px solid var(--red);
            border-radius: var(--r);
            padding: 11px 14px;
            font-size: 13px;
            color: var(--red);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: shake 0.3s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25%       { transform: translateX(-6px); }
            75%       { transform: translateX(6px); }
        }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--r);
            font-family: 'Syne', sans-serif;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all var(--tr);
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            letter-spacing: 0.02em;
        }

        .btn-login:hover:not(:disabled) {
            background: var(--al);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px var(--aglow);
        }

        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .spinner {
            width: 17px; height: 17px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            display: none;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .login-footer {
            text-align: center;
            margin-top: 32px;
            font-size: 11px;
            color: var(--muted);
            font-family: 'Space Mono', monospace;
        }

        @media (max-width: 480px) {
            .login-card { padding: 32px 24px; }
        }
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">

        <div class="login-brand">
            <div class="login-logo">⬡</div>
            <div class="login-brand-name">SmartAdmin</div>
            <div class="login-brand-sub">Gestión Empresarial</div>
        </div>

        <div class="login-title">Bienvenido</div>
        <div class="login-sub">Ingresá tus credenciales para continuar</div>

        <?php if ($error): ?>
        <div class="error-msg">
            <span>✕</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" id="login-form">
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <div class="input-wrap">
                    <span class="input-icon">@</span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="usuario@empresa.com"
                        value="<?= htmlspecialchars($email) ?>"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Contraseña</label>
                <div class="input-wrap">
                    <span class="input-icon">🔒</span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="••••••••••"
                        required
                    >
                    <button type="button" class="password-toggle" id="toggle-pass" aria-label="Mostrar contraseña">👁</button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="btn-submit">
                <span id="btn-text">Ingresar</span>
                <div class="spinner" id="btn-spinner"></div>
            </button>
        </form>

        <div class="login-footer">
            © <?= date('Y') ?> SmartAdmin — Sistema privado
        </div>
    </div>
</div>

<script>
document.getElementById('toggle-pass').addEventListener('click', function() {
    const inp = document.getElementById('password');
    const isPass = inp.type === 'password';
    inp.type = isPass ? 'text' : 'password';
    this.textContent = isPass ? '🙈' : '👁';
});

document.getElementById('login-form').addEventListener('submit', function() {
    const btn = document.getElementById('btn-submit');
    btn.disabled = true;
    document.getElementById('btn-text').textContent = 'Ingresando...';
    document.getElementById('btn-spinner').style.display = 'block';
});
</script>
</body>
</html>
