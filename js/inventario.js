document.addEventListener('DOMContentLoaded', () => {
    const btnInventario = document.getElementById('btn-inventario');
    const btnCerrarInv = document.getElementById('btn-cerrar-inventario');

    if (btnInventario) {
        btnInventario.addEventListener('click', () => {
            console.log("Botón Inventario clickeado");
            abrirInventario();
        });
    }

    if (btnCerrarInv) {
        btnCerrarInv.addEventListener('click', cerrarInventario);
    }
});

// Listener global fuera de DOMContentLoaded para mayor fiabilidad
window.addEventListener('keydown', (e) => {
    const key = e.key.toLowerCase();
    const code = e.code;
    const inventoryScreen = document.getElementById('inventory-screen');

    if (!inventoryScreen) return;

    // Evitar conflictos con el menú de pausa o notas
    const noteViewer = document.getElementById('note-viewer');
    const saveMenu = document.getElementById('save-menu');
    const noteVisible = noteViewer && noteViewer.style.display === 'flex';
    const saveVisible = saveMenu && saveMenu.style.display === 'flex';

    if (noteVisible || saveVisible) return;

    // Usar code o key para Tab
    if (code === 'Tab' || key === 'tab') {
        console.log("Tecla Tab detectada");
        e.preventDefault();
        e.stopPropagation();

        if (inventoryScreen.style.display === 'none' || inventoryScreen.style.display === '') {
            abrirInventario();
        } else {
            cerrarInventario();
        }
    }

    if (key === 'escape' && inventoryScreen.style.display === 'flex') {
        cerrarInventario();
    }
});

function abrirInventario() {
    console.log("abrirInventario() llamado");
    actualizarInventarioSilent().then(success => {
        if (success) {
            document.getElementById('inventory-screen').style.display = 'flex';
            if (typeof estadoActual !== 'undefined' && typeof ESTADOS_JUEGO !== 'undefined') {
                estadoActual = ESTADOS_JUEGO.INVENTARIO;
            }
        }
    });
}

function actualizarInventarioSilent() {
    return fetch('../src/api/get_inventario.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarInventario(data.inventario);
                return true;
            } else {
                console.error("Error en datos del inventario:", data.error);
                return false;
            }
        })
        .catch(error => {
            console.error("Error al cargar inventario:", error);
            return false;
        });
}

function cerrarInventario() {
    document.getElementById('inventory-screen').style.display = 'none';
    if (typeof estadoActual !== 'undefined' && typeof ESTADOS_JUEGO !== 'undefined') {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
    }
    selectedItem = null; // Reset selection
    document.getElementById('btn-examinar').style.display = 'none';
    document.getElementById('btn-eliminar').style.display = 'none';
}

let draggedItemInfo = null;
let selectedItem = null;

