<?php
/**
 * app/estudiantes.php
 * CRUD de Estudiantes con RBAC Jerárquico, Auditoría e Integridad de Datos.
 * Versión Corregida: Soluciona el problema de enrutamiento en Editar/Eliminar.
 */
require_once __DIR__ . '/layout.php';

// ==========================================
// /estudiantes (GET) — Listado
// ==========================================
if ($uri === '/estudiantes' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/estudiantes', 'VER');

    $estudiantes = db()->query("
        SELECT e.id_estudiante, e.codigo_estudiante, e.fecha_ingreso, e.estado,
               p.id_persona, p.cedula, p.nombres, p.apellidos, p.telefono, p.correo_personal
        FROM estudiante e
        INNER JOIN persona p ON e.id_persona = p.id_persona
        ORDER BY p.apellidos ASC, p.nombres ASC
    ")->fetchAll();

    renderHeader('Gestión de Estudiantes');
    ?>
    <div class="card" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">Estudiantes del Sistema</h2>
        <?php if (can('/estudiantes', 'CREAR')): ?>
        <a href="/ue-ameliarios/public/index.php/estudiantes/crear" style="padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">+ Nuevo Estudiante</a>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['exito'])): ?><div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">✅ <?= htmlspecialchars($_GET['exito']) ?></div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <thead><tr style="background: var(--primary-color); color: white;">
                <th style="padding: 12px; text-align: left;">Código</th>
                <th style="padding: 12px; text-align: left;">Cédula</th>
                <th style="padding: 12px; text-align: left;">Estudiante</th>
                <th style="padding: 12px; text-align: left;">Teléfono</th>
                <th style="padding: 12px; text-align: left;">Estado</th>
                <th style="padding: 12px; text-align: left;">Acciones</th>
            </tr></thead>
            <tbody>
                <?php foreach ($estudiantes as $e): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px;"><?= htmlspecialchars($e['codigo_estudiante']) ?></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($e['cedula']) ?></td>
                    <td style="padding: 12px;"><strong><?= htmlspecialchars($e['apellidos'] . ', ' . $e['nombres']) ?></strong></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($e['telefono'] ?? '-') ?></td>
                    <td style="padding: 12px;">
                        <span style="padding: 3px 10px; border-radius: 12px; font-size: 12px; <?= $e['estado'] === 'Activo' ? 'background: #d4edda; color: #155724;' : 'background: #f8d7da; color: #721c24;' ?>">
                            <?= htmlspecialchars($e['estado']) ?>
                        </span>
                    </td>
                    <td style="padding: 12px;">
                        <?php if (can('/estudiantes', 'EDITAR')): ?>
                            <a href="/ue-ameliarios/public/index.php/estudiantes/editar/<?= $e['id_estudiante'] ?>" style="color: #2c5aa0; margin-right: 10px;">Editar</a>
                        <?php endif; ?>
                        <?php if (can('/estudiantes', 'ELIMINAR')): ?>
                            <a href="/ue-ameliarios/public/index.php/estudiantes/eliminar/<?= $e['id_estudiante'] ?>" style="color: #e74c3c;" onclick="return confirm('¿Eliminar al estudiante? Se verificarán inscripciones activas.')">Eliminar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /estudiantes/crear (GET y POST)
