<?php
/**
 * UNE Sports — POST /api/contacto.php
 * Body JSON: { nombre, email, asunto, mensaje }
 * Guarda el mensaje en la tabla mensajes_contacto.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

une_require_method('POST');

$input = une_json_input();
$nombre = une_str($input, 'nombre', true);
$email = une_str($input, 'email', true);
$asunto = une_str($input, 'asunto');
$mensaje = une_str($input, 'mensaje', true);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    une_send_json(['error' => 'El correo electrónico no es válido.'], 400);
}
if (mb_strlen($mensaje) > 4000) {
    une_send_json(['error' => 'El mensaje es demasiado largo.'], 400);
}

$pdo = une_db();
try {
    $stmt = $pdo->prepare('INSERT INTO mensajes_contacto (nombre, email, asunto, mensaje) VALUES (?, ?, ?, ?)');
    $stmt->execute([$nombre, $email, $asunto, $mensaje]);
} catch (Throwable $e) {
    error_log('UNE Sports - error al guardar mensaje de contacto: ' . $e->getMessage());
    une_send_json(['error' => 'No se pudo enviar el mensaje, intenta nuevamente.'], 500);
}

une_send_json(['ok' => true, 'mensaje' => 'Mensaje recibido, te responderemos pronto.'], 201);
