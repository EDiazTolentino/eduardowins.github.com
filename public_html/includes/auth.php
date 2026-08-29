<?php
/**
 * auth.php — Sesión de administrador, CSRF y control de acceso por rol.
 * Debe incluirse después de config/database.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfCampo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfValido(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfExigirOMorir(): void
{
    if (!csrfValido($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        die('Token de seguridad inválido o expirado. Recarga la página e inténtalo de nuevo.');
    }
}

// ---------------------------------------------------------------------
// Mensajes flash (para mostrar avisos después de un redirect POST/GET)
// ---------------------------------------------------------------------

function flashAgregar(string $tipo, string $texto): void
{
    $_SESSION['flash'][$tipo][] = $texto;
}

/** Devuelve los mensajes flash de un tipo y los limpia de la sesión. */
function flashObtener(string $tipo): array
{
    $items = $_SESSION['flash'][$tipo] ?? [];
    unset($_SESSION['flash'][$tipo]);
    return $items;
}

// ---------------------------------------------------------------------
// Sesión de administrador
// ---------------------------------------------------------------------

const MAX_INTENTOS_LOGIN = 5;
const MINUTOS_BLOQUEO_LOGIN = 15;

/**
 * Intenta autenticar a un usuario del backoffice.
 * @return array{ok:bool,mensaje?:string}
 */
function intentarLoginAdmin(PDO $pdo, string $usuario, string $password): array
{
    $stmt = $pdo->prepare('SELECT * FROM une_admins WHERE usuario = :usuario AND activo = 1');
    $stmt->execute([':usuario' => $usuario]);
    $admin = $stmt->fetch();

    if (!$admin) {
        return ['ok' => false, 'mensaje' => 'Usuario o contraseña incorrectos.'];
    }

    if ($admin['bloqueado_hasta'] && strtotime($admin['bloqueado_hasta']) > time()) {
        $minutos = (int) ceil((strtotime($admin['bloqueado_hasta']) - time()) / 60);
        return ['ok' => false, 'mensaje' => "Cuenta bloqueada temporalmente. Intenta de nuevo en {$minutos} minuto(s)."];
    }

    if (!password_verify($password, $admin['password_hash'])) {
        $intentos = (int) $admin['intentos_fallidos'] + 1;
        $bloqueadoHasta = null;
        if ($intentos >= MAX_INTENTOS_LOGIN) {
            $bloqueadoHasta = date('Y-m-d H:i:s', time() + MINUTOS_BLOQUEO_LOGIN * 60);
            $intentos = 0;
        }
        $pdo->prepare('UPDATE une_admins SET intentos_fallidos = :i, bloqueado_hasta = :b WHERE id = :id')
            ->execute([':i' => $intentos, ':b' => $bloqueadoHasta, ':id' => $admin['id']]);

        return ['ok' => false, 'mensaje' => 'Usuario o contraseña incorrectos.'];
    }

    $pdo->prepare('UPDATE une_admins SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW() WHERE id = :id')
        ->execute([':id' => $admin['id']]);

    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_rol'] = $admin['rol'];
    $_SESSION['admin_nombre'] = $admin['nombre'];

    return ['ok' => true];
}

function cerrarSesionAdmin(): void
{
    $_SESSION = [];
    session_destroy();
}

function adminAutenticado(): bool
{
    return !empty($_SESSION['admin_id']);
}

function adminId(): ?int
{
    return $_SESSION['admin_id'] ?? null;
}

function adminRol(): ?string
{
    return $_SESSION['admin_rol'] ?? null;
}

function adminEsAdministrador(): bool
{
    return adminRol() === 'administrador';
}

/** Corta la ejecución y redirige al login si no hay sesión de admin activa. */
function exigirLoginAdmin(): void
{
    if (!adminAutenticado()) {
        header('Location: /admin/index.php');
        exit;
    }
}

/** Corta la ejecución con 403 si el admin actual no es 'administrador'. */
function exigirRolAdministrador(): void
{
    if (!adminEsAdministrador()) {
        http_response_code(403);
        die('Esta acción solo puede realizarla un administrador.');
    }
}
