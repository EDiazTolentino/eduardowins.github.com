<?php
/**
 * UNE Sports — conexión PDO compartida y helpers JSON para la API.
 */
declare(strict_types=1);

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'Falta api/config.php. Copia api/config.sample.php como api/config.php y coloca ahí los datos de tu base de datos.',
    ]);
    exit;
}
require_once $configFile;

function une_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('UNE Sports - error de conexión a la base de datos: ' . $e->getMessage());
            une_send_json(['error' => 'No se pudo conectar a la base de datos. Revisa api/config.php.'], 500);
        }
    }
    return $pdo;
}

function une_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    return is_array($data) ? $data : [];
}

function une_send_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function une_require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        une_send_json(['error' => 'Método no permitido.'], 405);
    }
}

/** Devuelve trim(string) o null si el campo no existe / queda vacío. */
function une_str(array $data, string $key, bool $required = false): ?string
{
    $value = isset($data[$key]) ? trim((string) $data[$key]) : '';
    if ($value === '') {
        if ($required) {
            une_send_json(['error' => "El campo \"$key\" es obligatorio."], 400);
        }
        return null;
    }
    return $value;
}
