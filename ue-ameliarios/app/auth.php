<?php
/**
 * app/auth.php
 * Login, logout y dashboard con soporte 100% dinámico para TODOS los roles y módulos.
 */

require_once __DIR__ . '/layout.php';

// ==========================================
// /login (GET)
// ==========================================
if ($uri === '/login' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Login — U.E. Amelia Ríos</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); width: 380px; }
            .login h2 { text-align: center; color: #1e3a8a; margin-top: 0; margin-bottom: 30px; font-size: 1.5rem; }
            .login input { width: 100%; padding: 12px 15px; margin-bottom: 15px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; font-size: 15px; }
            .login input:focus { outline: none; border-color: #3b82f6; }
            .login button { width: 100%; padding: 12px; background: #1e3a8a; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; transition: background 0.2s; }
            .login button:hover { background: #3b82f6; }
            .error { color: #e53e3e; text-align: center; margin-bottom: 15px; background: #fed7d7; padding: 10px; border-radius: 6px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="login">
            <h2>Iniciar Sesión</h2>
            <?php if (isset($_GET['error'])): ?>
                <p class="error">Usuario o contraseña incorrectos</p>
            <?php endif; ?>
            <form method="POST" action="/ue-ameliarios/public/index.php/login">
                <input type="text" name="username" placeholder="Usuario" required autofocus>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit">Ingresar</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ==========================================
// /login (POST) - Validación RBAC Jerárquico
// ==========================================
if ($uri === '/login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare("
        SELECT u.id_usuario, u.username, u.correo, u.password_hash, p.nombres, p.apellidos
        FROM usuario u
        INNER JOIN persona p ON u.id_persona = p.id_persona
        WHERE (u.username = :u OR u.correo = :u) AND u.activo = 1
        LIMIT 1
    ");
    $stmt->execute(['u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        header('Location: /ue-ameliarios/public/index.php/login?error=1');
        exit;
    }

    $stmt = db()->prepare("
        SELECT r.nombre
        FROM usuario_rol ur
        INNER JOIN rol r ON ur.id_rol = r.id_rol
        WHERE ur.id_usuario = :id
    ");
    $stmt->execute(['id' => $user['id_usuario']]);
    $roles = array_column($stmt->fetchAll(), 'nombre');

    $stmt = db()->prepare("
        SELECT m.nombre_modulo, v.ruta_acceso, p.nombre_permiso
        FROM usuario u
        JOIN usuario_rol ur ON u.id_usuario = ur.id_usuario
        JOIN r_modulo_rol mr ON ur.id_rol = mr.rol_id
        JOIN vistas_permiso vp ON mr.id = vp.r_modulo_rol_id
        JOIN vistas v ON vp.vista_id = v.id
        JOIN permisos p ON vp.permiso_id = p.id
        JOIN modulo m ON mr.modulo_id = m.id
        WHERE u.id_usuario = :id AND mr.habilitado = 1 AND vp.permitido = 1
    ");
    $stmt->execute(['id' => $user['id_usuario']]);
    $permisosRaw = $stmt->fetchAll();

    $permisosEstructurados = [];
    $modulosPermitidos = [];
    foreach ($permisosRaw as $p) {
        $permisosEstructurados[$p['ruta_acceso']][$p['nombre_permiso']] = true;
        $modulosPermitidos[] = $p['nombre_modulo'];
    }

    $_SESSION['user'] = [
        'id'         => $user['id_usuario'],
        'username'   => $user['username'],
        'correo'     => $user['correo'],
        'nombres'    => $user['nombres'], 
        'apellidos'  => $user['apellidos'], 
        'roles'      => $roles,
        'permisos'   => $permisosEstructurados,
        'modulos'    => array_unique($modulosPermitidos)
    ];

    header('Location: /ue-ameliarios/public/index.php/dashboard');
    exit;
}

// ==========================================
// /logout
// ==========================================
if ($uri === '/logout') {
    session_destroy();
    header('Location: /ue-ameliarios/public/index.php/login');
    exit;
}

// ==========================================
// /dashboard (HTML)
// ==========================================
if ($uri === '/dashboard') {
    requireLogin();

    $user = $_SESSION['user'];
    $rolPrincipal = primaryRole();

    renderHeader('Panel Principal');
    ?>

    <!-- Tarjeta de Bienvenida (Para todos los roles) -->
    <div style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: white; padding: 40px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 10px 25px rgba(30, 58, 138, 0.2);">
        <h2 style="color: white; font-size: 2rem; margin-bottom: 10px;">¡Bienvenido/a, <?= htmlspecialchars($user['nombres']) ?>! 👋</h2>
        <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem; margin-bottom: 20px;">
            Te damos la bienvenida a nuestro <strong>Portal Web Institucional</strong> de la U.E. Amelia Ríos.
        </p>
        <p style="color: rgba(255,255,255,0.8); font-size: 0.95rem;">
            Rol principal: <span style="background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; font-weight: 600; margin-left: 5px;"><?= htmlspecialchars($rolPrincipal) ?></span>
        </p>
    </div>

    <!-- ========================================== -->
    <!-- PANEL ADMINISTRADOR                        -->
    <!-- ========================================== -->
    <?php if (hasRole('Administrador')): ?>
        <h3 style="color: #1e3a8a; margin: 25px 0 15px; font-size: 1.2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">📊 Resumen Visual</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #3b82f6;">
                <div style="font-size: 2rem; font-weight: 700; color: #1e3a8a;" id="stat-estudiantes">--</div>
                <div style="font-size: 0.9rem; color: #6b7280; margin-top: 5px;">Estudiantes Activos</div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #10b981;">
                <div style="font-size: 2rem; font-weight: 700; color: #1e3a8a;" id="stat-docentes">--</div>
                <div style="font-size: 0.9rem; color: #6b7280; margin-top: 5px;">Docentes</div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #f59e0b;">
                <div style="font-size: 2rem; font-weight: 700; color: #1e3a8a;" id="stat-asistencias">--</div>
                <div style="font-size: 0.9rem; color: #6b7280; margin-top: 5px;">Asistencias Hoy</div>
            </div>
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #ef4444;">
                <div style="font-size: 2rem; font-weight: 700; color: #1e3a8a;" id="stat-pendientes">--</div>
                <div style="font-size: 0.9rem; color: #6b7280; margin-top: 5px;">Justificaciones Pendientes</div>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <h4 style="color: #1e3a8a; margin-bottom: 20px; font-size: 1.1rem;">Asistencias por Estado (Últimos 30 días)</h4>
                <div style="max-width: 300px; margin: 0 auto;"><canvas id="graficoDoughnut"></canvas></div>
            </div>
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <h4 style="color: #1e3a8a; margin-bottom: 20px; font-size: 1.1rem;">Asistencias Hoy</h4>
                <canvas id="graficoLineas" height="150"></canvas>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', async () => {
                try {
                    const res = await fetch('/ue-ameliarios/app/estadisticas.php'); 
                    if (!res.ok) throw new Error('Error HTTP');
                    const data = await res.json();
                    document.getElementById('stat-estudiantes').textContent = data.total_estudiantes ?? '0';
                    document.getElementById('stat-docentes').textContent = data.total_docentes ?? '0';
                    document.getElementById('stat-asistencias').textContent = data.asistencias_hoy ?? '0';
                    document.getElementById('stat-pendientes').textContent = data.justificaciones_pendientes ?? '0';

                    const labels = data.asistencias_por_estado.map(item => item.estado);
                    const values = data.asistencias_por_estado.map(item => item.total);

                    new Chart(document.getElementById('graficoDoughnut').getContext('2d'), {
                        type: 'doughnut',
                        data: { labels: labels.length > 0 ? labels : ['Sin datos'], datasets: [{ data: values.length > 0 ? values : [0], backgroundColor: ['rgba(16, 185, 129, 0.8)', 'rgba(239, 68, 68, 0.8)', 'rgba(245, 158, 11, 0.8)', 'rgba(59, 130, 246, 0.8)'], borderColor: '#fff', borderWidth: 2 }] },
                        options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '60%' }
                    });
                    new Chart(document.getElementById('graficoLineas').getContext('2d'), {
                        type: 'line',
                        data: { labels: labels.length > 0 ? labels : ['Sin datos'], datasets: [{ label: 'Cantidad', data: values.length > 0 ? values : [0], borderColor: '#3b82f6', backgroundColor: 'rgba(59, 130, 246, 0.1)', borderWidth: 2, pointBackgroundColor: '#1e3a8a', pointRadius: 6, fill: true, tension: 0.4 }] },
                        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                    });
                } catch (error) { console.error('Error:', error); }
            });
        </script>

        <h3 style="color: #1e3a8a; margin: 25px 0 15px; font-size: 1.2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">Administración</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px;">
            <?php if (hasModuleAccess('usuarios')): ?><a href="/ue-ameliarios/public/index.php/usuarios" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;"><div style="font-size: 28px; margin-bottom: 10px;">👥</div><div style="font-weight: 600;">Usuarios</div><div style="font-size: 13px; color: #777;">Gestionar usuarios</div></a><?php endif; ?>
            <a href="/ue-ameliarios/public/index.php/academico/grados" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;"><div style="font-size: 28px; margin-bottom: 10px;">📚</div><div style="font-weight: 600;">Académico</div><div style="font-size: 13px; color: #777;">Grados, secciones, materias</div></a>
            <a href="/ue-ameliarios/public/index.php/estudiantes" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;"><div style="font-size: 28px; margin-bottom: 10px;">🎓</div><div style="font-weight: 600;">Estudiantes</div><div style="font-size: 13px; color: #777;">Gestionar estudiantes</div></a>
            <a href="/ue-ameliarios/public/index.php/representantes" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;"><div style="font-size: 28px; margin-bottom: 10px;">👨‍👩‍👧</div><div style="font-weight: 600;">Representantes</div><div style="font-size: 13px; color: #777;">Gestionar representantes</div></a>
            <a href="/ue-ameliarios/public/index.php/inscripciones" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;"><div style="font-size: 28px; margin-bottom: 10px;">📝</div><div style="font-weight: 600;">Inscripciones</div><div style="font-size: 13px; color: #777;">Inscribir estudiantes</div></a>
            <a href="/ue-ameliarios/public/index.php/asistencia" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;"><div style="font-size: 28px; margin-bottom: 10px;">✅</div><div style="font-weight: 600;">Asistencia</div><div style="font-size: 13px; color: #777;">Registrar y consultar</div></a>
            <a href="/ue-ameliarios/public/index.php/eventos" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;"><div style="font-size: 28px; margin-bottom: 10px;">📅</div><div style="font-weight: 600;">Eventos</div><div style="font-size: 13px; color: #777;">Eventos institucionales</div></a>
            <?php if (hasModuleAccess('bitacora')): ?><a href="/ue-ameliarios/public/index.php/bitacora" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;"><div style="font-size: 28px; margin-bottom: 10px;">📋</div><div style="font-weight: 600;">Auditoría</div><div style="font-size: 13px; color: #777;">Registro de acciones</div></a><?php endif; ?>
        </div>

    <!-- ========================================== -->
    <!-- PANEL DOCENTE                              -->
    <!-- ========================================== -->
    <?php elseif (hasRole('Docente')): ?>
        <h3 style="color: #1e3a8a; margin: 25px 0 15px; font-size: 1.2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">👨‍🏫 Panel Docente</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px;">
            <?php if (hasModuleAccess('asistencia')): ?><a href="/ue-ameliarios/public/index.php/asistencia" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;"><div style="font-size: 32px; margin-bottom: 10px;">✅</div><div style="font-weight: 600;">Control de Asistencia</div><div style="font-size: 13px; color: #777;">Registrar y consultar</div></a><?php endif; ?>
            <?php if (hasModuleAccess('eventos')): ?><a href="/ue-ameliarios/public/index.php/eventos" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;"><div style="font-size: 32px; margin-bottom: 10px;">📅</div><div style="font-weight: 600;">Eventos</div><div style="font-size: 13px; color: #777;">Novedades institucionales</div></a><?php endif; ?>
        </div>

    <!-- ========================================== -->
    <!-- PANEL REPRESENTANTE                        -->
    <!-- ========================================== -->
    <?php elseif (hasRole('Representante')): ?>
        <h3 style="color: #1e3a8a; margin: 25px 0 15px; font-size: 1.2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">👨‍👩‍👧 Panel de Representante</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px;">
            <?php if (hasModuleAccess('justificaciones')): ?><a href="/ue-ameliarios/public/index.php/justificaciones" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;"><div style="font-size: 32px; margin-bottom: 10px;">📝</div><div style="font-weight: 600;">Justificaciones</div><div style="font-size: 13px; color: #777;">Enviar y revisar estado</div></a><?php endif; ?>
        </div>

    <!-- ========================================== -->
    <!-- PANEL ESTUDIANTE                           -->
    <!-- ========================================== -->
    <?php elseif (hasRole('Estudiante')): ?>
        <h3 style="color: #1e3a8a; margin: 25px 0 15px; font-size: 1.2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">🎓 Panel de Estudiante</h3>
        <div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center;">
            <div style="font-size: 48px; margin-bottom: 15px;">🎒</div>
            <h4 style="color: #1e3a8a; margin-bottom: 10px;">Tu Portal Académico</h4>
            <p style="color: #666; margin-bottom: 20px;">Aquí podrás consultar tu historial de asistencias, faltas justificadas y tu información académica.</p>
        </div>

    <!-- ========================================== -->
    <!-- PANEL 100% DINÁMICO (Roles y Módulos Nuevos) -->
    <!-- ========================================== -->
    <?php else: ?>
        <h3 style="color: #1e3a8a; margin: 25px 0 15px; font-size: 1.2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">🔧 Tus Módulos Asignados</h3>
        <p style="color: #666; margin-bottom: 20px;">
            Como usuario con rol <strong><?= htmlspecialchars($rolPrincipal) ?></strong>, tienes acceso a los siguientes módulos:
        </p>
        
        <?php 
        // Lógica 100% dinámica: Lee directamente de la BD los módulos asignados al usuario
        $modulos_usuario = $_SESSION['user']['modulos'] ?? [];
        $modulos_dinamicos = [];
        
        if (!empty($modulos_usuario)) {
            $placeholders = implode(',', array_fill(0, count($modulos_usuario), '?'));
            
            // Consulta que obtiene el nombre, icono, descripción y la primera ruta disponible de cada módulo
            $sql = "
                SELECT m.nombre_modulo, m.icono, m.descripcion, MIN(v.ruta_acceso) as ruta
                FROM modulo m
                LEFT JOIN r_modulo_rol rmr ON m.id = rmr.modulo_id
                LEFT JOIN vistas_permiso vp ON rmr.id = vp.r_modulo_rol_id AND vp.permitido = 1
                LEFT JOIN vistas v ON vp.vista_id = v.id
                WHERE m.nombre_modulo IN ($placeholders)
                GROUP BY m.id, m.nombre_modulo, m.icono, m.descripcion
                ORDER BY m.orden ASC
            ";
            $stmt = db()->prepare($sql);
            $stmt->execute($modulos_usuario);
            $modulos_dinamicos = $stmt->fetchAll();
        }
        ?>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px;">
            <?php foreach ($modulos_dinamicos as $mod): 
                // Si no hay ruta definida, usa el nombre del módulo en minúsculas como fallback
                $url_destino = $mod['ruta'] ? htmlspecialchars($mod['ruta']) : '/' . strtolower(htmlspecialchars($mod['nombre_modulo']));
            ?>
                <a href="/ue-ameliarios/public/index.php<?= $url_destino ?>" style="background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: #333; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; text-align: center; transition: transform 0.2s;">
                    <div style="font-size: 32px; margin-bottom: 10px;"><?= htmlspecialchars($mod['icono'] ?? '📁') ?></div>
                    <div style="font-weight: 600; font-size: 16px;"><?= htmlspecialchars(ucfirst($mod['nombre_modulo'])) ?></div>
                    <div style="font-size: 13px; color: #777; margin-top: 5px;"><?= htmlspecialchars($mod['descripcion'] ?? 'Módulo del sistema') ?></div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($modulos_dinamicos)): ?>
            <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 20px; border-radius: 6px; margin-top: 20px;">
                <p style="color: #92400e; margin: 0;"><strong>⚠️ No tienes módulos asignados.</strong><br>Contacta al administrador para que te asigne permisos.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php
    renderFooter();
    exit;
}