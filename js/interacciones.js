
function ejecutarEvento(evento) {
    console.log("Interactuando con:", evento);

    mostrarMensajeEnPantalla(`Has examinado: ${evento.nombre_objeto}`);

    setTimeout(() => {
        switch (evento.tipo_accion) {
            case 'leer_archivo':
                console.log("Acción: Leer archivo ID", evento.contenido_accion);
                abrirMenuArchivo(evento.contenido_accion);
                break;

            case 'recoger_item':
                console.log("Acción: Recoger item ID", evento.contenido_accion);
                mostrarMensajeEnPantalla(`[ITEM] Has obtenido: ${evento.nombre_objeto}`);
                break;

            case 'puzzle':
                console.log("Acción: Iniciar puzzle", evento.contenido_accion);
                mostrarMensajeEnPantalla(`[PUZZLE] Se requiere resolver el acertijo: ${evento.contenido_accion}`);
                if (typeof abrirMenuPuzzle === 'function') {
                    abrirMenuPuzzle(evento.contenido_accion);
                }
                break;

            case 'guardar':
                mostrarMensajeEnPantalla("[GUARDADO] ¿Deseas usar una cinta para guardar?");
                if (typeof abrirMenuGuardado === 'function') {
                    abrirMenuGuardado();
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

window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const viewer = document.getElementById('note-viewer');
        if (viewer && viewer.style.display === 'flex') {
            cerrarNota();
        }
    }
});
