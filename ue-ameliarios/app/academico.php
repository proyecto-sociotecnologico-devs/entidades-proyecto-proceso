<?php
/**
 * app/academico.php
 * CRUD de la estructura académica: grados, secciones, materias, cursos.
 * Versión Corregida: Soluciona el problema de enrutamiento en Editar/Eliminar.
 */
require_once __DIR__ . '/layout.php';

// ==========================================
// /academico (GET) — Redirige a Grados
// ==========================================
if ($uri === '/academico' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: /ue-ameliarios/public/index.php/academico/grados');
    exit;
}

// ==========================================
// /academico/grados (GET) — Listado
// ==========================================
if ($uri === '/academico/grados' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/academico', 'VER');

    $grados = db()->query("SELECT id_grado, nombre, nivel, orden FROM grado ORDER BY orden ASC, id_grado ASC")->fetchAll();

    renderHeader('Gestión de Grados');
    ?>
    <div class="card" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0;">Gestión de Grados</h2>
            <div style="display: flex; gap: 5px; margin-top: 15px; border-bottom: 2px solid #ddd;">
                <a href="/ue-ameliarios/public/index.php/academico/grados" style="padding: 10px 20px; text-decoration: none; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); font-weight: bold;">Grados</a>
                <a href="/ue-ameliarios/public/index.php/academico/secciones" style="padding: 10px 20px; text-decoration: none; color: #666;">Secciones</a>
                <a href="/ue-ameliarios/public/index.php/academico/materias" style="padding: 10px 20px; text-decoration: none; color: #666;">Materias</a>
                <a href="/ue-ameliarios/public/index.php/academico/cursos" style="padding: 10px 20px; text-decoration: none; color: #666;">Cursos</a>
            </div>
        </div>
        <?php if (can('/academico', 'CREAR')): ?>
        <a href="/ue-ameliarios/public/index.php/academico/grados/crear" style="padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">+ Nuevo Grado</a>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['exito'])): ?><div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">✅ <?= htmlspecialchars($_GET['exito']) ?></div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead><tr style="background: var(--primary-color); color: white;">
                <th style="padding: 12px; text-align: left;">Nombre</th><th style="padding: 12px; text-align: left;">Nivel</th>
                <th style="padding: 12px; text-align: left;">Orden</th><th style="padding: 12px; text-align: left;">Acciones</th>
            </tr></thead>
            <tbody>
                <?php foreach ($grados as $g): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px;"><?= htmlspecialchars($g['nombre']) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($g['nivel'] ?? '-') ?></td>
                    <td style="padding: 12px;"><?= $g['orden'] ?? '-' ?></td>
                    <td style="padding: 12px;">
                        <?php if (can('/academico', 'EDITAR')): ?><a href="/ue-ameliarios/public/index.php/academico/grados/editar/<?= $g['id_grado'] ?>" style="color: #2c5aa0; margin-right: 10px;">Editar</a><?php endif; ?>
                        <?php if (can('/academico', 'ELIMINAR')): ?><a href="/ue-ameliarios/public/index.php/academico/grados/eliminar/<?= $g['id_grado'] ?>" style="color: #e74c3c;" onclick="return confirm('¿Eliminar?')">Eliminar</a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/grados/crear (GET y POST)
