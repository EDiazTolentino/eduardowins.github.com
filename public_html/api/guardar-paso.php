<?php
/**
 * api/guardar-paso.php — Autoguardado del backoffice (§7B).
 *
 * Recibe un JSON con el negocio_id y un subconjunto de campos de
 * contenido (nunca 'estado' ni 'verificado': esos cambios de estado se
 * hacen desde la acción explícita de negocio-editar.php, nunca desde el
 * autoguardado periódico). Requiere sesión de admin y CSRF válido.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!adminAutenticado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sesión expirada. Vuelve a iniciar sesión.']);
    exit;
}

$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Solicitud inválida.']);
    exit;
}

if (!csrfValido($entrada['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Token de seguridad inválido.']);
    exit;
}

$negocioId = (int) ($entrada['negocio_id'] ?? 0);
if ($negocioId < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'negocio_id inválido.']);
    exit;
}

$pdo = BaseDatos::obtener();

// Solo puede autoguardar quien tiene la ficha asignada o un administrador.
$stmt = $pdo->prepare('SELECT admin_asignado_id, editando_por FROM une_negocios WHERE id = :id');
$stmt->execute([':id' => $negocioId]);
$negocio = $stmt->fetch();
if (!$negocio) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Ficha no encontrada.']);
    exit;
}
if ($negocio['editando_por'] && (int) $negocio['editando_por'] !== adminId() && !adminEsAdministrador()) {
    http_response_code(423);
    echo json_encode(['ok' => false, 'error' => 'Otro miembro del equipo está editando esta ficha.']);
    exit;
}

// Campos de contenido permitidos en el autoguardado (whitelist explícita).
// 'estado' y 'verificado' quedan fuera a propósito.
$camposTexto = [
    'nombre_comercial', 'razon_social', 'ruc', 'descripcion',
    'direccion', 'referencia',
    'telefono_publico', 'telefono_publico_2', 'email_publico', 'web',
    'facebook', 'instagram', 'tiktok', 'youtube',
    'contacto_nombre', 'contacto_cargo', 'contacto_telefono', 'contacto_email',
    'afiliacion_federacion', 'notas_internas', 'resultado_contacto',
];
$camposNumericos = [
    'anio_fundacion', 'departamento_id', 'provincia_id', 'distrito_id',
    'latitud', 'longitud', 'rango_precio', 'precio_mensual_ref',
    'capacidad_alumnos', 'alumnos_actuales', 'num_entrenadores',
    'admin_asignado_id', 'intentos_contacto',
];
$camposBooleanos = [
    'tiene_matricula', 'ofrece_beca', 'clase_prueba_gratis', 'local_propio',
    'seguro_accidentes', 'protocolo_salvaguarda', 'personal_certificado',
    'requiere_examen_medico',
];
$camposEnum = [
    'tipo_registro' => ['formativo', 'servicio'],
    'modalidad' => ['presencial', 'virtual', 'mixta'],
    'atiende_genero' => ['mixto', 'femenino', 'masculino'],
];

$set = [];
$params = [':id' => $negocioId];
$campos = $entrada['campos'] ?? [];

foreach ($camposTexto as $campo) {
    if (array_key_exists($campo, $campos)) {
        $set[] = "`$campo` = :$campo";
        $valor = trim((string) $campos[$campo]);
        $params[":$campo"] = $valor === '' ? null : $valor;
    }
}
foreach ($camposNumericos as $campo) {
    if (array_key_exists($campo, $campos)) {
        $set[] = "`$campo` = :$campo";
        $valor = $campos[$campo];
        $params[":$campo"] = ($valor === '' || $valor === null) ? null : $valor;
    }
}
foreach ($camposBooleanos as $campo) {
    if (array_key_exists($campo, $campos)) {
        $set[] = "`$campo` = :$campo";
        $params[":$campo"] = !empty($campos[$campo]) ? 1 : 0;
    }
}
foreach ($camposEnum as $campo => $valoresValidos) {
    if (array_key_exists($campo, $campos) && in_array($campos[$campo], $valoresValidos, true)) {
        $set[] = "`$campo` = :$campo";
        $params[":$campo"] = $campos[$campo];
    }
}

if (empty($set)) {
    echo json_encode(['ok' => true, 'sinCambios' => true]);
    exit;
}

$set[] = 'editando_por = :editando_por';
$set[] = 'editando_desde = NOW()';
$params[':editando_por'] = adminId();

$sql = 'UPDATE une_negocios SET ' . implode(', ', $set) . ' WHERE id = :id';
$pdo->prepare($sql)->execute($params);

$completitud = calcularCompletitud($pdo, $negocioId);

echo json_encode([
    'ok' => true,
    'completitud' => $completitud,
    'guardadoEn' => date('H:i:s'),
]);
