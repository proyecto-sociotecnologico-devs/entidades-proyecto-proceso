<?php
/**
 * app/layout.php
 * Layout unificado con RBAC Jerárquico para U.E. Amelia Ríos.
 */

function renderHeader(string $titulo = 'Panel'): void {
    $user = $_SESSION['user'] ?? null;
    $rolPrincipal = function_exists('primaryRole') ? primaryRole() : 'Invitado';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($titulo) ?> — U.E. Amelia Ríos</title>
        <style>
            /* Reset y Variables Globales */
            :root {
                --primary-color: #1e3a8a;
                --primary-light: #3b82f6;
                --bg-color: #f3f4f6;
                --text-color: #1f2937;
                --text-muted: #6b7280;
                --sidebar-width: 260px;
                --header-height: 60px;
            }
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--bg-color); color: var(--text-color); display: flex; min-height: 100vh; }

            /* Sidebar */
            .sidebar {
                width: var(--sidebar-width);
                background: var(--primary-color);
                color: white;
                display: flex;
                flex-direction: column;
                position: fixed;
                height: 100vh;
                overflow-y: auto;
                transition: transform 0.3s ease;
                z-index: 1000;
            }
            .sidebar-header { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
            .sidebar-header h2 { font-size: 1.2rem; font-weight: 600; letter-spacing: 0.5px; }
            .sidebar-header p { font-size: 0.8rem; color: #9ca3af; margin-top: 4px; }
            
            .sidebar-nav { flex: 1; padding: 20px 10px; }
            .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: #d1d5db; text-decoration: none; border-radius: 8px; margin-bottom: 4px; transition: all 0.2s; font-size: 14px; }
            .nav-item:hover { background: rgba(255,255,255,0.1); color: white; }
            .nav-item.active { background: var(--primary-light); color: white; font-weight: 600; }
            .nav-item .icon { font-size: 18px; }

            .main-content { flex: 1; margin-left: var(--sidebar-width); display: flex; flex-direction: column; min-height: 100vh; transition: margin-left 0.3s ease; }
            
            .top-header {
                height: var(--header-height);
                background: white;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0 25px;
                position: sticky;
                top: 0;
                z-index: 100;
            }
            
            .header-left { display: flex; align-items: center; gap: 15px; }
            .page-title { font-weight: 600; font-size: 1.1rem; color: var(--text-color); }
            
            .menu-toggle {
                display: none;
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                color: var(--primary-color);
                padding: 5px;
            }

            .user-info { display: flex; align-items: center; gap: 15px; }
            .user-details { text-align: right; }
            .user-details .name { font-weight: 600; font-size: 14px; color: var(--text-color); }
            .user-details .role { font-size: 12px; color: var(--text-muted); text-transform: capitalize; }
            .user-avatar { width: 40px; height: 40px; background: var(--primary-light); color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-weight: bold; font-size: 16px; }
            .btn-logout { background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; transition: background 0.2s; }
            .btn-logout:hover { background: #dc2626; }

            .page-content { padding: 30px; flex: 1; }
            .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.02); border: 1px solid #e5e7eb; margin-bottom: 20px; }
            .card h2 { color: var(--primary-color); margin-bottom: 20px; border-bottom: 2px solid #f3f4f6; padding-bottom: 10px; font-size: 1.3rem; }

            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            }

            @media (max-width: 768px) {
                .sidebar { transform: translateX(-100%); }
                .sidebar.open { transform: translateX(0); }
                .main-content { margin-left: 0; }
                .menu-toggle { display: block; }
                .sidebar-overlay.active { display: block; }
                .top-header { padding: 0 15px; }
                .user-details { display: none; }
                .page-content { padding: 15px; }
                .main-content, .page-content, .card { max-width: 100vw; overflow-x: hidden; }
                table { display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
                .card h2, .card p { word-wrap: break-word; }
            }
        </style>
    </head>
    <body>
        <div class="sidebar-overlay" id="sidebar-overlay"></div>

        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>U.E. Amelia Ríos</h2>
                <p>Sistema de Gestión</p>
            </div>
            <nav class="sidebar-nav">
                <?php
                $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                $uri = str_replace('/ue-ameliarios/public', '', $uri);
                $uri = str_replace('/index.php', '', $uri);
                $uri = rtrim($uri, '/') ?: '/';
                ?>

                <a href="/ue-ameliarios/public/index.php/dashboard" class="nav-item <?= $uri === '/dashboard' ? 'active' : '' ?>">
                    <span class="icon">🏠</span> Panel Principal
                </a>

                <?php if (hasModuleAccess('usuarios')): ?>
                    <a href="/ue-ameliarios/public/index.php/usuarios" class="nav-item <?= str_starts_with($uri, '/usuarios') ? 'active' : '' ?>"><span class="icon">👥</span> Usuarios</a>
                <?php endif; ?>

                <?php if (hasModuleAccess('academico')): ?>
                    <a href="/ue-ameliarios/public/index.php/academico/grados" class="nav-item <?= str_starts_with($uri, '/academico') ? 'active' : '' ?>"><span class="icon">📚</span> Académico</a>
                <?php endif; ?>

                <?php if (hasModuleAccess('estudiantes')): ?>
                    <a href="/ue-ameliarios/public/index.php/estudiantes" class="nav-item <?= str_starts_with($uri, '/estudiantes') ? 'active' : '' ?>"><span class="icon">🎓</span> Estudiantes</a>
                <?php endif; ?>

                <?php if (hasModuleAccess('representantes')): ?>
                    <a href="/ue-ameliarios/public/index.php/representantes" class="nav-item <?= str_starts_with($uri, '/representantes') ? 'active' : '' ?>"><span class="icon">👨‍👩‍👧</span> Representantes</a>
                <?php endif; ?>

                <?php if (hasModuleAccess('inscripciones')): ?>
                    <a href="/ue-ameliarios/public/index.php/inscripciones" class="nav-item <?= str_starts_with($uri, '/inscripciones') ? 'active' : '' ?>"><span class="icon">📝</span> Inscripciones</a>
                <?php endif; ?>

                <?php if (hasModuleAccess('asistencia')): ?>
                    <a href="/ue-ameliarios/public/index.php/asistencia" class="nav-item <?= str_starts_with($uri, '/asistencia') ? 'active' : '' ?>"><span class="icon">✅</span> Asistencia</a>
                <?php endif; ?>

                <?php if (hasModuleAccess('justificaciones')): ?>
                    <a href="/ue-ameliarios/public/index.php/justificaciones" class="nav-item <?= str_starts_with($uri, '/justificaciones') ? 'active' : '' ?>"><span class="icon">📋</span> Justificaciones</a>
                <?php endif; ?>

                <?php if (hasModuleAccess('reportes')): ?>
                    <a href="/ue-ameliarios/public/index.php/reportes" class="nav-item <?= str_starts_with($uri, '/reportes') ? 'active' : '' ?>"><span class="icon">📊</span> Reportes</a>
                <?php endif; ?>

                <?php if (hasModuleAccess('eventos')): ?>
                    <a href="/ue-ameliarios/public/index.php/eventos" class="nav-item <?= str_starts_with($uri, '/eventos') ? 'active' : '' ?>"><span class="icon">📅</span> Eventos</a>
                <?php endif; ?>

                <?php if (hasModuleAccess('bitacora')): ?>
                    <a href="/ue-ameliarios/public/index.php/bitacora" class="nav-item <?= str_starts_with($uri, '/bitacora') ? 'active' : '' ?>"><span class="icon">📋</span> Bitácora</a>
                <?php endif; ?>

                <!-- Gestión de Seguridad (Solo Administrador) -->
                <?php if (function_exists('hasRole') && hasRole('Administrador')): ?>
                    <div style="margin: 15px 0 5px 15px; font-size: 11px; text-transform: uppercase; color: #9ca3af; font-weight: bold; letter-spacing: 1px;">Administración</div>
                    <a href="/ue-ameliarios/public/index.php/roles" class="nav-item <?= str_starts_with($uri, '/roles') ? 'active' : '' ?>"><span class="icon">🔐</span> Roles y Permisos</a>
                    <a href="/ue-ameliarios/public/index.php/modulos" class="nav-item <?= str_starts_with($uri, '/modulos') ? 'active' : '' ?>"><span class="icon">🧩</span> Módulos y Vistas</a>
                <?php endif; ?>
            </nav>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button class="menu-toggle" id="menu-toggle">☰</button>
                    <div class="page-title"><?= htmlspecialchars($titulo) ?></div>
                </div>
                <div class="user-info">
                    <?php if ($user): ?>
                        <div class="user-details">
                            <div class="name"><?= htmlspecialchars($user['nombres']) ?></div>
                            <div class="role"><?= htmlspecialchars($rolPrincipal) ?></div>
                        </div>
                        <div class="user-avatar">
                            <?= strtoupper(substr($user['nombres'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <a href="/ue-ameliarios/public/index.php/logout" class="btn-logout">Salir</a>
                </div>
            </header>

            <main class="page-content">
    <?php
}

function renderFooter(): void {
    ?>
            </main>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const menuToggle = document.getElementById('menu-toggle');
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebar-overlay');

                if (menuToggle && sidebar && overlay) {
                    menuToggle.addEventListener('click', () => {
                        sidebar.classList.toggle('open');
                        overlay.classList.toggle('active');
                    });
                    overlay.addEventListener('click', () => {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('active');
                    });
                    sidebar.querySelectorAll('a').forEach(link => {
                        link.addEventListener('click', () => {
                            if (window.innerWidth <= 768) {
                                sidebar.classList.remove('open');
                                overlay.classList.remove('active');
                            }
                        });
                    });
                }
            });
        </script>
    </body>
    </html>
    <?php
}