// ==========================================
if ($uri === '/academico/grados/crear') {
    requireLogin(); requirePermission('/academico', 'CREAR');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $nivel = trim($_POST['nivel'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        if (empty($nombre)) { header('Location: /ue-ameliarios/public/index.php/academico/grados/crear?error=El+nombre+es+obligatorio'); exit; }

        try {
            db()->beginTransaction();
            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM grado WHERE nombre = :n");
            $stmt->execute(['n' => $nombre]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("El grado ya existe.");

            db()->prepare("INSERT INTO grado (nombre, nivel, orden) VALUES (:nombre, :nivel, :orden)")->execute(['nombre' => $nombre, 'nivel' => $nivel ?: null, 'orden' => $orden ?: null]);
            $id_grado = db()->lastInsertId();

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'CREAR_GRADO', 'grado', :r, NOW())")->execute(['u' => $uid, 'r' => $id_grado]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/academico/grados?exito=Grado+creado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            header('Location: /ue-ameliarios/public/index.php/academico/grados/crear?error=' . urlencode($e->getMessage())); exit;
        }
    }

    renderHeader('Nuevo Grado');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h2>Crear Nuevo Grado</h2>
        <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
        <form method="POST" action="/ue-ameliarios/public/index.php/academico/grados/crear">
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Nombre del Grado *</label>
                <input type="text" name="nombre" placeholder="Ej: 1er Año" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Nivel</label>
                <input type="text" name="nivel" placeholder="Ej: Primaria" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Orden</label>
                <input type="number" name="orden" placeholder="Ej: 1" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Crear Grado</button>
                <a href="/ue-ameliarios/public/index.php/academico/grados" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/grados/editar (GET y POST)
// ==========================================
if ($uri === '/academico/grados/editar') {
    requireLogin(); requirePermission('/academico', 'EDITAR');
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID no válido.");

    $stmt = db()->prepare("SELECT id_grado, nombre, nivel, orden FROM grado WHERE id_grado = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $grado = $stmt->fetch();
    if (!$grado) die("Error: Grado no encontrado.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $nivel = trim($_POST['nivel'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        if (empty($nombre)) die("Error: El nombre es obligatorio.");

        try {
            db()->beginTransaction();
            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM grado WHERE nombre = :n AND id_grado != :id");
            $stmt->execute(['n' => $nombre, 'id' => $id]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("Ya existe otro grado con ese nombre.");

            db()->prepare("UPDATE grado SET nombre = :nombre, nivel = :nivel, orden = :orden WHERE id_grado = :id")
                ->execute(['nombre' => $nombre, 'nivel' => $nivel ?: null, 'orden' => $orden ?: null, 'id' => $id]);

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'EDITAR_GRADO', 'grado', :r, NOW())")->execute(['u' => $uid, 'r' => $id]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/academico/grados?exito=Grado+actualizado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            die("Error al guardar: " . $e->getMessage());
        }
    }

    renderHeader('Editar Grado');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h2>Editar Grado: <?= htmlspecialchars($grado['nombre']) ?></h2>
        <form method="POST" action="/ue-ameliarios/public/index.php/academico/grados/editar/<?= $id ?>">
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Nombre del Grado *</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($grado['nombre']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Nivel</label>
                <input type="text" name="nivel" value="<?= htmlspecialchars($grado['nivel'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Orden</label>
                <input type="number" name="orden" value="<?= $grado['orden'] ?? '' ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Guardar Cambios</button>
                <a href="/ue-ameliarios/public/index.php/academico/grados" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/grados/eliminar (GET)
// ==========================================
if ($uri === '/academico/grados/eliminar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin(); requirePermission('/academico', 'ELIMINAR');
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID no válido.");

    $stmt = db()->prepare("SELECT id_grado, nombre FROM grado WHERE id_grado = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $grado = $stmt->fetch();
    if (!$grado) die("Error: Grado no encontrado.");

    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM curso WHERE id_grado = :id");
    $stmt->execute(['id' => $id]);
    if ($stmt->fetch()['total'] > 0) die("Error: No se puede eliminar, tiene cursos asociados.");

    try {
        db()->beginTransaction();
        db()->prepare("DELETE FROM grado WHERE id_grado = :id")->execute(['id' => $id]);
        
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'ELIMINAR_GRADO', 'grado', :r, NOW())")->execute(['u' => $uid, 'r' => $id]);
        
        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/academico/grados?exito=Grado+eliminado+exitosamente'); exit;
    } catch (Exception $e) {
        db()->rollBack();
        die("Error al eliminar: " . $e->getMessage());
    }
}

// ==========================================
// /academico/secciones (GET) — Listado
// ==========================================
if ($uri === '/academico/secciones' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin(); requirePermission('/academico', 'VER');
    $secciones = db()->query("SELECT id_seccion, nombre, capacidad FROM seccion ORDER BY nombre ASC")->fetchAll();

    renderHeader('Gestión de Secciones');
    ?>
    <div class="card" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0;">Gestión de Secciones</h2>
            <div style="display: flex; gap: 5px; margin-top: 15px; border-bottom: 2px solid #ddd;">
                <a href="/ue-ameliarios/public/index.php/academico/grados" style="padding: 10px 20px; text-decoration: none; color: #666;">Grados</a>
                <a href="/ue-ameliarios/public/index.php/academico/secciones" style="padding: 10px 20px; text-decoration: none; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); font-weight: bold;">Secciones</a>
                <a href="/ue-ameliarios/public/index.php/academico/materias" style="padding: 10px 20px; text-decoration: none; color: #666;">Materias</a>
                <a href="/ue-ameliarios/public/index.php/academico/cursos" style="padding: 10px 20px; text-decoration: none; color: #666;">Cursos</a>
            </div>
        </div>
        <?php if (can('/academico', 'CREAR')): ?>
        <a href="/ue-ameliarios/public/index.php/academico/secciones/crear" style="padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">+ Nueva Sección</a>
        <?php endif; ?>
    </div>
    <?php if (isset($_GET['exito'])): ?><div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">✅ <?= htmlspecialchars($_GET['exito']) ?></div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead><tr style="background: var(--primary-color); color: white;">
                <th style="padding: 12px; text-align: left;">Nombre</th><th style="padding: 12px; text-align: left;">Capacidad</th><th style="padding: 12px; text-align: left;">Acciones</th>
            </tr></thead>
            <tbody>
                <?php foreach ($secciones as $s): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px;"><?= htmlspecialchars($s['nombre']) ?></td>
                    <td style="padding: 12px;"><?= $s['capacidad'] ?? '-' ?></td>
                    <td style="padding: 12px;">
                        <?php if (can('/academico', 'EDITAR')): ?><a href="/ue-ameliarios/public/index.php/academico/secciones/editar/<?= $s['id_seccion'] ?>" style="color: #2c5aa0; margin-right: 10px;">Editar</a><?php endif; ?>
                        <?php if (can('/academico', 'ELIMINAR')): ?><a href="/ue-ameliarios/public/index.php/academico/secciones/eliminar/<?= $s['id_seccion'] ?>" style="color: #e74c3c;" onclick="return confirm('¿Eliminar?')">Eliminar</a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/secciones/crear (GET y POST)
// ==========================================
if ($uri === '/academico/secciones/crear') {
    requireLogin(); requirePermission('/academico', 'CREAR');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $capacidad = (int)($_POST['capacidad'] ?? 0);
        if (empty($nombre)) { header('Location: /ue-ameliarios/public/index.php/academico/secciones/crear?error=El+nombre+es+obligatorio'); exit; }

        try {
            db()->beginTransaction();
            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM seccion WHERE nombre = :n");
            $stmt->execute(['n' => $nombre]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("La sección ya existe.");

            db()->prepare("INSERT INTO seccion (nombre, capacidad) VALUES (:nombre, :capacidad)")->execute(['nombre' => $nombre, 'capacidad' => $capacidad ?: null]);
            $id_seccion = db()->lastInsertId();

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'CREAR_SECCION', 'seccion', :r, NOW())")->execute(['u' => $uid, 'r' => $id_seccion]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/academico/secciones?exito=Seccion+creada+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            header('Location: /ue-ameliarios/public/index.php/academico/secciones/crear?error=' . urlencode($e->getMessage())); exit;
        }
    }

    renderHeader('Nueva Sección');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h2>Crear Nueva Sección</h2>
        <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
        <form method="POST" action="/ue-ameliarios/public/index.php/academico/secciones/crear">
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Nombre de la Sección *</label>
                <input type="text" name="nombre" placeholder="Ej: A" required maxlength="10" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Capacidad</label>
                <input type="number" name="capacidad" placeholder="Ej: 30" min="1" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Crear Sección</button>
                <a href="/ue-ameliarios/public/index.php/academico/secciones" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/secciones/editar (GET y POST)
// ==========================================
if ($uri === '/academico/secciones/editar') {
    requireLogin(); requirePermission('/academico', 'EDITAR');
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID no válido.");

    $stmt = db()->prepare("SELECT id_seccion, nombre, capacidad FROM seccion WHERE id_seccion = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $seccion = $stmt->fetch();
    if (!$seccion) die("Error: Sección no encontrada.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $nombre = trim($_POST['nombre'] ?? '');
        $capacidad = (int)($_POST['capacidad'] ?? 0);
        if (empty($nombre)) die("Error: El nombre es obligatorio.");

        try {
            db()->beginTransaction();
            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM seccion WHERE nombre = :n AND id_seccion != :id");
            $stmt->execute(['n' => $nombre, 'id' => $id]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("Ya existe otra sección con ese nombre.");

            db()->prepare("UPDATE seccion SET nombre = :nombre, capacidad = :capacidad WHERE id_seccion = :id")
                ->execute(['nombre' => $nombre, 'capacidad' => $capacidad ?: null, 'id' => $id]);

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'EDITAR_SECCION', 'seccion', :r, NOW())")->execute(['u' => $uid, 'r' => $id]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/academico/secciones?exito=Seccion+actualizada+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            die("Error al guardar: " . $e->getMessage());
        }
    }

    renderHeader('Editar Sección');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h2>Editar Sección: <?= htmlspecialchars($seccion['nombre']) ?></h2>
        <form method="POST" action="/ue-ameliarios/public/index.php/academico/secciones/editar/<?= $id ?>">
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Nombre de la Sección *</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($seccion['nombre']) ?>" required maxlength="10" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Capacidad</label>
                <input type="number" name="capacidad" value="<?= $seccion['capacidad'] ?? '' ?>" min="1" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Guardar Cambios</button>
                <a href="/ue-ameliarios/public/index.php/academico/secciones" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/secciones/eliminar (GET)
// ==========================================
if ($uri === '/academico/secciones/eliminar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin(); requirePermission('/academico', 'ELIMINAR');
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID no válido.");

    $stmt = db()->prepare("SELECT id_seccion, nombre FROM seccion WHERE id_seccion = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $seccion = $stmt->fetch();
    if (!$seccion) die("Error: Sección no encontrada.");

    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM curso WHERE id_seccion = :id");
    $stmt->execute(['id' => $id]);
    if ($stmt->fetch()['total'] > 0) die("Error: No se puede eliminar, tiene cursos asociados.");

    try {
        db()->beginTransaction();
        db()->prepare("DELETE FROM seccion WHERE id_seccion = :id")->execute(['id' => $id]);
        
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'ELIMINAR_SECCION', 'seccion', :r, NOW())")->execute(['u' => $uid, 'r' => $id]);
        
        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/academico/secciones?exito=Seccion+eliminada+exitosamente'); exit;
    } catch (Exception $e) {
        db()->rollBack();
        die("Error al eliminar: " . $e->getMessage());
    }
}

// ==========================================
// /academico/materias (GET) — Listado
// ==========================================
if ($uri === '/academico/materias' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin(); requirePermission('/academico', 'VER');
    $materias = db()->query("SELECT id_materia, codigo, nombre, descripcion FROM materia ORDER BY nombre ASC")->fetchAll();

    renderHeader('Gestión de Materias');
    ?>
    <div class="card" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0;">Gestión de Materias</h2>
            <div style="display: flex; gap: 5px; margin-top: 15px; border-bottom: 2px solid #ddd;">
                <a href="/ue-ameliarios/public/index.php/academico/grados" style="padding: 10px 20px; text-decoration: none; color: #666;">Grados</a>
                <a href="/ue-ameliarios/public/index.php/academico/secciones" style="padding: 10px 20px; text-decoration: none; color: #666;">Secciones</a>
                <a href="/ue-ameliarios/public/index.php/academico/materias" style="padding: 10px 20px; text-decoration: none; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); font-weight: bold;">Materias</a>
                <a href="/ue-ameliarios/public/index.php/academico/cursos" style="padding: 10px 20px; text-decoration: none; color: #666;">Cursos</a>
            </div>
        </div>
        <?php if (can('/academico', 'CREAR')): ?>
        <a href="/ue-ameliarios/public/index.php/academico/materias/crear" style="padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">+ Nueva Materia</a>
        <?php endif; ?>
    </div>
    <?php if (isset($_GET['exito'])): ?><div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">✅ <?= htmlspecialchars($_GET['exito']) ?></div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead><tr style="background: var(--primary-color); color: white;">
                <th style="padding: 12px; text-align: left;">Código</th><th style="padding: 12px; text-align: left;">Nombre</th><th style="padding: 12px; text-align: left;">Descripción</th><th style="padding: 12px; text-align: left;">Acciones</th>
            </tr></thead>
            <tbody>
                <?php foreach ($materias as $m): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px;"><?= htmlspecialchars($m['codigo']) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($m['nombre']) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($m['descripcion'] ?? '-') ?></td>
                    <td style="padding: 12px;">
                        <?php if (can('/academico', 'EDITAR')): ?><a href="/ue-ameliarios/public/index.php/academico/materias/editar/<?= $m['id_materia'] ?>" style="color: #2c5aa0; margin-right: 10px;">Editar</a><?php endif; ?>
                        <?php if (can('/academico', 'ELIMINAR')): ?><a href="/ue-ameliarios/public/index.php/academico/materias/eliminar/<?= $m['id_materia'] ?>" style="color: #e74c3c;" onclick="return confirm('¿Eliminar?')">Eliminar</a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/materias/crear (GET y POST)
// ==========================================
if ($uri === '/academico/materias/crear') {
    requireLogin(); requirePermission('/academico', 'CREAR');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        if (empty($codigo) || empty($nombre)) { header('Location: /ue-ameliarios/public/index.php/academico/materias/crear?error=Codigo+y+nombre+son+obligatorios'); exit; }

        try {
            db()->beginTransaction();
            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM materia WHERE codigo = :c OR nombre = :n");
            $stmt->execute(['c' => $codigo, 'n' => $nombre]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("El código o nombre ya existen.");

            db()->prepare("INSERT INTO materia (codigo, nombre, descripcion) VALUES (:codigo, :nombre, :descripcion)")
                ->execute(['codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => $descripcion ?: null]);
            $id_materia = db()->lastInsertId();

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'CREAR_MATERIA', 'materia', :r, NOW())")->execute(['u' => $uid, 'r' => $id_materia]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/academico/materias?exito=Materia+creada+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            header('Location: /ue-ameliarios/public/index.php/academico/materias/crear?error=' . urlencode($e->getMessage())); exit;
        }
    }

    renderHeader('Nueva Materia');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h2>Crear Nueva Materia</h2>
        <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
        <form method="POST" action="/ue-ameliarios/public/index.php/academico/materias/crear">
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Código *</label>
                <input type="text" name="codigo" placeholder="Ej: MAT" required maxlength="20" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Nombre *</label>
                <input type="text" name="nombre" placeholder="Ej: Matemática" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Descripción</label>
                <textarea name="descripcion" rows="3" placeholder="Descripción opcional" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit;"></textarea></div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Crear Materia</button>
                <a href="/ue-ameliarios/public/index.php/academico/materias" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/materias/editar (GET y POST)
// ==========================================
if ($uri === '/academico/materias/editar') {
    requireLogin(); requirePermission('/academico', 'EDITAR');
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID no válido.");

    $stmt = db()->prepare("SELECT id_materia, codigo, nombre, descripcion FROM materia WHERE id_materia = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $materia = $stmt->fetch();
    if (!$materia) die("Error: Materia no encontrada.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $codigo = trim($_POST['codigo'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        if (empty($codigo) || empty($nombre)) die("Error: Código y nombre son obligatorios.");

        try {
            db()->beginTransaction();
            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM materia WHERE (codigo = :c OR nombre = :n) AND id_materia != :id");
            $stmt->execute(['c' => $codigo, 'n' => $nombre, 'id' => $id]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("El código o nombre ya existen en otra materia.");

            db()->prepare("UPDATE materia SET codigo = :codigo, nombre = :nombre, descripcion = :descripcion WHERE id_materia = :id")
                ->execute(['codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => $descripcion ?: null, 'id' => $id]);

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'EDITAR_MATERIA', 'materia', :r, NOW())")->execute(['u' => $uid, 'r' => $id]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/academico/materias?exito=Materia+actualizada+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            die("Error al guardar: " . $e->getMessage());
        }
    }

    renderHeader('Editar Materia');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h2>Editar Materia: <?= htmlspecialchars($materia['nombre']) ?></h2>
        <form method="POST" action="/ue-ameliarios/public/index.php/academico/materias/editar/<?= $id ?>">
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Código *</label>
                <input type="text" name="codigo" value="<?= htmlspecialchars($materia['codigo']) ?>" required maxlength="20" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Nombre *</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($materia['nombre']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Descripción</label>
                <textarea name="descripcion" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit;"><?= htmlspecialchars($materia['descripcion'] ?? '') ?></textarea></div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Guardar Cambios</button>
                <a href="/ue-ameliarios/public/index.php/academico/materias" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/materias/eliminar (GET)
// ==========================================
if ($uri === '/academico/materias/eliminar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin(); requirePermission('/academico', 'ELIMINAR');
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID no válido.");

    $stmt = db()->prepare("SELECT id_materia, nombre FROM materia WHERE id_materia = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $materia = $stmt->fetch();
    if (!$materia) die("Error: Materia no encontrada.");

    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM asignacion_docente WHERE id_materia = :id");
    $stmt->execute(['id' => $id]);
    if ($stmt->fetch()['total'] > 0) die("Error: No se puede eliminar, tiene docentes asignados.");

    try {
        db()->beginTransaction();
        db()->prepare("DELETE FROM materia WHERE id_materia = :id")->execute(['id' => $id]);
        
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'ELIMINAR_MATERIA', 'materia', :r, NOW())")->execute(['u' => $uid, 'r' => $id]);
        
        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/academico/materias?exito=Materia+eliminada+exitosamente'); exit;
    } catch (Exception $e) {
        db()->rollBack();
        die("Error al eliminar: " . $e->getMessage());
    }
}

// ==========================================
// /academico/cursos (GET) — Listado
// ==========================================
if ($uri === '/academico/cursos' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin(); requirePermission('/academico', 'VER');
    $cursos = db()->query("
        SELECT c.id_curso, c.aula, g.nombre AS grado, s.nombre AS seccion, p.nombre AS periodo
        FROM curso c
        INNER JOIN grado g ON c.id_grado = g.id_grado
        INNER JOIN seccion s ON c.id_seccion = s.id_seccion
        INNER JOIN periodo_academico p ON c.id_periodo = p.id_periodo
        ORDER BY p.id_periodo DESC, g.orden ASC, s.nombre ASC
    ")->fetchAll();

    renderHeader('Gestión de Cursos');
    ?>
    <div class="card" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0;">Gestión de Cursos</h2>
            <div style="display: flex; gap: 5px; margin-top: 15px; border-bottom: 2px solid #ddd;">
                <a href="/ue-ameliarios/public/index.php/academico/grados" style="padding: 10px 20px; text-decoration: none; color: #666;">Grados</a>
                <a href="/ue-ameliarios/public/index.php/academico/secciones" style="padding: 10px 20px; text-decoration: none; color: #666;">Secciones</a>
                <a href="/ue-ameliarios/public/index.php/academico/materias" style="padding: 10px 20px; text-decoration: none; color: #666;">Materias</a>
                <a href="/ue-ameliarios/public/index.php/academico/cursos" style="padding: 10px 20px; text-decoration: none; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); font-weight: bold;">Cursos</a>
            </div>
        </div>
        <?php if (can('/academico', 'CREAR')): ?>
        <a href="/ue-ameliarios/public/index.php/academico/cursos/crear" style="padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">+ Nuevo Curso</a>
        <?php endif; ?>
    </div>
    <?php if (isset($_GET['exito'])): ?><div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">✅ <?= htmlspecialchars($_GET['exito']) ?></div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead><tr style="background: var(--primary-color); color: white;">
                <th style="padding: 12px; text-align: left;">Grado</th><th style="padding: 12px; text-align: left;">Sección</th>
                <th style="padding: 12px; text-align: left;">Período</th><th style="padding: 12px; text-align: left;">Aula</th><th style="padding: 12px; text-align: left;">Acciones</th>
            </tr></thead>
            <tbody>
                <?php foreach ($cursos as $c): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px;"><?= htmlspecialchars($c['grado']) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($c['seccion']) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($c['periodo']) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($c['aula'] ?? '-') ?></td>
                    <td style="padding: 12px;">
                        <?php if (can('/academico', 'EDITAR')): ?><a href="/ue-ameliarios/public/index.php/academico/cursos/editar/<?= $c['id_curso'] ?>" style="color: #2c5aa0; margin-right: 10px;">Editar</a><?php endif; ?>
                        <?php if (can('/academico', 'ELIMINAR')): ?><a href="/ue-ameliarios/public/index.php/academico/cursos/eliminar/<?= $c['id_curso'] ?>" style="color: #e74c3c;" onclick="return confirm('¿Eliminar?')">Eliminar</a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/cursos/crear (GET y POST)
// ==========================================
if ($uri === '/academico/cursos/crear') {
    requireLogin(); requirePermission('/academico', 'CREAR');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_grado = (int)($_POST['id_grado'] ?? 0);
        $id_seccion = (int)($_POST['id_seccion'] ?? 0);
        $id_periodo = (int)($_POST['id_periodo'] ?? 0);
        $aula = trim($_POST['aula'] ?? '');

        if ($id_grado === 0 || $id_seccion === 0 || $id_periodo === 0) {
            header('Location: /ue-ameliarios/public/index.php/academico/cursos/crear?error=Grado,+seccion+y+periodo+son+obligatorios'); exit;
        }

        try {
            db()->beginTransaction();
            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM curso WHERE id_grado = :g AND id_seccion = :s AND id_periodo = :p");
            $stmt->execute(['g' => $id_grado, 's' => $id_seccion, 'p' => $id_periodo]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("Ese curso ya existe para ese período.");

            db()->prepare("INSERT INTO curso (id_grado, id_seccion, id_periodo, aula) VALUES (:g, :s, :p, :aula)")
                ->execute(['g' => $id_grado, 's' => $id_seccion, 'p' => $id_periodo, 'aula' => $aula ?: null]);
            $id_curso = db()->lastInsertId();

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'CREAR_CURSO', 'curso', :r, NOW())")->execute(['u' => $uid, 'r' => $id_curso]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/academico/cursos?exito=Curso+creado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            header('Location: /ue-ameliarios/public/index.php/academico/cursos/crear?error=' . urlencode($e->getMessage())); exit;
        }
    }

    $grados = db()->query("SELECT id_grado, nombre FROM grado ORDER BY orden")->fetchAll();
    $secciones = db()->query("SELECT id_seccion, nombre FROM seccion ORDER BY nombre")->fetchAll();
    $periodos = db()->query("SELECT id_periodo, nombre FROM periodo_academico ORDER BY id_periodo DESC")->fetchAll();

    renderHeader('Nuevo Curso');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h2>Crear Nuevo Curso</h2>
        <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
        <form method="POST" action="/ue-ameliarios/public/index.php/academico/cursos/crear">
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Grado *</label>
                <select name="id_grado" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($grados as $g): ?><option value="<?= $g['id_grado'] ?>"><?= htmlspecialchars($g['nombre']) ?></option><?php endforeach; ?>
                </select></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Sección *</label>
                <select name="id_seccion" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($secciones as $s): ?><option value="<?= $s['id_seccion'] ?>"><?= htmlspecialchars($s['nombre']) ?></option><?php endforeach; ?>
                </select></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Período Académico *</label>
                <select name="id_periodo" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($periodos as $p): ?><option value="<?= $p['id_periodo'] ?>"><?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?>
                </select></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Aula</label>
                <input type="text" name="aula" placeholder="Ej: Aula 01" maxlength="20" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Crear Curso</button>
                <a href="/ue-ameliarios/public/index.php/academico/cursos" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/cursos/editar (GET y POST)
// ==========================================
if ($uri === '/academico/cursos/editar') {
    requireLogin(); requirePermission('/academico', 'EDITAR');
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID no válido.");

    $stmt = db()->prepare("SELECT id_curso, id_grado, id_seccion, id_periodo, aula FROM curso WHERE id_curso = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $curso = $stmt->fetch();
    if (!$curso) die("Error: Curso no encontrado.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_grado = (int)($_POST['id_grado'] ?? 0);
        $id_seccion = (int)($_POST['id_seccion'] ?? 0);
        $id_periodo = (int)($_POST['id_periodo'] ?? 0);
        $aula = trim($_POST['aula'] ?? '');

        if ($id_grado === 0 || $id_seccion === 0 || $id_periodo === 0) die("Error: Grado, sección y período son obligatorios.");

        try {
            db()->beginTransaction();
            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM curso WHERE id_grado = :g AND id_seccion = :s AND id_periodo = :p AND id_curso != :id");
            $stmt->execute(['g' => $id_grado, 's' => $id_seccion, 'p' => $id_periodo, 'id' => $id]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("Ya existe otro curso con esos datos.");

            db()->prepare("UPDATE curso SET id_grado = :g, id_seccion = :s, id_periodo = :p, aula = :aula WHERE id_curso = :id")
                ->execute(['g' => $id_grado, 's' => $id_seccion, 'p' => $id_periodo, 'aula' => $aula ?: null, 'id' => $id]);

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'EDITAR_CURSO', 'curso', :r, NOW())")->execute(['u' => $uid, 'r' => $id]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/academico/cursos?exito=Curso+actualizado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            die("Error al guardar: " . $e->getMessage());
        }
    }

    $grados = db()->query("SELECT id_grado, nombre FROM grado ORDER BY orden")->fetchAll();
    $secciones = db()->query("SELECT id_seccion, nombre FROM seccion ORDER BY nombre")->fetchAll();
    $periodos = db()->query("SELECT id_periodo, nombre FROM periodo_academico ORDER BY id_periodo DESC")->fetchAll();

    renderHeader('Editar Curso');
    ?>
    <div class="card" style="max-width: 700px; margin: 0 auto;">
        <h2>Editar Curso ID: <?= $curso['id_curso'] ?></h2>
        <form method="POST" action="/ue-ameliarios/public/index.php/academico/cursos/editar/<?= $id ?>">
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Grado *</label>
                <select name="id_grado" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <?php foreach ($grados as $g): ?><option value="<?= $g['id_grado'] ?>" <?= ($g['id_grado'] == $curso['id_grado']) ? 'selected' : '' ?>><?= htmlspecialchars($g['nombre']) ?></option><?php endforeach; ?>
                </select></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Sección *</label>
                <select name="id_seccion" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <?php foreach ($secciones as $s): ?><option value="<?= $s['id_seccion'] ?>" <?= ($s['id_seccion'] == $curso['id_seccion']) ? 'selected' : '' ?>><?= htmlspecialchars($s['nombre']) ?></option><?php endforeach; ?>
                </select></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Período Académico *</label>
                <select name="id_periodo" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <?php foreach ($periodos as $p): ?><option value="<?= $p['id_periodo'] ?>" <?= ($p['id_periodo'] == $curso['id_periodo']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option><?php endforeach; ?>
                </select></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Aula</label>
                <input type="text" name="aula" value="<?= htmlspecialchars($curso['aula'] ?? '') ?>" maxlength="20" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Guardar Cambios</button>
                <a href="/ue-ameliarios/public/index.php/academico/cursos" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /academico/cursos/eliminar (GET)
// ==========================================
if ($uri === '/academico/cursos/eliminar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin(); requirePermission('/academico', 'ELIMINAR');
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID no válido.");

    $stmt = db()->prepare("SELECT id_curso FROM curso WHERE id_curso = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $curso = $stmt->fetch();
    if (!$curso) die("Error: Curso no encontrado.");

    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM inscripcion WHERE id_curso = :id");
    $stmt->execute(['id' => $id]);
    if ($stmt->fetch()['total'] > 0) die("Error: No se puede eliminar, tiene estudiantes inscritos.");

    try {
        db()->beginTransaction();
        db()->prepare("DELETE FROM curso WHERE id_curso = :id")->execute(['id' => $id]);
        
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'ELIMINAR_CURSO', 'curso', :r, NOW())")->execute(['u' => $uid, 'r' => $id]);
        
        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/academico/cursos?exito=Curso+eliminado+exitosamente'); exit;
    } catch (Exception $e) {
        db()->rollBack();
        die("Error al eliminar: " . $e->getMessage());
    }
}