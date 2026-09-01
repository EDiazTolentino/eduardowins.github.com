<?php
/**
 * functions.php — Helpers generales: sanitización, slugs, teléfonos,
 * imágenes y cálculo de completitud de una ficha.
 */

/** Escapa un valor para salida segura en HTML. */
function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

/** Convierte un texto en slug URL-friendly (minúsculas, sin tildes, con guiones). */
function generarSlugBase(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $mapa = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];
    $texto = strtr($texto, $mapa);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    return trim($texto, '-') ?: 'negocio';
}

/** Genera un slug único para une_negocios, agregando -2, -3... si hace falta. */
function generarSlugUnico(PDO $pdo, string $nombre, ?int $idExcluir = null): string
{
    $base = generarSlugBase($nombre);
    $slug = $base;
    $sufijo = 2;

    $sql = 'SELECT COUNT(*) FROM une_negocios WHERE slug = :slug';
    if ($idExcluir !== null) {
        $sql .= ' AND id != :id';
    }
    $stmt = $pdo->prepare($sql);

    while (true) {
        $params = [':slug' => $slug];
        if ($idExcluir !== null) {
            $params[':id'] = $idExcluir;
        }
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $sufijo;
        $sufijo++;
    }
}

/**
 * Normaliza un teléfono peruano: quita espacios, guiones, paréntesis y el
 * prefijo +51/051. Devuelve solo dígitos, o null si queda vacío.
 */
function normalizarTelefono(string $telefono): ?string
{
    $limpio = preg_replace('/[^0-9]/', '', $telefono);
    if ($limpio === '') {
        return null;
    }
    if (strlen($limpio) > 9 && str_starts_with($limpio, '51')) {
        $limpio = substr($limpio, 2);
    }
    return $limpio;
}

/**
 * Valida un teléfono peruano ya normalizado: celular de 9 dígitos
 * (empieza con 9) o fijo con código de área (6-9 dígitos).
 */
function validarTelefonoPeru(string $telefonoNormalizado): bool
{
    if (preg_match('/^9[0-9]{8}$/', $telefonoNormalizado)) {
        return true; // celular
    }
    if (preg_match('/^[0-9]{6,9}$/', $telefonoNormalizado)) {
        return true; // fijo con código de área
    }
    return false;
}

/** Valida un RUC peruano de 11 dígitos, incluyendo el dígito verificador. */
function validarRuc(string $ruc): bool
{
    if (!preg_match('/^[0-9]{11}$/', $ruc)) {
        return false;
    }
    $factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    for ($i = 0; $i < 10; $i++) {
        $suma += (int) $ruc[$i] * $factores[$i];
    }
    $resto = $suma % 11;
    $digitoEsperado = 11 - $resto;
    if ($digitoEsperado === 10) {
        $digitoEsperado = 0;
    } elseif ($digitoEsperado === 11) {
        $digitoEsperado = 1;
    }
    return $digitoEsperado === (int) $ruc[10];
}

/** Devuelve la IP del visitante en formato binario para VARBINARY(16), o null. */
function ipBinariaActual(): ?string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if (!$ip) {
        return null;
    }
    $bin = @inet_pton($ip);
    return $bin !== false ? $bin : null;
}