// ==========================================
if ($uri === '/estudiantes/crear') {
    requireLogin(); requirePermission('/estudiantes', 'CREAR');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cedula = trim($_POST['cedula'] ?? '');
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo_personal = trim($_POST['correo_personal'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $codigo_estudiante = trim($_POST['codigo_estudiante'] ?? '');
        $fecha_ingreso = trim($_POST['fecha_ingreso'] ?? '');

        if (empty($cedula) || empty($nombres) || empty($apellidos) || empty($codigo_estudiante) || empty($fecha_ingreso)) {
            header('Location: /ue-ameliarios/public/index.php/estudiantes/crear?error=Campos+obligatorios+vacios'); exit;
        }

        try {
            db()->beginTransaction();

            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM persona WHERE cedula = :c");
            $stmt->execute(['c' => $cedula]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("La cédula ya está registrada en el sistema.");

            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM estudiante WHERE codigo_estudiante = :c");
            $stmt->execute(['c' => $codigo_estudiante]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("El código de estudiante ya existe.");

            db()->prepare("INSERT INTO persona (cedula, nombres, apellidos, fecha_nacimiento, sexo, telefono, correo_personal, direccion, estado) VALUES (:cedula, :nombres, :apellidos, :fecha_nacimiento, :sexo, :telefono, :correo_personal, :direccion, 'Activo')")
                ->execute(['cedula' => $cedula, 'nombres' => $nombres, 'apellidos' => $apellidos, 'fecha_nacimiento' => $fecha_nacimiento ?: null, 'sexo' => $sexo ?: null, 'telefono' => $telefono ?: null, 'correo_personal' => $correo_personal ?: null, 'direccion' => $direccion ?: null]);
            $id_persona = db()->lastInsertId();

            db()->prepare("INSERT INTO estudiante (id_persona, codigo_estudiante, fecha_ingreso, estado) VALUES (:id_persona, :codigo_estudiante, :fecha_ingreso, 'Activo')")
                ->execute(['id_persona' => $id_persona, 'codigo_estudiante' => $codigo_estudiante, 'fecha_ingreso' => $fecha_ingreso]);
            $id_estudiante = db()->lastInsertId();

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'CREAR_ESTUDIANTE', 'estudiante', :r, NOW())")
                ->execute(['u' => $uid, 'r' => $id_estudiante]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/estudiantes?exito=Estudiante+creado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            header('Location: /ue-ameliarios/public/index.php/estudiantes/crear?error=' . urlencode($e->getMessage())); exit;
        }
    }

    // GET: Mostrar formulario
    renderHeader('Nuevo Estudiante');
    ?>
    <div class="card" style="max-width: 900px; margin: 0 auto;">
        <h2>Crear Nuevo Estudiante</h2>
        <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
        
        <form method="POST" action="/ue-ameliarios/public/index.php/estudiantes/crear">
            <h3 style="color: var(--primary-color); margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Datos Personales</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div><label style="font-weight: bold; font-size: 14px;">Cédula *</label>
                    <input type="text" name="cedula" placeholder="V-12345678" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Fecha de Nacimiento</label>
                    <input type="date" name="fecha_nacimiento" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Nombres *</label>
                    <input type="text" name="nombres" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Apellidos *</label>
                    <input type="text" name="apellidos" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Sexo</label>
                    <select name="sexo" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Seleccionar --</option>
                        <option value="M">Masculino</option>
                        <option value="F">Femenino</option>
                        <option value="Otro">Otro</option>
                    </select></div>
                <div><label style="font-weight: bold; font-size: 14px;">Teléfono</label>
                    <input type="text" name="telefono" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            </div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Correo Personal</label>
                <input type="text" name="correo_personal" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Dirección</label>
                <textarea name="direccion" rows="2" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit;"></textarea></div>

            <h3 style="color: var(--primary-color); margin: 25px 0 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Datos Académicos</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div><label style="font-weight: bold; font-size: 14px;">Código de Estudiante *</label>
                    <input type="text" name="codigo_estudiante" placeholder="EST-2025-001" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Fecha de Ingreso *</label>
                    <input type="date" name="fecha_ingreso" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Crear Estudiante</button>
                <a href="/ue-ameliarios/public/index.php/estudiantes" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /estudiantes/editar (GET y POST)
// ==========================================
if ($uri === '/estudiantes/editar') {
    requireLogin(); requirePermission('/estudiantes', 'EDITAR');
    
    // ✅ CORRECCIÓN CLAVE: Usar $routeParams en lugar de preg_match
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID de estudiante no válido.");

    $stmt = db()->prepare("
        SELECT e.id_estudiante, e.codigo_estudiante, e.fecha_ingreso, e.estado,
               p.id_persona, p.cedula, p.nombres, p.apellidos, p.fecha_nacimiento,
               p.sexo, p.telefono, p.correo_personal, p.direccion
        FROM estudiante e
        INNER JOIN persona p ON e.id_persona = p.id_persona
        WHERE e.id_estudiante = :id LIMIT 1
    ");
    $stmt->execute(['id' => $id]);
    $estudiante = $stmt->fetch();

    if (!$estudiante) die("Error: Estudiante no encontrado.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cedula = trim($_POST['cedula'] ?? '');
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo_personal = trim($_POST['correo_personal'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $codigo_estudiante = trim($_POST['codigo_estudiante'] ?? '');
        $fecha_ingreso = trim($_POST['fecha_ingreso'] ?? '');
        $estado = trim($_POST['estado'] ?? 'Activo');

        if (empty($cedula) || empty($nombres) || empty($apellidos) || empty($codigo_estudiante) || empty($fecha_ingreso)) {
            die("Error: Los campos obligatorios deben completarse.");
        }

        try {
            db()->beginTransaction();

            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM persona WHERE cedula = :c AND id_persona != :idp");
            $stmt->execute(['c' => $cedula, 'idp' => $estudiante['id_persona']]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("La cédula ya está registrada en otra persona.");

            $stmt = db()->prepare("SELECT COUNT(*) AS total FROM estudiante WHERE codigo_estudiante = :c AND id_estudiante != :id");
            $stmt->execute(['c' => $codigo_estudiante, 'id' => $id]);
            if ($stmt->fetch()['total'] > 0) throw new Exception("El código de estudiante ya existe.");

            db()->prepare("UPDATE persona SET nombres = :nombres, apellidos = :apellidos, fecha_nacimiento = :fecha_nacimiento, sexo = :sexo, telefono = :telefono, correo_personal = :correo_personal, direccion = :direccion WHERE id_persona = :id_persona")
                ->execute(['nombres' => $nombres, 'apellidos' => $apellidos, 'fecha_nacimiento' => $fecha_nacimiento ?: null, 'sexo' => $sexo ?: null, 'telefono' => $telefono ?: null, 'correo_personal' => $correo_personal ?: null, 'direccion' => $direccion ?: null, 'id_persona' => $estudiante['id_persona']]);

            db()->prepare("UPDATE estudiante SET codigo_estudiante = :codigo_estudiante, fecha_ingreso = :fecha_ingreso, estado = :estado WHERE id_estudiante = :id")
                ->execute(['codigo_estudiante' => $codigo_estudiante, 'fecha_ingreso' => $fecha_ingreso, 'estado' => $estado, 'id' => $id]);

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'EDITAR_ESTUDIANTE', 'estudiante', :r, NOW())")
                ->execute(['u' => $uid, 'r' => $id]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/estudiantes?exito=Estudiante+actualizado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            die("Error al guardar: " . $e->getMessage());
        }
    }

    // GET: Mostrar formulario
    renderHeader('Editar Estudiante');
    ?>
    <div class="card" style="max-width: 900px; margin: 0 auto;">
        <h2>Editar Estudiante: <?= htmlspecialchars($estudiante['nombres'] . ' ' . $estudiante['apellidos']) ?></h2>
        <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
        
        <form method="POST" action="/ue-ameliarios/public/index.php/estudiantes/editar/<?= $id ?>">
            <h3 style="color: var(--primary-color); margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Datos Personales</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div><label style="font-weight: bold; font-size: 14px;">Cédula *</label>
                    <input type="text" name="cedula" value="<?= htmlspecialchars($estudiante['cedula']) ?>" required readonly style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: #f8fafc;" title="La cédula no se puede modificar"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Fecha de Nacimiento</label>
                    <input type="date" name="fecha_nacimiento" value="<?= htmlspecialchars($estudiante['fecha_nacimiento'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Nombres *</label>
                    <input type="text" name="nombres" value="<?= htmlspecialchars($estudiante['nombres']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Apellidos *</label>
                    <input type="text" name="apellidos" value="<?= htmlspecialchars($estudiante['apellidos']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Sexo</label>
                    <select name="sexo" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Seleccionar --</option>
                        <option value="M" <?= ($estudiante['sexo'] ?? '') === 'M' ? 'selected' : '' ?>>Masculino</option>
                        <option value="F" <?= ($estudiante['sexo'] ?? '') === 'F' ? 'selected' : '' ?>>Femenino</option>
                        <option value="Otro" <?= ($estudiante['sexo'] ?? '') === 'Otro' ? 'selected' : '' ?>>Otro</option>
                    </select></div>
                <div><label style="font-weight: bold; font-size: 14px;">Teléfono</label>
                    <input type="text" name="telefono" value="<?= htmlspecialchars($estudiante['telefono'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            </div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Correo Personal</label>
                <input type="text" name="correo_personal" value="<?= htmlspecialchars($estudiante['correo_personal'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Dirección</label>
                <textarea name="direccion" rows="2" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: inherit;"><?= htmlspecialchars($estudiante['direccion'] ?? '') ?></textarea></div>

            <h3 style="color: var(--primary-color); margin: 25px 0 15px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Datos Académicos</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div><label style="font-weight: bold; font-size: 14px;">Código de Estudiante *</label>
                    <input type="text" name="codigo_estudiante" value="<?= htmlspecialchars($estudiante['codigo_estudiante']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Fecha de Ingreso *</label>
                    <input type="date" name="fecha_ingreso" value="<?= htmlspecialchars($estudiante['fecha_ingreso']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            </div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Estado *</label>
                <select name="estado" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <option value="Activo" <?= $estudiante['estado'] === 'Activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="Inactivo" <?= $estudiante['estado'] === 'Inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    <option value="Egresado" <?= $estudiante['estado'] === 'Egresado' ? 'selected' : '' ?>>Egresado</option>
                    <option value="Retirado" <?= $estudiante['estado'] === 'Retirado' ? 'selected' : '' ?>>Retirado</option>
                </select></div>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Guardar Cambios</button>
                <a href="/ue-ameliarios/public/index.php/estudiantes" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /estudiantes/eliminar (GET)
// ==========================================
if ($uri === '/estudiantes/eliminar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin(); requirePermission('/estudiantes', 'ELIMINAR');
    
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID de estudiante no válido.");

    $stmt = db()->prepare("SELECT e.id_estudiante, e.id_persona, e.codigo_estudiante, p.nombres, p.apellidos FROM estudiante e INNER JOIN persona p ON e.id_persona = p.id_persona WHERE e.id_estudiante = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $estudiante = $stmt->fetch();

    if (!$estudiante) die("Error: Estudiante no encontrado.");

    // ✅ TEST-01: Integridad Referencial (No borrar si tiene inscripciones)
    $stmt = db()->prepare("SELECT COUNT(*) AS total FROM inscripcion WHERE id_estudiante = :id");
    $stmt->execute(['id' => $id]);
    if ($stmt->fetch()['total'] > 0) {
        die("Error: No se puede eliminar. El estudiante tiene inscripciones activas. Retírelo primero.");
    }

    try {
        db()->beginTransaction();

        db()->prepare("DELETE FROM estudiante WHERE id_estudiante = :id")->execute(['id' => $id]);
        db()->prepare("DELETE FROM persona WHERE id_persona = :idp")->execute(['idp' => $estudiante['id_persona']]);

        $uid = (int)($_SESSION['user']['id'] ?? 0);
        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'ELIMINAR_ESTUDIANTE', 'estudiante', :r, NOW())")
            ->execute(['u' => $uid, 'r' => $id]);

        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/estudiantes?exito=Estudiante+eliminado+exitosamente'); exit;
    } catch (Exception $e) {
        db()->rollBack();
        die("Error al eliminar: " . $e->getMessage());
    }
}