<?php
/**
 * app/representantes.php
 * Versión Final y Completa: Guardado, Auditoría y Visualización de Cédula/Teléfono.
 */
require_once __DIR__ . '/layout.php';

// ==========================================
// /representantes (GET) — Listado
// ==========================================
if ($uri === '/representantes' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/representantes', 'VER');

    $representantes = db()->query("
        SELECT r.id_representante, r.parentesco, r.ocupacion, p.cedula, p.nombres, p.apellidos, p.telefono
        FROM representante r INNER JOIN persona p ON r.id_persona = p.id_persona
        ORDER BY p.apellidos ASC
    ")->fetchAll();

    renderHeader('Gestión de Representantes');
    ?>
    <div class="card" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">Representantes del Sistema</h2>
        <?php if (can('/representantes', 'CREAR')): ?>
        <a href="/ue-ameliarios/public/index.php/representantes/crear" style="padding: 10px 20px; background: var(--primary-color); color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">+ Nuevo Representante</a>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['exito'])): ?><div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px;">✅ <?= htmlspecialchars($_GET['exito']) ?></div><?php endif; ?>
    <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>

    <div class="card" style="padding: 0; overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
            <!-- ✅ AGREGADA COLUMNA DE TELÉFONO -->
            <thead><tr style="background: var(--primary-color); color: white;">
                <th style="padding: 12px; text-align: left;">Cédula</th>
                <th style="padding: 12px; text-align: left;">Nombre</th>
                <th style="padding: 12px; text-align: left;">Teléfono</th>
                <th style="padding: 12px; text-align: left;">Parentesco</th>
                <th style="padding: 12px; text-align: left;">Acciones</th>
            </tr></thead>
            <tbody>
                <?php foreach ($representantes as $r): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px;"><?= htmlspecialchars($r['cedula']) ?></td>
                    <td style="padding: 12px;"><strong><?= htmlspecialchars($r['apellidos'] . ', ' . $r['nombres']) ?></strong></td>
                    <td style="padding: 12px;"><?= htmlspecialchars($r['telefono'] ?? '-') ?></td> <!-- ✅ AQUÍ SE MUESTRA EL TELÉFONO -->
                    <td style="padding: 12px;"><?= htmlspecialchars($r['parentesco'] ?? '-') ?></td>
                    <td style="padding: 12px;">
                        <?php if (can('/representantes', 'EDITAR')): ?>
                            <a href="/ue-ameliarios/public/index.php/representantes/editar/<?= $r['id_representante'] ?>" style="color: #2c5aa0; margin-right: 10px;">Editar</a>
                        <?php endif; ?>
                        <?php if (can('/representantes', 'ELIMINAR')): ?>
                            <a href="/ue-ameliarios/public/index.php/representantes/eliminar/<?= $r['id_representante'] ?>" style="color: #e74c3c;" onclick="return confirm('¿Eliminar?')">Eliminar</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /representantes/crear (GET y POST)