/** Registra un evento de analítica interna en une_eventos. */
function registrarEvento(PDO $pdo, string $tipo, ?int $negocioId = null, array $metadata = []): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO une_eventos (tipo, negocio_id, metadata) VALUES (:tipo, :negocio_id, :metadata)'
    );
    $stmt->execute([
        ':tipo' => $tipo,
        ':negocio_id' => $negocioId,
        ':metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

/**
 * Sube y redimensiona una imagen (logo o foto de galería) con GD.
 * Valida el MIME real con finfo (nunca la extensión), la renombra con un
 * nombre aleatorio y la guarda en $subdirectorio dentro de uploads/.
 *
 * @return array{ok:bool,archivo?:string,error?:string}
 */
function subirImagen(array $archivo, string $subdirectorio, int $anchoMaximo = 1200, int $pesoMaximoMb = 3): array
{
    if (!isset($archivo['tmp_name']) || !is_uploaded_file($archivo['tmp_name'])) {
        return ['ok' => false, 'error' => 'No se recibió ningún archivo.'];
    }
    if (($archivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Error al subir el archivo.'];
    }
    if ($archivo['size'] > $pesoMaximoMb * 1024 * 1024) {
        return ['ok' => false, 'error' => "El archivo supera el límite de {$pesoMaximoMb} MB."];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo['tmp_name']);
    $extensionesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensionesPermitidas[$mime])) {
        return ['ok' => false, 'error' => 'Formato no permitido. Usa JPG, PNG o WebP.'];
    }

    switch ($mime) {
        case 'image/jpeg':
            $origen = @imagecreatefromjpeg($archivo['tmp_name']);
            break;
        case 'image/png':
            $origen = @imagecreatefrompng($archivo['tmp_name']);
            break;
        case 'image/webp':
            $origen = @imagecreatefromwebp($archivo['tmp_name']);
            break;
        default:
            $origen = false;
    }
    if ($origen === false) {
        return ['ok' => false, 'error' => 'El archivo no es una imagen válida.'];
    }

    $anchoOriginal = imagesx($origen);
    $altoOriginal = imagesy($origen);
    if ($anchoOriginal > $anchoMaximo) {
        $altoNuevo = (int) round($altoOriginal * ($anchoMaximo / $anchoOriginal));
        $redimensionada = imagecreatetruecolor($anchoMaximo, $altoNuevo);
        imagealphablending($redimensionada, false);
        imagesavealpha($redimensionada, true);
        imagecopyresampled($redimensionada, $origen, 0, 0, 0, 0, $anchoMaximo, $altoNuevo, $anchoOriginal, $altoOriginal);
        imagedestroy($origen);
        $origen = $redimensionada;
    }

    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extensionesPermitidas[$mime];
    $rutaDestino = RUTA_UPLOADS . '/' . trim($subdirectorio, '/') . '/' . $nombreArchivo;

    $guardado = match ($mime) {
        'image/jpeg' => imagejpeg($origen, $rutaDestino, 85),
        'image/png'  => imagepng($origen, $rutaDestino, 6),
        'image/webp' => imagewebp($origen, $rutaDestino, 85),
        default => false,
    };
    imagedestroy($origen);

    if (!$guardado) {
        return ['ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor.'];
    }

    return ['ok' => true, 'archivo' => $nombreArchivo];
}

/**
 * Calcula la completitud (0-100) de una ficha según la ponderación del §5.3
 * y la guarda en une_negocios.completitud.
 */
function calcularCompletitud(PDO $pdo, int $negocioId): int
{
    $stmt = $pdo->prepare('SELECT * FROM une_negocios WHERE id = :id');
    $stmt->execute([':id' => $negocioId]);
    $negocio = $stmt->fetch();
    if (!$negocio) {
        return 0;
    }

    $puntos = 0;

    // Datos básicos (25)
    if ($negocio['nombre_comercial'] && $negocio['tipo_registro'] && $negocio['departamento_id'] && $negocio['telefono_publico']) {
        $puntos += 25;
    }

    // Dirección + coordenadas (15)
    if ($negocio['direccion'] && $negocio['latitud'] !== null && $negocio['longitud'] !== null) {
        $puntos += 15;
    }

    // Al menos 1 disciplina + 1 etapa (15)
    $stmtDep = $pdo->prepare('SELECT COUNT(*) FROM une_negocio_deportes WHERE negocio_id = :id');
    $stmtDep->execute([':id' => $negocioId]);
    $numDeportes = (int) $stmtDep->fetchColumn();

    $stmtCat = $pdo->prepare('SELECT COUNT(*) FROM une_negocio_categorias WHERE negocio_id = :id');
    $stmtCat->execute([':id' => $negocioId]);
    $numCategorias = (int) $stmtCat->fetchColumn();

    $stmtEtapa = $pdo->prepare('SELECT COUNT(*) FROM une_negocio_etapas WHERE negocio_id = :id');
    $stmtEtapa->execute([':id' => $negocioId]);
    $numEtapas = (int) $stmtEtapa->fetchColumn();

    $especialidadOk = $negocio['tipo_registro'] === 'formativo'
        ? ($numDeportes > 0 && $numEtapas > 0)
        : ($numCategorias > 0);
    if ($especialidadOk) {
        $puntos += 15;
    }

    // Horarios cargados (10)
    $stmtHorarios = $pdo->prepare('SELECT COUNT(*) FROM une_horarios WHERE negocio_id = :id');
    $stmtHorarios->execute([':id' => $negocioId]);
    if ((int) $stmtHorarios->fetchColumn() > 0) {
        $puntos += 10;
    }

    // Descripción >= 120 caracteres (10)
    if ($negocio['descripcion'] && mb_strlen($negocio['descripcion']) >= 120) {
        $puntos += 10;
    }

    // Logo (10)
    if (!empty($negocio['logo'])) {
        $puntos += 10;
    }

    // >= 3 fotos de galería (10)
    $stmtImg = $pdo->prepare('SELECT COUNT(*) FROM une_imagenes WHERE negocio_id = :id');
    $stmtImg->execute([':id' => $negocioId]);
    if ((int) $stmtImg->fetchColumn() >= 3) {
        $puntos += 10;
    }

    // Redes sociales o web (5)
    if ($negocio['web'] || $negocio['facebook'] || $negocio['instagram'] || $negocio['tiktok'] || $negocio['youtube']) {
        $puntos += 5;
    }

    $puntos = min(100, $puntos);

    $pdo->prepare('UPDATE une_negocios SET completitud = :c WHERE id = :id')
        ->execute([':c' => $puntos, ':id' => $negocioId]);

    return $puntos;
}

/**
 * Evalúa si una ficha cumple el umbral mínimo "publicable" (§7C, nivel 1).
 * Se valida siempre en servidor antes de permitir estado='publicado'.
 */
function cumpleUmbralPublicable(PDO $pdo, int $negocioId): bool
{
    $stmt = $pdo->prepare('SELECT * FROM une_negocios WHERE id = :id');
    $stmt->execute([':id' => $negocioId]);
    $n = $stmt->fetch();
    if (!$n) {
        return false;
    }
    if (!$n['nombre_comercial'] || !$n['tipo_registro'] || !$n['departamento_id']
        || !$n['provincia_id'] || !$n['distrito_id'] || !$n['telefono_publico']) {
        return false;
    }

    $stmtCat = $pdo->prepare('SELECT COUNT(*) FROM une_negocio_categorias WHERE negocio_id = :id');
    $stmtCat->execute([':id' => $negocioId]);
    if ((int) $stmtCat->fetchColumn() < 1) {
        return false;
    }

    if ($n['tipo_registro'] === 'formativo') {
        $stmtDep = $pdo->prepare('SELECT COUNT(*) FROM une_negocio_deportes WHERE negocio_id = :id');
        $stmtDep->execute([':id' => $negocioId]);
        if ((int) $stmtDep->fetchColumn() < 1) {
            return false;
        }
    }

    return true;
}

/**
 * Evalúa si una ficha cumple el umbral de "verificada" (§7C, nivel 2).
 * Solo un administrador puede marcarla, y solo si ya cumple esto.
 */
function cumpleUmbralVerificada(PDO $pdo, int $negocioId): bool
{
    if (!cumpleUmbralPublicable($pdo, $negocioId)) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT * FROM une_negocios WHERE id = :id');
    $stmt->execute([':id' => $negocioId]);
    $n = $stmt->fetch();

    if ($n['tipo_registro'] === 'formativo') {
        $stmtEtapa = $pdo->prepare('SELECT COUNT(*) FROM une_negocio_etapas WHERE negocio_id = :id');
        $stmtEtapa->execute([':id' => $negocioId]);
        if ((int) $stmtEtapa->fetchColumn() < 1) {
            return false;
        }
    }

    if (!$n['descripcion'] || mb_strlen($n['descripcion']) < 80) {
        return false;
    }

    $stmtHorarios = $pdo->prepare('SELECT COUNT(*) FROM une_horarios WHERE negocio_id = :id');
    $stmtHorarios->execute([':id' => $negocioId]);
    if ((int) $stmtHorarios->fetchColumn() < 1) {
        return false;
    }

    $stmtContacto = $pdo->prepare(
        "SELECT COUNT(*) FROM une_lead_historial WHERE negocio_id = :id AND resultado IN ('interesado','en_espera')"
    );
    $stmtContacto->execute([':id' => $negocioId]);
    if ((int) $stmtContacto->fetchColumn() < 1) {
        return false;
    }

    return true;
}

/** Genera un token de edición aleatorio y único para el reclamo de fichas. */
function generarTokenEdicion(): string
{
    return bin2hex(random_bytes(24));
}

/** Enlace de WhatsApp con mensaje precargado y editable. */
function enlaceWhatsApp(string $telefonoNormalizado, string $mensaje): string
{
    return 'https://wa.me/' . WHATSAPP_PREFIJO_PAIS . $telefonoNormalizado . '?text=' . rawurlencode($mensaje);
}

/** Ícono SVG en línea (20x20, currentColor) para el pie de página. */
function iconoRedSocial(string $red): string
{
    return match ($red) {
        'facebook' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M22 12.06C22 6.51 17.52 2 12 2S2 6.51 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.91h-2.34V22c4.78-.79 8.44-4.94 8.44-9.94z"/></svg>',
        'instagram' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M12 2c2.72 0 3.06.01 4.12.06 1.06.05 1.79.22 2.43.47.66.26 1.21.6 1.76 1.15.55.55.89 1.1 1.15 1.76.25.64.42 1.37.47 2.43.05 1.06.06 1.4.06 4.12s-.01 3.06-.06 4.12c-.05 1.06-.22 1.79-.47 2.43-.26.66-.6 1.21-1.15 1.76-.55.55-1.1.89-1.76 1.15-.64.25-1.37.42-2.43.47-1.06.05-1.4.06-4.12.06s-3.06-.01-4.12-.06c-1.06-.05-1.79-.22-2.43-.47-.66-.26-1.21-.6-1.76-1.15-.55-.55-.89-1.1-1.15-1.76-.25-.64-.42-1.37-.47-2.43C2.01 15.06 2 14.72 2 12s.01-3.06.06-4.12c.05-1.06.22-1.79.47-2.43.26-.66.6-1.21 1.15-1.76.55-.55 1.1-.89 1.76-1.15.64-.25 1.37-.42 2.43-.47C8.94 2.01 9.28 2 12 2zm0 1.8c-2.67 0-2.99.01-4.04.06-.87.04-1.34.18-1.65.3-.42.16-.72.36-1.03.67-.31.31-.51.61-.67 1.03-.12.31-.26.78-.3 1.65-.05 1.05-.06 1.37-.06 4.04s.01 2.99.06 4.04c.04.87.18 1.34.3 1.65.16.42.36.72.67 1.03.31.31.61.51 1.03.67.31.12.78.26 1.65.3 1.05.05 1.37.06 4.04.06s2.99-.01 4.04-.06c.87-.04 1.34-.18 1.65-.3.42-.16.72-.36 1.03-.67.31-.31.51-.61.67-1.03.12-.31.26-.78.3-1.65.05-1.05.06-1.37.06-4.04s-.01-2.99-.06-4.04c-.04-.87-.18-1.34-.3-1.65-.16-.42-.36-.72-.67-1.03-.31-.31-.61-.51-1.03-.67-.31-.12-.78-.26-1.65-.3C14.99 3.81 14.67 3.8 12 3.8zm0 3.05a5.15 5.15 0 1 1 0 10.3 5.15 5.15 0 0 1 0-10.3zm0 1.8a3.35 3.35 0 1 0 0 6.7 3.35 3.35 0 0 0 0-6.7zm5.34-1.99a1.2 1.2 0 1 1-2.4 0 1.2 1.2 0 0 1 2.4 0z"/></svg>',
        'tiktok' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M16.5 2h-3.1v13.6c0 1.4-1.1 2.5-2.5 2.5s-2.5-1.1-2.5-2.5 1.1-2.5 2.5-2.5c.28 0 .55.04.8.12v-3.2a5.7 5.7 0 0 0-.8-.06 5.7 5.7 0 1 0 5.7 5.7V8.9a8.3 8.3 0 0 0 4.9 1.6V7.4a5 5 0 0 1-5-5.4z"/></svg>',
        'youtube' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2s-.23-1.64-.94-2.36c-.9-.95-1.9-.95-2.36-1.01C16.9 2.5 12 2.5 12 2.5h-.01s-4.89 0-8.19.33c-.46.06-1.46.06-2.36 1.01C.73 4.56.5 6.2.5 6.2S.26 8.12.26 10.04v1.8c0 1.92.24 3.84.24 3.84s.23 1.64.94 2.36c.9.95 2.08.92 2.6 1.02 1.89.18 8 .24 8 .24s4.9-.01 8.19-.34c.46-.06 1.46-.06 2.36-1.01.71-.72.94-2.36.94-2.36s.24-1.92.24-3.84v-1.8c0-1.92-.24-3.84-.24-3.84zM9.75 14.85V7.85l6.5 3.5-6.5 3.5z"/></svg>',
        'linkedin' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M6.94 5a1.94 1.94 0 1 1-3.88 0 1.94 1.94 0 0 1 3.88 0zM3.4 8.75h3.4V21H3.4V8.75zm6.16 0h3.26v1.68h.05c.45-.86 1.56-1.77 3.22-1.77 3.44 0 4.08 2.27 4.08 5.22V21h-3.4v-5.6c0-1.34-.02-3.06-1.87-3.06-1.87 0-2.16 1.46-2.16 2.96V21h-3.4V8.75z"/></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.7-.8-1.9-.9-.3-.1-.4-.1-.6.1-.2.3-.7.9-.8 1-.2.2-.3.2-.5.1-.3-.1-1.2-.4-2.2-1.4-.8-.7-1.4-1.6-1.5-1.9-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.1.2-.3.3-.4.1-.2 0-.3 0-.5-.1-.1-.6-1.5-.8-2-.2-.5-.4-.5-.6-.5h-.5c-.2 0-.5.1-.7.3-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3 4.8 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.5-.1 1.7-.7 1.9-1.3.2-.7.2-1.2.2-1.3-.1-.2-.3-.3-.6-.4zM12 2C6.5 2 2 6.5 2 12c0 1.8.5 3.5 1.3 5L2 22l5.1-1.3c1.4.8 3.1 1.2 4.9 1.2 5.5 0 10-4.5 10-10S17.5 2 12 2zm0 18.2c-1.6 0-3.1-.4-4.5-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 0 1 3.8 12c0-4.5 3.7-8.2 8.2-8.2s8.2 3.7 8.2 8.2-3.7 8.2-8.2 8.2z"/></svg>',
        default => '',
    };
}

/**
 * Envía un correo simple en HTML con la función mail() nativa de PHP.
 *
 * NOTA: esto es una implementación mínima para la Fase 1. La Fase 3
 * (§11 del prompt) reemplaza esto por PHPMailer con SMTP autenticado del
 * dominio, porque mail() suele terminar en spam. Se usa aquí solo para
 * cumplir el criterio de aceptación "el admin recibe el aviso por correo
 * de inmediato" sin bloquear el registro del lead si el envío falla.
 */
function enviarCorreoSimple(string $destino, string $asunto, string $cuerpoHtml): bool
{
    $cabeceras = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . SITE_NAME . ' <' . SMTP_USUARIO . '>',
    ];
    try {
        return @mail($destino, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpoHtml, implode("\r\n", $cabeceras));
    } catch (\Throwable $e) {
        error_log('Error enviando correo: ' . $e->getMessage());
        return false;
    }
}

/** Convierte un DATETIME de la BD (guardado en UTC) a hora de Lima para mostrarlo. */
function formatoFechaLima(string $fechaMysqlUtc, string $formato = 'd/m/Y H:i'): string
{
    $dt = new DateTime($fechaMysqlUtc, new DateTimeZone('UTC'));
    $dt->setTimezone(new DateTimeZone('America/Lima'));
    return $dt->format($formato);
}

/** Formatea "hace N días/horas" a partir de un DATETIME MySQL. */
function tiempoRelativo(string $fechaMysql): string
{
    $entonces = strtotime($fechaMysql);
    $diff = time() - $entonces;
    if ($diff < 3600) {
        $min = max(1, (int) round($diff / 60));
        return $min === 1 ? 'hace 1 minuto' : "hace {$min} minutos";
    }
    if ($diff < 86400) {
        $horas = (int) round($diff / 3600);
        return $horas === 1 ? 'hace 1 hora' : "hace {$horas} horas";
    }
    $dias = (int) round($diff / 86400);
    return $dias === 1 ? 'hace 1 día' : "hace {$dias} días";
}
