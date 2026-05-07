<?php
require_once __DIR__ . '/../includes/seguridad.php';
session_start();

// Protección: solo admins
if (empty($_SESSION['logueado']) || ($_SESSION['usuario_rol'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$adminNombre      = htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Admin');
$zombiesVisibles  = (int)($_SESSION['zombies_visibles'] ?? 1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Panel de control de administrador — Resident Evil">
    <title>Panel Admin — Umbrella Corp | RE:Trivia</title>
    <link rel="stylesheet" href="../styles/admin.css">
</head>
<body class="admin-body">

<!-- ─── HEADER ──────────────────────────────────────────────── -->
<header class="admin-header" id="admin-header">
    <div class="admin-logo-block">
        <div class="admin-umbrella-icon" aria-hidden="true">☣</div>
        <div>
            <div class="admin-titulo">PANEL DE CONTROL</div>
            <div class="admin-subtitulo">UMBRELLA CORPORATION — ACCESO RESTRINGIDO</div>
        </div>
    </div>
    <div class="admin-header-links">
        <div class="admin-operator-badge">
            <div class="admin-operator-dot"></div>
            <span><?= $adminNombre ?></span>
        </div>
        <a href="../pages/juego.php" class="admin-btn-header" id="btn-ir-juego">▶ JUGAR</a>
        <a href="../index.php" class="admin-btn-header" id="btn-volver-menu">← MENÚ</a>
        <a href="../sessions/logout.php" class="admin-btn-header" id="btn-logout">⏻ SALIR</a>
    </div>
</header>

<!-- ─── CONTENIDO PRINCIPAL ─────────────────────────────────── -->
<div class="admin-wrapper">

    <!-- ── Estadísticas ─────────────────────────────────────── -->
    <section class="admin-section" aria-label="Estadísticas globales">
        <div class="admin-section-header">
            <span class="admin-section-icon">📊</span>
            <h2 class="admin-section-title">Estadísticas del Sistema</h2>
        </div>
        <div class="admin-stats-grid" id="stats-grid">
            <div class="admin-stat-card">
                <span class="admin-stat-value" id="stat-usuarios">—</span>
                <span class="admin-stat-label">Usuarios Registrados</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-value" id="stat-partidas">—</span>
                <span class="admin-stat-label">Partidas Guardadas</span>
            </div>
            <div class="admin-stat-card">
                <span class="admin-stat-value" id="stat-zombies-estado"><?= $zombiesVisibles ? '🔴 ON' : '🟢 OFF' ?></span>
                <span class="admin-stat-label">Estado de Zombies</span>
            </div>
        </div>
    </section>

    <!-- ── Control de Zombies ────────────────────────────────── -->
    <section class="admin-section" aria-label="Control de zombies">
        <div class="admin-section-header">
            <span class="admin-section-icon">🧟</span>
            <h2 class="admin-section-title">Control de Enemigos</h2>
            <span class="admin-section-badge">MODO DEBUG</span>
        </div>

        <div class="zombie-control-card <?= $zombiesVisibles ? 'zombies-on' : 'zombies-off' ?>" id="zombie-card">
            <div class="zombie-control-info">
                <div class="zombie-control-title">APARICIÓN DE ZOMBIES EN EL JUEGO</div>
                <div class="zombie-control-desc">
                    Desactiva los zombies para ajustar eventos, posición de objetos, puzzles
                    o cualquier elemento del juego sin que los enemigos interfieran.
                    El cambio se aplica la próxima vez que cargues una sala.
                </div>
                <div class="zombie-status-text <?= $zombiesVisibles ? 'activos' : 'inactivos' ?>" id="zombie-status-text">
                    <?= $zombiesVisibles ? '⚠ ZOMBIES ACTIVOS — Aparecen en todas las salas' : '✔ ZOMBIES DESACTIVADOS — Modo ajuste activado' ?>
                </div>
                <div class="zombie-toggle-feedback" id="zombie-feedback"></div>
            </div>

            <div class="toggle-container">
                <span class="toggle-label-text <?= $zombiesVisibles ? 'toggle-on-label' : 'toggle-off-label' ?>" id="toggle-label">
                    <?= $zombiesVisibles ? 'ACTIVOS' : 'INACTIVOS' ?>
                </span>
                <label class="toggle-switch" for="toggle-zombies" aria-label="Activar o desactivar zombies">
                    <input type="checkbox" id="toggle-zombies" <?= $zombiesVisibles ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
    </section>

    <!-- ── Gestión de Usuarios ───────────────────────────────── -->
    <section class="admin-section" aria-label="Gestión de usuarios">
        <div class="admin-section-header">
            <span class="admin-section-icon">👥</span>
            <h2 class="admin-section-title">Gestión de Supervivientes</h2>
            <span class="admin-section-badge" id="badge-total-usuarios">Cargando...</span>
        </div>

        <div class="admin-table-wrapper">
            <table class="admin-table" id="tabla-usuarios" aria-label="Lista de usuarios">
                <thead>
                    <tr>
                        <th scope="col">#ID</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Email</th>
                        <th scope="col">Rol Actual</th>
                        <th scope="col">Partidas</th>
                        <th scope="col">Registro</th>
                        <th scope="col">Acción</th>
                    </tr>
                </thead>
                <tbody id="tbody-usuarios">
                    <!-- Skeleton loader -->
                    <tr class="skeleton-row">
                        <td>#00001</td>
                        <td>Cargando usuario...</td>
                        <td>usuario@raccoon.gov</td>
                        <td>jugador</td>
                        <td>0</td>
                        <td>01/01/2026</td>
                        <td>ACCIÓN</td>
                    </tr>
                    <tr class="skeleton-row">
                        <td>#00002</td>
                        <td>Cargando usuario...</td>
                        <td>usuario@raccoon.gov</td>
                        <td>jugador</td>
                        <td>0</td>
                        <td>01/01/2026</td>
                        <td>ACCIÓN</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

</div><!-- /.admin-wrapper -->

<!-- ─── TOAST ────────────────────────────────────────────────── -->
<div id="admin-toast" role="alert" aria-live="polite"></div>

<!-- ─── SCRIPTS ──────────────────────────────────────────────── -->
<script>
const SESSION_ADMIN_ID = <?= (int)$_SESSION['usuario_id'] ?>;

/* ── Toast helper ─────────────────────────────────────────── */
function mostrarToast(msg, esError = false) {
    const t = document.getElementById('admin-toast');
    t.textContent = msg;
    t.classList.toggle('error', esError);
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}

/* ── Toggle Zombies ───────────────────────────────────────── */
const toggleZombies = document.getElementById('toggle-zombies');
const zombieFeedback = document.getElementById('zombie-feedback');
const zombieStatusText = document.getElementById('zombie-status-text');
const zombieCard = document.getElementById('zombie-card');
const toggleLabel = document.getElementById('toggle-label');
const statZombiesEstado = document.getElementById('stat-zombies-estado');

toggleZombies.addEventListener('change', () => {
    const activo = toggleZombies.checked;
    zombieFeedback.textContent = 'Aplicando cambios...';
    zombieFeedback.style.color = '#888';
    toggleZombies.disabled = true;

    fetch('../src/api/admin_toggle_zombies.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ activo })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            zombieFeedback.textContent = '✔ ' + data.mensaje;
            zombieFeedback.style.color = activo ? '#e74c3c' : '#00ff88';

            // Actualizar UI
            zombieCard.className = 'zombie-control-card ' + (activo ? 'zombies-on' : 'zombies-off');
            zombieStatusText.className = 'zombie-status-text ' + (activo ? 'activos' : 'inactivos');
            zombieStatusText.textContent = activo
                ? '⚠ ZOMBIES ACTIVOS — Aparecen en todas las salas'
                : '✔ ZOMBIES DESACTIVADOS — Modo ajuste activado';
            toggleLabel.textContent = activo ? 'ACTIVOS' : 'INACTIVOS';
            toggleLabel.className = 'toggle-label-text ' + (activo ? 'toggle-on-label' : 'toggle-off-label');
            statZombiesEstado.textContent = activo ? '🔴 ON' : '🟢 OFF';

            mostrarToast(data.mensaje);
        } else {
            zombieFeedback.textContent = '⚠ ' + data.error;
            zombieFeedback.style.color = '#e74c3c';
            // Revertir toggle visualmente
            toggleZombies.checked = !activo;
            mostrarToast(data.error, true);
        }
    })
    .catch(() => {
        zombieFeedback.textContent = '⚠ Error de conexión.';
        zombieFeedback.style.color = '#e74c3c';
        toggleZombies.checked = !activo;
        mostrarToast('Error de conexión.', true);
    })
    .finally(() => {
        toggleZombies.disabled = false;
        setTimeout(() => { zombieFeedback.textContent = ''; }, 4000);
    });
});

/* ── Cargar usuarios ──────────────────────────────────────── */
function cargarUsuarios() {
    fetch('../src/api/admin_get_usuarios.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) { mostrarToast(data.error, true); return; }

            // Stats
            document.getElementById('stat-usuarios').textContent = data.total_usuarios;
            document.getElementById('stat-partidas').textContent = data.total_partidas;
            document.getElementById('badge-total-usuarios').textContent = data.total_usuarios + ' usuarios';

            // Tabla
            const tbody = document.getElementById('tbody-usuarios');
            tbody.innerHTML = '';

            data.usuarios.forEach(u => {
                const esAdmin = u.rol === 'admin';
                const esMisPropioId = u.id_usuario == SESSION_ADMIN_ID;

                const tr = document.createElement('tr');
                if (esAdmin) tr.classList.add('fila-admin');
                tr.id = `fila-${u.id_usuario}`;

                const fecha = u.fecha_registro
                    ? u.fecha_registro.substring(0, 10)
                    : '—';

                tr.innerHTML = `
                    <td class="td-id">#${String(u.id_usuario).padStart(5, '0')}</td>
                    <td class="td-nombre">${escHtml(u.nombre)}</td>
                    <td class="td-email">${escHtml(u.email)}</td>
                    <td>
                        <span class="rol-badge ${escHtml(u.rol)}" id="badge-rol-${u.id_usuario}">
                            ${u.rol === 'admin' ? '★ ADMIN' : 'JUGADOR'}
                        </span>
                    </td>
                    <td>${u.partidas}</td>
                    <td>${fecha}</td>
                    <td>
                        ${esMisPropioId
                            ? '<span style="color:#555;font-size:.65rem">— TÚ —</span>'
                            : `<button
                                class="btn-rol ${esAdmin ? 'hacer-jugador' : 'hacer-admin'}"
                                id="btn-rol-${u.id_usuario}"
                                onclick="cambiarRol(${u.id_usuario}, '${esAdmin ? 'jugador' : 'admin'}')"
                                title="${esAdmin ? 'Quitar rol de admin' : 'Hacer administrador'}"
                               >
                                ${esAdmin ? '↓ Jugador' : '↑ Admin'}
                               </button>`
                        }
                        <span class="fila-feedback" id="feedback-${u.id_usuario}"></span>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(() => mostrarToast('Error al cargar usuarios.', true));
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

/* ── Cambiar rol ──────────────────────────────────────────── */
function cambiarRol(idUsuario, nuevoRol) {
    const btn = document.getElementById(`btn-rol-${idUsuario}`);
    const feedback = document.getElementById(`feedback-${idUsuario}`);
    if (btn) btn.disabled = true;

    fetch('../src/api/admin_cambiar_rol.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_usuario: idUsuario, nuevo_rol: nuevoRol })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            mostrarToast(data.mensaje);
            // Recargar la tabla para reflejar el cambio
            cargarUsuarios();
        } else {
            if (feedback) {
                feedback.textContent = '⚠ ' + data.error;
                feedback.style.color = '#e74c3c';
            }
            if (btn) btn.disabled = false;
            mostrarToast(data.error, true);
        }
    })
    .catch(() => {
        if (btn) btn.disabled = false;
        mostrarToast('Error de conexión.', true);
    });
}

/* ── Init ─────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', cargarUsuarios);
</script>

</body>
</html>