// ==========================================
if ($uri === '/representantes/crear') {
    requireLogin(); requirePermission('/representantes', 'CREAR');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cedula = trim($_POST['cedula'] ?? '');
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $parentesco = trim($_POST['parentesco'] ?? '');
        $ocupacion = trim($_POST['ocupacion'] ?? '');
        $estudiantes = $_POST['estudiantes'] ?? [];

        if (empty($cedula) || empty($nombres) || empty($apellidos)) {
            header('Location: /ue-ameliarios/public/index.php/representantes/crear?error=Campos+obligatorios+vacios'); exit;
        }

        try {
            db()->beginTransaction();
            $stmt = db()->prepare("SELECT id_persona FROM persona WHERE cedula = :c LIMIT 1");
            $stmt->execute(['c' => $cedula]);
            $persona = $stmt->fetch();

            if ($persona) {
                $id_persona = $persona['id_persona'];
                if (db()->query("SELECT 1 FROM representante WHERE id_persona = $id_persona LIMIT 1")->fetch()) {
                    throw new Exception("Esta persona ya es representante.");
                }
            } else {
                db()->prepare("INSERT INTO persona (cedula, nombres, apellidos, telefono, correo_personal, estado) VALUES (:c, :n, :a, :t, :co, 'Activo')")
                    ->execute(['c' => $cedula, 'n' => $nombres, 'a' => $apellidos, 't' => $telefono, 'co' => $correo]);
                $id_persona = db()->lastInsertId();
            }

            db()->prepare("INSERT INTO representante (id_persona, parentesco, ocupacion) VALUES (:id, :p, :o)")
                ->execute(['id' => $id_persona, 'p' => $parentesco, 'o' => $ocupacion]);
            $id_rep = db()->lastInsertId();

            if (!empty($estudiantes)) {
                $stmt = db()->prepare("INSERT INTO representante_estudiante (id_representante, id_estudiante, es_principal) VALUES (:r, :e, 0)");
                foreach ($estudiantes as $est_id) {
                    $stmt->execute(['r' => $id_rep, 'e' => (int)$est_id]);
                }
            }

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'CREAR_REPRESENTANTE', 'representante', :r, NOW())")
                ->execute(['u' => $uid, 'r' => $id_rep]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/representantes?exito=Creado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            header('Location: /ue-ameliarios/public/index.php/representantes/crear?error=' . urlencode($e->getMessage())); exit;
        }
    }

    $cedula_buscar = trim($_GET['cedula_rep'] ?? '');
    $persona_existente = null;
    if ($cedula_buscar !== '') {
        $stmt = db()->prepare("SELECT cedula, nombres, apellidos, telefono, correo_personal FROM persona WHERE cedula = :c LIMIT 1");
        $stmt->execute(['c' => $cedula_buscar]);
        $persona_existente = $stmt->fetch();
    }

    $estudiantes = db()->query("SELECT e.id_estudiante, e.codigo_estudiante, p.cedula, p.nombres, p.apellidos FROM estudiante e INNER JOIN persona p ON e.id_persona = p.id_persona WHERE e.estado = 'Activo' ORDER BY p.apellidos ASC")->fetchAll();

    renderHeader('Nuevo Representante');
    ?>
    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <h2>Crear Nuevo Representante</h2>
        <?php if (isset($_GET['error'])): ?><div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
        
        <div style="background: #eef2ff; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <form method="GET" action="/ue-ameliarios/public/index.php/representantes/crear" style="display: flex; gap: 10px;">
                <input type="text" name="cedula_rep" placeholder="Buscar persona por Cédula..." value="<?= htmlspecialchars($cedula_buscar) ?>" style="flex: 1; padding: 10px; border: 1px solid #a5b4fc; border-radius: 4px;">
                <button type="submit" style="padding: 10px 20px; background: #4f46e5; color: white; border: none; border-radius: 4px; font-weight: bold;">🔍 Buscar</button>
            </form>
        </div>

        <form method="POST" action="/ue-ameliarios/public/index.php/representantes/crear">
            <h3 style="color: var(--primary-color); margin-bottom: 15px;">Datos Personales</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div><label style="font-weight: bold; font-size: 14px;">Cédula *</label>
                    <input type="text" name="cedula" value="<?= htmlspecialchars($persona_existente['cedula'] ?? $cedula_buscar) ?>" required <?= $persona_existente ? 'readonly style="background: #e9ecef;"' : '' ?> style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Teléfono</label>
                    <input type="text" name="telefono" value="<?= htmlspecialchars($persona_existente['telefono'] ?? '') ?>" <?= $persona_existente ? 'readonly style="background: #e9ecef;"' : '' ?> style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Nombres *</label>
                    <input type="text" name="nombres" value="<?= htmlspecialchars($persona_existente['nombres'] ?? '') ?>" required <?= $persona_existente ? 'readonly style="background: #e9ecef;"' : '' ?> style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Apellidos *</label>
                    <input type="text" name="apellidos" value="<?= htmlspecialchars($persona_existente['apellidos'] ?? '') ?>" required <?= $persona_existente ? 'readonly style="background: #e9ecef;"' : '' ?> style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            </div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Correo</label>
                <input type="text" name="correo" value="<?= htmlspecialchars($persona_existente['correo_personal'] ?? '') ?>" <?= $persona_existente ? 'readonly style="background: #e9ecef;"' : '' ?> style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>

            <h3 style="color: var(--primary-color); margin: 25px 0 15px;">Datos de Representación</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div><label style="font-weight: bold; font-size: 14px;">Parentesco</label>
                    <select name="parentesco" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach (['Madre', 'Padre', 'Abuelo/a', 'Tío/a', 'Tutor/a', 'Otro'] as $op): ?><option value="<?= $op ?>"><?= $op ?></option><?php endforeach; ?>
                    </select></div>
                <div><label style="font-weight: bold; font-size: 14px;">Ocupación</label>
                    <input type="text" name="ocupacion" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; font-size: 14px; display: block; margin-bottom: 8px;">Estudiantes Representados (Mantén Ctrl presionado para seleccionar varios)</label>
                <select name="estudiantes[]" multiple size="6" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <?php foreach ($estudiantes as $e): ?>
                        <option value="<?= $e['id_estudiante'] ?>"><?= htmlspecialchars($e['cedula'] . ' - ' . $e['apellidos'] . ', ' . $e['nombres']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Guardar</button>
                <a href="/ue-ameliarios/public/index.php/representantes" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /representantes/editar (GET y POST)
// ==========================================
if ($uri === '/representantes/editar') {
    requireLogin(); requirePermission('/representantes', 'EDITAR');
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID no válido.");

    $stmt = db()->prepare("SELECT r.id_representante, r.parentesco, r.ocupacion, p.id_persona, p.cedula, p.nombres, p.apellidos, p.telefono, p.correo_personal FROM representante r INNER JOIN persona p ON r.id_persona = p.id_persona WHERE r.id_representante = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $rep = $stmt->fetch();
    if (!$rep) die("Error: No encontrado.");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cedula = trim($_POST['cedula'] ?? '');
        $nombres = trim($_POST['nombres'] ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $parentesco = trim($_POST['parentesco'] ?? '');
        $ocupacion = trim($_POST['ocupacion'] ?? '');
        $estudiantes = $_POST['estudiantes'] ?? [];

        try {
            db()->beginTransaction();
            db()->prepare("UPDATE persona SET nombres = :n, apellidos = :a, telefono = :t, correo_personal = :c WHERE id_persona = :id")
                ->execute(['n' => $nombres, 'a' => $apellidos, 't' => $telefono, 'c' => $correo, 'id' => $rep['id_persona']]);
            
            db()->prepare("UPDATE representante SET parentesco = :p, ocupacion = :o WHERE id_representante = :id")
                ->execute(['p' => $parentesco, 'o' => $ocupacion, 'id' => $id]);

            db()->prepare("DELETE FROM representante_estudiante WHERE id_representante = :id")->execute(['id' => $id]);

            if (!empty($estudiantes)) {
                $stmt = db()->prepare("INSERT INTO representante_estudiante (id_representante, id_estudiante, es_principal) VALUES (:r, :e, 0)");
                foreach ($estudiantes as $est_id) {
                    $stmt->execute(['r' => $id, 'e' => (int)$est_id]);
                }
            }

            $uid = (int)($_SESSION['user']['id'] ?? 0);
            db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'EDITAR_REPRESENTANTE', 'representante', :r, NOW())")
                ->execute(['u' => $uid, 'r' => $id]);

            db()->commit();
            header('Location: /ue-ameliarios/public/index.php/representantes?exito=Actualizado+exitosamente'); exit;
        } catch (Exception $e) {
            db()->rollBack();
            die("Error al guardar: " . $e->getMessage());
        }
    }

    $estudiantes = db()->query("SELECT e.id_estudiante, e.codigo_estudiante, p.cedula, p.nombres, p.apellidos FROM estudiante e INNER JOIN persona p ON e.id_persona = p.id_persona WHERE e.estado = 'Activo' ORDER BY p.apellidos ASC")->fetchAll();
    $stmtSel = db()->prepare("SELECT id_estudiante FROM representante_estudiante WHERE id_representante = :id");
    $stmtSel->execute(['id' => $id]);
    $asignados = array_column($stmtSel->fetchAll(), 'id_estudiante');

    renderHeader('Editar Representante');
    ?>
    <div class="card" style="max-width: 800px; margin: 0 auto;">
        <h2>Editar Representante</h2>
        <form method="POST" action="/ue-ameliarios/public/index.php/representantes/editar/<?= $id ?>">
            <h3 style="color: var(--primary-color); margin-bottom: 15px;">Datos Personales</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div><label style="font-weight: bold; font-size: 14px;">Cédula *</label>
                    <input type="text" name="cedula" value="<?= htmlspecialchars($rep['cedula']) ?>" required readonly style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; background: #e9ecef;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Teléfono</label>
                    <input type="text" name="telefono" value="<?= htmlspecialchars($rep['telefono'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Nombres *</label>
                    <input type="text" name="nombres" value="<?= htmlspecialchars($rep['nombres']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
                <div><label style="font-weight: bold; font-size: 14px;">Apellidos *</label>
                    <input type="text" name="apellidos" value="<?= htmlspecialchars($rep['apellidos']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            </div>
            <div style="margin-bottom: 15px;"><label style="font-weight: bold; font-size: 14px;">Correo</label>
                <input type="text" name="correo" value="<?= htmlspecialchars($rep['correo_personal'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>

            <h3 style="color: var(--primary-color); margin: 25px 0 15px;">Datos de Representación</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                <div><label style="font-weight: bold; font-size: 14px;">Parentesco</label>
                    <select name="parentesco" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach (['Madre', 'Padre', 'Abuelo/a', 'Tío/a', 'Tutor/a', 'Otro'] as $op): ?>
                            <option value="<?= $op ?>" <?= $rep['parentesco'] === $op ? 'selected' : '' ?>><?= $op ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div><label style="font-weight: bold; font-size: 14px;">Ocupación</label>
                    <input type="text" name="ocupacion" value="<?= htmlspecialchars($rep['ocupacion'] ?? '') ?>" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;"></div>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; font-size: 14px; display: block; margin-bottom: 8px;">Estudiantes Representados (Mantén Ctrl presionado para seleccionar varios)</label>
                <select name="estudiantes[]" multiple size="6" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px;">
                    <?php foreach ($estudiantes as $e): ?>
                        <option value="<?= $e['id_estudiante'] ?>" <?= in_array($e['id_estudiante'], $asignados) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($e['cedula'] . ' - ' . $e['apellidos'] . ', ' . $e['nombres']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 4px; font-weight: 600; cursor: pointer;">💾 Guardar Cambios</button>
                <a href="/ue-ameliarios/public/index.php/representantes" style="padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">Cancelar</a>
            </div>
        </form>
    </div>
    <?php renderFooter(); exit; }

// ==========================================
// /representantes/eliminar (GET)
// ==========================================
if ($uri === '/representantes/eliminar' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin(); requirePermission('/representantes', 'ELIMINAR');
    $id = $routeParams['id'] ?? 0;
    if ($id === 0) die("Error: ID no válido.");

    $stmt = db()->prepare("SELECT r.id_persona, p.nombres, p.apellidos FROM representante r INNER JOIN persona p ON r.id_persona = p.id_persona WHERE r.id_representante = :id LIMIT 1");
    $stmt->execute(['id' => $id]);
    $rep = $stmt->fetch();
    if (!$rep) die("Error: No encontrado.");

    try {
        db()->beginTransaction();
        db()->prepare("DELETE FROM representante_estudiante WHERE id_representante = :id")->execute(['id' => $id]);
        db()->prepare("DELETE FROM representante WHERE id_representante = :id")->execute(['id' => $id]);
        db()->prepare("DELETE FROM persona WHERE id_persona = :idp")->execute(['idp' => $rep['id_persona']]);
        
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        db()->prepare("INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora) VALUES (:u, 'ELIMINAR_REPRESENTANTE', 'representante', :r, NOW())")->execute(['u' => $uid, 'r' => $id]);
        
        db()->commit();
        header('Location: /ue-ameliarios/public/index.php/representantes?exito=Eliminado+exitosamente'); exit;
    } catch (Exception $e) {
        db()->rollBack();
        die("Error al eliminar: " . $e->getMessage());
    }
}