function renderizarInventario(items) {
    const grid = document.getElementById('inventory-grid');
    grid.innerHTML = '';

    const itemMap = {};
    items.forEach(item => {
        itemMap[item.posicion_slot] = item;
    });

    for (let i = 0; i < 8; i++) {
        const slot = document.createElement('div');
        slot.className = 'inventory-slot';
        slot.dataset.slotIndex = i;

        slot.addEventListener('dragover', (e) => {
            e.preventDefault();
            slot.classList.add('drag-over');
        });

        slot.addEventListener('dragleave', () => {
            slot.classList.remove('drag-over');
        });

        slot.addEventListener('drop', (e) => {
            e.preventDefault();
            slot.classList.remove('drag-over');
            const targetSlotIndex = parseInt(slot.dataset.slotIndex);

            if (draggedItemInfo && draggedItemInfo.sourceSlotIndex !== targetSlotIndex) {
                const targetItem = itemMap[targetSlotIndex];

                if (targetItem) {
                    combinarObjetos(draggedItemInfo.idRegistro, targetItem.id_registro, targetSlotIndex);
                } else {
                    moverObjeto(draggedItemInfo.idRegistro, targetSlotIndex);
                }
            }
        });

        if (itemMap[i]) {
            const item = itemMap[i];

            const itemElement = document.createElement('div');
            itemElement.className = 'item-draggable';
            itemElement.draggable = true;
            itemElement.innerHTML = `
                <img src="${item.imagen_url}" alt="${item.nombre}" draggable="false">
                ${item.cantidad > 1 ? `<span class="item-quantity">x${item.cantidad}</span>` : ''}
            `;

            itemElement.addEventListener('dragstart', (e) => {
                draggedItemInfo = {
                    idRegistro: item.id_registro,
                    sourceSlotIndex: i
                };
                itemElement.classList.add('dragging');
            });

            itemElement.addEventListener('dragend', () => {
                itemElement.classList.remove('dragging');
                draggedItemInfo = null;
            });

            itemElement.addEventListener('mouseenter', () => {
                document.getElementById('detail-name').innerText = item.nombre;
                document.getElementById('detail-description').innerText = item.descripcion;
            });

            itemElement.addEventListener('mouseleave', () => {
                if (!selectedItem) {
                    document.getElementById('detail-name').innerText = "Selecciona un objeto";
                    document.getElementById('detail-description').innerText = "Pasa el ratón sobre un objeto para ver sus detalles.";
                } else {
                    document.getElementById('detail-name').innerText = selectedItem.nombre;
                    document.getElementById('detail-description').innerText = selectedItem.descripcion;
                }
            });

            // Seleccionar para examinar/eliminar
            itemElement.addEventListener('click', () => {
                document.querySelectorAll('.inventory-slot').forEach(s => s.style.borderColor = '#333');
                slot.style.borderColor = '#ff0000';

                selectedItem = item;
                document.getElementById('detail-name').innerText = item.nombre;
                document.getElementById('detail-description').innerText = item.descripcion;

                const btnExaminar = document.getElementById('btn-examinar');
                if (btnExaminar) {
                    btnExaminar.style.display = 'block';
                    btnExaminar.onclick = () => examinarObjeto(item);
                }

                const btnEliminar = document.getElementById('btn-eliminar');
                if (btnEliminar) {
                    // Solo mostrar eliminar si NO es un arma Y NO es un objeto clave
                    if (item.tipo !== 'arma' && item.tipo !== 'clave') {
                        btnEliminar.style.display = 'block';
                        btnEliminar.onclick = () => {
                            if (confirm(`¿Estás seguro de que quieres eliminar ${item.nombre}?`)) {
                                eliminarObjeto(item.id_registro);
                            }
                        };
                    } else {
                        btnEliminar.style.display = 'none';
                    }
                }
            });

            slot.appendChild(itemElement);
        }

        grid.appendChild(slot);
    }
}

function eliminarObjeto(idRegistro) {
    fetch('../src/api/eliminar_objeto.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_registro: idRegistro })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                abrirInventario(); // Recargar
            } else {
                alert(data.error);
            }
        })
        .catch(error => console.error("Error al eliminar:", error));
}

function combinarObjetos(idOrigen, idDestino, targetSlotIndex) {
    fetch('../src/api/combinar_objetos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id_registro_arrastrado: idOrigen,
            id_registro_destino: idDestino
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.action === 'combined') {
                if (typeof mostrarMensajeEnPantalla === 'function') {
                    mostrarMensajeEnPantalla("[COMBINACIÓN] " + data.message);
                } else {
                    alert(data.message);
                }
                abrirInventario();
            } else if (data.action === 'swap') {
                moverObjeto(idOrigen, targetSlotIndex);
            }
        })
        .catch(error => console.error("Error al combinar:", error));
}

function moverObjeto(idRegistro, nuevoSlot) {
    fetch('../src/api/mover_objeto.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id_registro: idRegistro,
            nuevo_slot: nuevoSlot
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                abrirInventario();
            } else {
                console.error("Error al mover objeto:", data.error);
            }
        })
        .catch(error => console.error("Error en la petición:", error));
}

function examinarObjeto(item) {
    const noteViewer = document.getElementById('note-viewer');
    const noteTitle = document.getElementById('note-title');
    const noteBody = document.getElementById('note-body');
    const noteImg = document.getElementById('note-img');

    if (noteViewer && noteTitle && noteBody && noteImg) {
        if (item.nombre.toUpperCase().includes("CAJA FUERTE PORTATIL")) {
            cerrarInventario();
            if (typeof abrirPortableSafe === 'function') {
                abrirPortableSafe(item.id_registro);
            }
            return;
        }

        noteTitle.innerText = item.nombre;
        noteBody.innerText = item.descripcion + "\n\n(Objeto examinado)";

        noteImg.src = item.imagen_url;
        noteImg.style.width = '300px';
        noteImg.style.height = 'auto';
        noteImg.style.margin = '0 auto';
        noteImg.style.display = 'block';

        noteViewer.style.display = 'flex';
    }
}
