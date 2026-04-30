function mostrarMensajeEnPantalla(mensaje) {
    const messageBox = document.querySelector(".message-box p");
    if (messageBox) {
        messageBox.innerHTML = mensaje;
    }
}

function ejecutarEvento(evento, event) {
    console.log("Evento clickeado:", evento);

    switch (evento.tipo_accion) {
        case 'recoger_item':
        case 'recoger_arma':
            const tipo = (evento.tipo_accion === 'recoger_arma') ? 'arma' : 'item';
            console.log(`Acción: Recoger ${tipo} ID`, evento.contenido_accion);
            mostrarMensajeEnPantalla(`[OBJETO] Has obtenido: ${evento.nombre_objeto}`);

            registrarRecogida(evento.id_evento, tipo, evento.contenido_accion);

            const target = event.currentTarget;
            if (target) target.style.display = 'none';
            break;

        case 'nota':
            const noteViewer = document.getElementById('note-viewer');
            document.getElementById('note-title').innerText = evento.nombre_objeto;
            document.getElementById('note-body').innerText = evento.contenido_accion;
            noteViewer.style.display = 'flex';
            break;

        case 'puzzle':
            mostrarMensajeEnPantalla(`[PUZZLE] Se requiere resolver el acertijo: ${evento.contenido_accion}`);
            if (typeof abrirMenuPuzzle === 'function') {
                abrirMenuPuzzle(evento.contenido_accion);
            }
            break;

        case 'guardar':
            abrirMenuGuardado();
            break;

        case 'transicion':
            mostrarMensajeEnPantalla("Cambiando de zona...");
            window.location.href = `juego.php?sala=${evento.contenido_accion}`;
            break;

        default:
            console.warn("Tipo de acción no reconocido:", evento.tipo_accion);
            break;
    }
}

function registrarRecogida(idEvento, tipoObjeto, idObjeto) {
    fetch('../api/recoger_objeto.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id_evento: idEvento,
            tipo_objeto: tipoObjeto,
            id_objeto: idObjeto
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log("Objeto recogido en sesión:", idEvento);
            } else {
                console.error("Error al registrar:", data.error);
            }
        })
        .catch(error => console.error("Error en la petición:", error));
}

function abrirMenuGuardado() {
    const menu = document.getElementById('save-menu');
    const ribbonSpan = document.getElementById('ribbon-count');

    fetch('../api/get_save_slots.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                ribbonSpan.innerText = data.cintas;

                document.querySelectorAll('.save-slot').forEach(slotDiv => {
                    const num = slotDiv.dataset.slot;
                    const info = data.slots[num];
                    const statusSpan = slotDiv.querySelector('.slot-status');
                    const dateSpan = slotDiv.querySelector('.slot-date');

                    if (info) {
                        statusSpan.innerText = info.ubicacion;
                        dateSpan.innerText = info.fecha;
                        slotDiv.classList.add('occupied');
                    } else {
                        statusSpan.innerText = "VACÍO";
                        dateSpan.innerText = "--/--/-- --:--";
                        slotDiv.classList.remove('occupied');
                    }

                    slotDiv.onclick = () => {
                        if (data.cintas > 0) {
                            const confirmMsg = info ? "¿Sobreescribir partida existente?" : "¿Guardar en este slot?";
                            if (confirm(confirmMsg)) {
                                guardarEnSlot(num);
                            }
                        } else {
                            mostrarMensajeEnPantalla("[AVISO] No tienes Cintas de Guardado.");
                        }
                    };
                });

                menu.style.display = 'flex';
            }
        });
}

function cerrarMenuGuardado() {
    document.getElementById('save-menu').style.display = 'none';
}

function guardarEnSlot(slotNumero) {
    fetch('../api/guardar_partida.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ slot_numero: slotNumero })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                mostrarMensajeEnPantalla(data.message);
                cerrarMenuGuardado();
            } else {
                mostrarMensajeEnPantalla("[ERROR] " + data.error);
            }
        })
        .catch(error => console.error("Error al guardar:", error));
}

// Botón cancelar
document.addEventListener('DOMContentLoaded', () => {
    const btnCancel = document.getElementById('btn-cancelar-guardado');
    if (btnCancel) btnCancel.onclick = cerrarMenuGuardado;

    const btnCerrarNota = document.getElementById('btn-cerrar-nota');
    if (btnCerrarNota) {
        btnCerrarNota.addEventListener('click', () => {
            document.getElementById('note-viewer').style.display = 'none';
        });
    }
});

window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const viewer = document.getElementById('note-viewer');
        if (viewer.style.display === 'flex') {
            viewer.style.display = 'none';
        }

        const saveMenu = document.getElementById('save-menu');
        if (saveMenu.style.display === 'flex') {
            cerrarMenuGuardado();
        }
    }
});
