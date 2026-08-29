<?php
/**
 * admin/articulos.php — Gestión simple del blog (listado + alta/edición
 * + eliminación). El contenido HTML lo escribe el propio equipo, por
 * eso no se sanitiza al mostrarlo en pages/articulo.php.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

exigirLoginAdmin();
$pdo = BaseDatos::obtener();

$mensaje = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfExigirOMorir();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $resumen = trim((string) ($_POST['resumen'] ?? '')) ?: null;
        $contenido = (string) ($_POST['contenido'] ?? '');
        $categoria = trim((string) ($_POST['categoria'] ?? '')) ?: null;
        $metaTitulo = trim((string) ($_POST['meta_titulo'] ?? '')) ?: null;
        $metaDescripcion = trim((string) ($_POST['meta_descripcion'] ?? '')) ?: null;
        $publicado = !empty($_POST['publicado']) ? 1 : 0;
        $fecha = ($_POST['fecha'] ?? '') ?: date('Y-m-d');

        $imagenArchivo = null;
        if (!empty($_FILES['imagen']['name'])) {
            $resultado = subirImagen($_FILES['imagen'], 'galeria', 1200, 3);
            if ($resultado['ok']) {
                $imagenArchivo = $resultado['archivo'];
            }
        }

        if ($titulo === '' || mb_strlen($contenido) < 20) {
            $mensaje = 'El título y el contenido son obligatorios.';
        } elseif ($id > 0) {
            $sql = 'UPDATE une_articulos SET titulo=:titulo, resumen=:resumen, contenido=:contenido, categoria=:categoria,
                    meta_titulo=:meta_titulo, meta_descripcion=:meta_descripcion, publicado=:publicado, fecha=:fecha';
            $params = [
                ':titulo' => $titulo, ':resumen' => $resumen, ':contenido' => $contenido, ':categoria' => $categoria,
                ':meta_titulo' => $metaTitulo, ':meta_descripcion' => $metaDescripcion, ':publicado' => $publicado,
                ':fecha' => $fecha, ':id' => $id,
            ];
            if ($imagenArchivo) {
                $sql .= ', imagen=:imagen';
                $params[':imagen'] = $imagenArchivo;
            }
            $sql .= ' WHERE id=:id';
            $pdo->prepare($sql)->execute($params);
            $mensaje = 'Artículo actualizado.';
        } else {
            $slug = generarSlugUnicoArticulo($pdo, $titulo);
            $pdo->prepare(
                'INSERT INTO une_articulos (titulo, slug, resumen, contenido, imagen, categoria, meta_titulo, meta_descripcion, publicado, fecha)
                 VALUES (:titulo, :slug, :resumen, :contenido, :imagen, :categoria, :meta_titulo, :meta_descripcion, :publicado, :fecha)'
            )->execute([
                ':titulo' => $titulo, ':slug' => $slug, ':resumen' => $resumen, ':contenido' => $contenido,
                ':imagen' => $imagenArchivo, ':categoria' => $categoria, ':meta_titulo' => $metaTitulo,
                ':meta_descripcion' => $metaDescripcion, ':publicado' => $publicado, ':fecha' => $fecha,
            ]);
            $mensaje = 'Artículo creado.';
            $id = (int) $pdo->lastInsertId();
        }
        header('Location: /admin/articulos.php?id=' . $id . '&guardado=1');
        exit;
    }

    if ($accion === 'eliminar') {
        exigirRolAdministrador();
        $pdo->prepare('DELETE FROM une_articulos WHERE id = :id')->execute([':id' => (int) $_POST['id']]);
        header('Location: /admin/articulos.php?eliminado=1');
        exit;
    }
}

function generarSlugUnicoArticulo(PDO $pdo, string $titulo): string
{
    $base = generarSlugBase($titulo);
    $slug = $base;
    $n = 2;
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM une_articulos WHERE slug = :slug');
    while (true) {
        $stmt->execute([':slug' => $slug]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }
        $slug = $base . '-' . $n++;
    }
}

$idEditar = (int) ($_GET['id'] ?? 0);
$articulo = null;
if ($idEditar) {
    $stmt = $pdo->prepare('SELECT * FROM une_articulos WHERE id = :id');
    $stmt->execute([':id' => $idEditar]);
    $articulo = $stmt->fetch();
}

$articulos = $pdo->query('SELECT id, titulo, publicado, fecha FROM une_articulos ORDER BY fecha DESC')->fetchAll();

$tituloPagina = 'Blog — Panel ' . SITE_NAME;
require __DIR__ . '/../includes/admin-header.php';
?>
<div class="contenedor-admin">
  <h1>Blog</h1>
  <?php if (isset($_GET['guardado'])): ?><p class="alerta alerta--exito">Guardado.</p><?php endif; ?>
  <?php if (isset($_GET['eliminado'])): ?><p class="alerta alerta--exito">Artículo eliminado.</p><?php endif; ?>
  <?php if ($mensaje): ?><p class="alerta alerta--error"><?= e($mensaje) ?></p><?php endif; ?>

  <div class="editor-negocio__layout">
    <div class="editor-negocio__principal">
      <h2><?= $articulo ? 'Editar artículo' : 'Nuevo artículo' ?></h2>
      <form method="post" enctype="multipart/form-data">
        <?= csrfCampo() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) ($articulo['id'] ?? 0) ?>">

        <div class="campo"><label for="titulo">Título *</label><input type="text" id="titulo" name="titulo" required value="<?= e($articulo['titulo'] ?? '') ?>"></div>
        <div class="campo"><label for="resumen">Resumen</label><textarea id="resumen" name="resumen" rows="2"><?= e($articulo['resumen'] ?? '') ?></textarea></div>
        <div class="campo"><label for="contenido">Contenido (HTML) *</label><textarea id="contenido" name="contenido" rows="14" required><?= $articulo['contenido'] ?? '' ?></textarea></div>
        <div class="campo"><label for="categoria">Categoría</label><input type="text" id="categoria" name="categoria" value="<?= e($articulo['categoria'] ?? '') ?>"></div>
        <div class="campo"><label for="fecha">Fecha</label><input type="date" id="fecha" name="fecha" value="<?= e($articulo['fecha'] ?? date('Y-m-d')) ?>"></div>
        <div class="campo">
          <label for="imagen">Imagen de portada</label>
          <?php if (!empty($articulo['imagen'])): ?><img src="/uploads/galeria/<?= e($articulo['imagen']) ?>" width="120" alt=""><?php endif; ?>
          <input type="file" id="imagen" name="imagen" accept="image/png,image/jpeg,image/webp">
        </div>
        <div class="campo"><label for="meta_titulo">Meta título (SEO)</label><input type="text" id="meta_titulo" name="meta_titulo" value="<?= e($articulo['meta_titulo'] ?? '') ?>"></div>
        <div class="campo"><label for="meta_descripcion">Meta descripción (SEO)</label><input type="text" id="meta_descripcion" name="meta_descripcion" value="<?= e($articulo['meta_descripcion'] ?? '') ?>"></div>
        <label class="opcion-casilla"><input type="checkbox" name="publicado" <?= !empty($articulo['publicado']) ? 'checked' : '' ?>> Publicado</label>

        <button type="submit" class="boton boton--primario">Guardar</button>
        <?php if ($articulo): ?><a href="/admin/articulos.php" class="boton boton--secundario">Nuevo artículo</a><?php endif; ?>
      </form>
    </div>

    <aside class="editor-negocio__lateral">
      <div class="panel-lead">
        <h2>Artículos</h2>
        <ul class="historial-lead">
          <?php foreach ($articulos as $a): ?>
            <li>
              <a href="/admin/articulos.php?id=<?= (int) $a['id'] ?>"><?= e($a['titulo']) ?></a>
              — <?= $a['publicado'] ? 'publicado' : 'borrador' ?>
              <?php if (adminEsAdministrador()): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este artículo?');">
                  <?= csrfCampo() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                  <button type="submit" class="boton-enlace boton-enlace--peligro">Eliminar</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </aside>
  </div>
</div>
<?php
require __DIR__ . '/../includes/admin-footer.php';
