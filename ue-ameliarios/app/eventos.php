<?php
/**
 * app/eventos.php
 * Módulo de Eventos tipo "Muro Social" (Feed) con RBAC Jerárquico.
 * Cumple con la Sección 2 del Instructivo UNERMB.
 */

require_once __DIR__ . '/layout.php';

// ==========================================
// /eventos/crear (POST) — Guardar Evento
// ==========================================
if ($uri === '/eventos/crear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    requirePermission('/eventos', 'CREAR'); // ✅ RBAC JERÁRQUICO

    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $lugar = trim($_POST['lugar'] ?? '');
    $tipo = $_POST['tipo'] ?? 'Otro';

    if (empty($titulo) || empty($fecha_inicio)) {
        header('Location: /ue-ameliarios/public/index.php/eventos?error=' . urlencode('El título y la fecha son obligatorios'));
        exit;
    }

    try {
        db()->beginTransaction();

        $stmt = db()->prepare("
            INSERT INTO evento (titulo, descripcion, fecha_inicio, lugar, tipo, creado_por)
            VALUES (:titulo, :desc, :fecha, :lugar, :tipo, :creado)
        ");
        $stmt->execute([
            'titulo' => $titulo,
            'desc'   => $descripcion,
            'fecha'  => $fecha_inicio,
            'lugar'  => $lugar,
            'tipo'   => $tipo,
            'creado' => $_SESSION['user']['id'] // ✅ CORREGIDO
        ]);
        $id_evento = db()->lastInsertId();

        // ✅ AUDITORÍA (TEST-06)
        $stmt = db()->prepare("
            INSERT INTO auditoria_logs (usuario_id, accion, tabla_afectada, registro_id, fecha_hora)
            VALUES (:usr, 'CREAR_EVENTO', 'evento', :id, NOW())
        ");
        $stmt->execute(['usr' => $_SESSION['user']['id'], 'id' => $id_evento]);

        db()->commit();

        header('Location: /ue-ameliarios/public/index.php/eventos?exito=' . urlencode('Evento publicado exitosamente.'));
        exit;
    } catch (Exception $e) {
        db()->rollBack();
        header('Location: /ue-ameliarios/public/index.php/eventos?error=' . urlencode('Error al guardar: ' . $e->getMessage()));
        exit;
    }
}

// ==========================================
// /eventos/like (POST) — Dar o quitar Like
// ==========================================
if ($uri === '/eventos/like' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    $id_evento = (int)($_POST['id_evento'] ?? 0);
    $id_usuario = $_SESSION['user']['id']; // ✅ CORREGIDO

    if ($id_evento > 0) {
        $stmt = db()->prepare("SELECT COUNT(*) FROM evento_reacciones WHERE id_evento = :e AND id_usuario = :u");
        $stmt->execute(['e' => $id_evento, 'u' => $id_usuario]);
        
        if ($stmt->fetchColumn() > 0) {
            $stmt = db()->prepare("DELETE FROM evento_reacciones WHERE id_evento = :e AND id_usuario = :u");
            $stmt->execute(['e' => $id_evento, 'u' => $id_usuario]);
        } else {
            $stmt = db()->prepare("INSERT INTO evento_reacciones (id_evento, id_usuario) VALUES (:e, :u)");
            $stmt->execute(['e' => $id_evento, 'u' => $id_usuario]);
        }
    }
    header('Location: /ue-ameliarios/public/index.php/eventos');
    exit;
}

// ==========================================
// /eventos/comentar (POST) — Publicar Comentario
// ==========================================
if ($uri === '/eventos/comentar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    $id_evento = (int)($_POST['id_evento'] ?? 0);
    $comentario = trim($_POST['comentario'] ?? '');
    $id_usuario = $_SESSION['user']['id']; // ✅ CORREGIDO

    if ($id_evento > 0 && !empty($comentario)) {
        try {
            $stmt = db()->prepare("
                INSERT INTO evento_comentarios (id_evento, id_usuario, comentario) 
                VALUES (:e, :u, :c)
            ");
            $stmt->execute(['e' => $id_evento, 'u' => $id_usuario, 'c' => $comentario]);
        } catch (Exception $e) {
            // Manejar error silenciosamente
        }
    }
    header('Location: /ue-ameliarios/public/index.php/eventos');
    exit;
}

// ==========================================
// /eventos (GET) — Muro Principal
// ==========================================
if ($uri === '/eventos' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    requirePermission('/eventos', 'VER'); // ✅ RBAC JERÁRQUICO
    
    $puedeCrear = can('/eventos', 'CREAR');
    $id_usuario_actual = $_SESSION['user']['id']; // ✅ CORREGIDO

    $eventos = db()->query("
        SELECT e.*, 
               p.nombres AS autor_nombres, p.apellidos AS autor_apellidos,
               (SELECT COUNT(*) FROM evento_reacciones er WHERE er.id_evento = e.id_evento) AS total_likes,
               (SELECT COUNT(*) FROM evento_comentarios ec WHERE ec.id_evento = e.id_evento) AS total_comentarios
        FROM evento e
        INNER JOIN usuario u ON e.creado_por = u.id_usuario
        INNER JOIN persona p ON u.id_persona = p.id_persona
        ORDER BY e.fecha_inicio DESC
        LIMIT 50
    ")->fetchAll();

    $stmt = db()->prepare("SELECT id_evento FROM evento_reacciones WHERE id_usuario = :u");
    $stmt->execute(['u' => $id_usuario_actual]);
    $mis_likes = array_column($stmt->fetchAll(), 'id_evento');

    renderHeader('Muro de Eventos');
    ?>

    <?php if (isset($_GET['exito'])): ?>
        <div style="background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">✅ <?= htmlspecialchars($_GET['exito']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">⚠️ <?= htmlspecialchars($_GET['error']) ?></div>
    <?php endif; ?>

    <!-- FORMULARIO DE CREACIÓN (Solo si tiene permiso CREAR) -->
    <?php if ($puedeCrear): ?>
        <div class="card" style="border-left: 4px solid var(--primary-color); margin-bottom: 30px;">
            <h2>📢 Publicar Nuevo Anuncio/Evento</h2>
            <form method="POST" action="/ue-ameliarios/public/index.php/eventos/crear">
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom: 15px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:5px;">Título *</label>
                        <input type="text" name="titulo" required placeholder="Ej: Reunión de Padres" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:5px;">Fecha *</label>
                        <input type="date" name="fecha_inicio" required value="<?= date('Y-m-d') ?>" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-bottom: 15px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:5px;">Lugar</label>
                        <input type="text" name="lugar" placeholder="Ej: Salón Principal" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:5px;">Tipo</label>
                        <select name="tipo" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px;">
                            <option value="Académico">Académico</option>
                            <option value="Deporte">Deporte</option>
                            <option value="Cultural">Cultural</option>
                            <option value="Efeméride">Efeméride</option>
                            <option value="Reunión">Reunión</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display:block; font-size:13px; font-weight:bold; margin-bottom:5px;">Descripción</label>
                    <textarea name="descripcion" rows="2" placeholder="Detalles del evento..." style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-family:inherit;"></textarea>
                </div>
                <button type="submit" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">Publicar</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- MURO (FEED) DE EVENTOS -->
    <h3 style="color: #1e3a8a; margin-bottom: 20px; font-size: 1.2rem; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">📰 Novedades de la Institución</h3>
    
    <?php if (!$eventos): ?>
        <div class="card" style="text-align: center; padding: 50px 20px; color: #888;">
            <p style="font-size:40px; margin-bottom:10px;">📅</p>
            <p>No hay eventos programados por el momento.</p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 25px; max-width: 800px; margin: 0 auto;">
            <?php foreach ($eventos as $e): ?>
                <?php $le_di_like = in_array($e['id_evento'], $mis_likes); ?>
                
                <div style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; overflow: hidden;">
                    
                    <!-- Cabecera del Post -->
                    <div style="padding: 20px; border-bottom: 1px solid #f0f0f0;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <span style="background: #e7f3ff; color: var(--primary-color); padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase;">
                                    <?= htmlspecialchars($e['tipo']) ?>
                                </span>
                                <h2 style="color: #1e3a8a; font-size: 1.3rem; margin-top: 10px; margin-bottom: 5px;"><?= htmlspecialchars($e['titulo']) ?></h2>
                                <p style="color: #718096; font-size: 13px;">
                                    Publicado por <strong><?= htmlspecialchars($e['autor_nombres'] . ' ' . $e['autor_apellidos']) ?></strong>
                                    · 📅 <?= htmlspecialchars($e['fecha_inicio']) ?>
                                    · 📍 <?= htmlspecialchars($e['lugar'] ?? 'No especificado') ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Cuerpo del Post -->
                    <div style="padding: 20px; color: #4a5568; font-size: 15px; line-height: 1.6;">
                        <?= nl2br(htmlspecialchars($e['descripcion'] ?? 'Sin descripción.')) ?>
                    </div>

                    <!-- Barra de Interacción (Likes y Comentarios) -->
                    <div style="padding: 10px 20px; background: #f9fafb; border-top: 1px solid #f0f0f0; display: flex; gap: 20px; align-items: center;">
                        <!-- Botón de Like -->
                        <form method="POST" action="/ue-ameliarios/public/index.php/eventos/like" style="margin: 0;">
                            <input type="hidden" name="id_evento" value="<?= $e['id_evento'] ?>">
                            <button type="submit" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; font-size: 14px; color: <?= $le_di_like ? '#e53e3e' : '#718096' ?>; font-weight: 600;">
                                <span style="font-size: 18px;"><?= $le_di_like ? '❤️' : '🤍' ?></span> 
                                <?= $e['total_likes'] ?> Me gusta
                            </button>
                        </form>

                        <!-- Botón para ver/ocultar comentarios -->
                        <button onclick="toggleComentarios(<?= $e['id_evento'] ?>)" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 5px; font-size: 14px; color: #718096; font-weight: 600;">
                            <span style="font-size: 18px;">💬</span> 
                            <?= $e['total_comentarios'] ?> Comentarios
                        </button>
                    </div>

                    <!-- Sección de Comentarios (Oculta por defecto) -->
                    <div id="comentarios-<?= $e['id_evento'] ?>" style="display: none; padding: 20px; background: #f9fafb; border-top: 1px solid #e5e7eb;">
                        
                        <?php
                        $stmtCom = db()->prepare("
                            SELECT c.comentario, c.creado_en, p.nombres, p.apellidos 
                            FROM evento_comentarios c 
                            INNER JOIN usuario u ON c.id_usuario = u.id_usuario 
                            INNER JOIN persona p ON u.id_persona = p.id_persona
                            WHERE c.id_evento = :e 
                            ORDER BY c.creado_en ASC
                        ");
                        $stmtCom->execute(['e' => $e['id_evento']]);
                        $comentarios = $stmtCom->fetchAll();
                        ?>

                        <!-- Lista de comentarios existentes -->
                        <?php if ($comentarios): ?>
                            <div style="margin-bottom: 15px; display: flex; flex-direction: column; gap: 10px;">
                                <?php foreach ($comentarios as $c): ?>
                                    <div style="background: white; padding: 10px 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                                        <span style="font-weight: 600; font-size: 13px; color: #1e3a8a;"><?= htmlspecialchars($c['nombres'] . ' ' . $c['apellidos']) ?>:</span>
                                        <span style="font-size: 14px; color: #4a5568;"><?= htmlspecialchars($c['comentario']) ?></span>
                                        <div style="font-size: 11px; color: #a0aec0; margin-top: 4px;"><?= htmlspecialchars($c['creado_en']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="font-size: 13px; color: #a0aec0; margin-bottom: 15px;">Sé el primero en comentar.</p>
                        <?php endif; ?>

                        <!-- Formulario para nuevo comentario -->
                        <form method="POST" action="/ue-ameliarios/public/index.php/eventos/comentar" style="display: flex; gap: 10px;">
                            <input type="hidden" name="id_evento" value="<?= $e['id_evento'] ?>">
                            <input type="text" name="comentario" placeholder="Escribe un comentario..." required style="flex: 1; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px;">
                            <button type="submit" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Enviar</button>
                        </form>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Script para Mostrar/Ocultar Comentarios -->
    <script>
        function toggleComentarios(id) {
            const section = document.getElementById('comentarios-' + id);
            if (section.style.display === 'none') {
                section.style.display = 'block';
            } else {
                section.style.display = 'none';
            }
        }
    </script>

    <?php
    renderFooter();
    exit;
}