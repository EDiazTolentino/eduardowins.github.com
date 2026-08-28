<?php
/**
 * UNE Sports — GET /api/blog.php
 * Devuelve todos los artículos del blog con la misma forma que antes tenía
 * data/blog.json, para que blog.js no necesite cambios de lógica.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

une_require_method('GET');

$pdo = une_db();

$rows = $pdo->query(
    "SELECT a.*, c.nombre AS categoria_nombre
     FROM blog_articulos a
     JOIN blog_categorias c ON c.id = a.categoria_id
     ORDER BY a.fecha_publicacion DESC, a.id DESC"
)->fetchAll();

$out = array_map(function ($a) {
    return [
        'id' => (int) $a['id'],
        'slug' => $a['slug'],
        'titulo' => $a['titulo'],
        'categoria' => $a['categoria_nombre'],
        'resumen' => $a['resumen'],
        'imagen' => $a['imagen'],
        'autor' => $a['autor_nombre'],
        'autorFoto' => $a['autor_foto'],
        'fecha' => $a['fecha_publicacion'],
        'tiempoLectura' => $a['tiempo_lectura'],
        'contenido' => json_decode($a['contenido'], true),
    ];
}, $rows);

une_send_json($out);
