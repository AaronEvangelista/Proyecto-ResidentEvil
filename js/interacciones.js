
function ejecutarEvento(evento, event) {
    console.log("Interactuando con:", evento);

    mostrarMensajeEnPantalla(`Has examinado: ${evento.nombre_objeto}`);

    setTimeout(() => {
        switch (evento.tipo_accion) {
            case 'leer_archivo':
                console.log("Acción: Leer archivo ID", evento.contenido_accion);
                abrirMenuArchivo(evento.contenido_accion);
                break;

            case 'recoger_item':
            case 'recoger_arma':
                const tipo = (evento.tipo_accion === 'recoger_arma') ? 'arma' : 'item';
                console.log(`Acción: Recoger ${tipo} ID`, evento.contenido_accion);
                mostrarMensajeEnPantalla(`[OBJETO] Has obtenido: ${evento.nombre_objeto}`);
                
                // 1. Registrar recogida y añadir al inventario
                registrarRecogida(evento.id_evento, tipo, evento.contenido_accion);

                // 2. Ocultar el elemento visualmente
                const target = event.currentTarget;
                if (target) target.style.display = 'none';
                break;

            case 'puzzle':
                console.log("Acción: Iniciar puzzle", evento.contenido_accion);
                mostrarMensajeEnPantalla(`[PUZZLE] Se requiere resolver el acertijo: ${evento.contenido_accion}`);
                if (typeof abrirMenuPuzzle === 'function') {
                    abrirMenuPuzzle(evento.contenido_accion);
                }
                break;

            case 'guardar':
                if (confirm("¿Deseas guardar tu progreso en la máquina de escribir?")) {
                    guardarPartida();
                }
                break;

            case 'transicion':
                mostrarMensajeEnPantalla("Cambiando de zona...");
                window.location.href = `juego.php?sala=${evento.contenido_accion}`;
                break;

            default:
                console.warn("Tipo de acción no reconocido:", evento.tipo_accion);
        }
    }, 500);
}

function abrirMenuArchivo(idArchivo) {
    const archivo = catalogoArchivos.find(a => a.id_archivo == idArchivo);

    if (archivo) {
        document.getElementById('note-title').innerText = archivo.nombre;
        document.getElementById('note-body').innerText = archivo.informacion;
        document.getElementById('note-viewer').style.display = 'flex';

        if (typeof estadoActual !== 'undefined') {
            estadoAnterior = estadoActual;
            estadoActual = "leyendo";
        }
    } else {
        console.error("Archivo no encontrado:", idArchivo);
    }
}

function cerrarNota() {
    document.getElementById('note-viewer').style.display = 'none';
    if (typeof estadoAnterior !== 'undefined') {
        estadoActual = estadoAnterior;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const btnCerrar = document.getElementById('btn-cerrar-nota');
    if (btnCerrar) {
        btnCerrar.addEventListener('click', cerrarNota);
    }
});

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
            console.log("Evento y objeto registrados:", idEvento);
        } else {
            console.error("Error al registrar:", data.error);
        }
    })
    .catch(error => console.error("Error en la petición:", error));
}

function guardarPartida() {
    mostrarMensajeEnPantalla("Guardando...");
    fetch('../api/guardar_partida.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarMensajeEnPantalla("[ÉXITO] Progreso guardado.");
        } else {
            mostrarMensajeEnPantalla("[ERROR] No se pudo guardar: " + data.error);
        }
    })
    .catch(error => {
        console.error("Error:", error);
        mostrarMensajeEnPantalla("[ERROR] Fallo en la conexión.");
    });
}

window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const viewer = document.getElementById('note-viewer');
        if (viewer && viewer.style.display === 'flex') {
            cerrarNota();
        }
    }
});
