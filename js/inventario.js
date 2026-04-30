document.addEventListener('DOMContentLoaded', () => {
    const btnInventario = document.getElementById('btn-inventario');
    const btnCerrarInv = document.getElementById('btn-cerrar-inventario');
    const inventoryScreen = document.getElementById('inventory-screen');

    if (btnInventario) {
        btnInventario.addEventListener('click', abrirInventario);
    }

    if (btnCerrarInv) {
        btnCerrarInv.addEventListener('click', cerrarInventario);
    }

    // Abrir con TAB
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Tab') {
            e.preventDefault();
            if (inventoryScreen.style.display === 'none' || inventoryScreen.style.display === '') {
                abrirInventario();
            } else {
                cerrarInventario();
            }
        }
        if (e.key === 'Escape' && inventoryScreen.style.display === 'flex') {
            cerrarInventario();
        }
    });
});

function abrirInventario() {
    fetch('../api/get_inventario.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderizarInventario(data.inventario);
                document.getElementById('inventory-screen').style.display = 'flex';
            }
        })
        .catch(error => console.error("Error al cargar inventario:", error));
}

function cerrarInventario() {
    document.getElementById('inventory-screen').style.display = 'none';
}

let draggedItemInfo = null;

function renderizarInventario(items) {
    const grid = document.getElementById('inventory-grid');
    grid.innerHTML = '';

    // Mapear items por su posición
    const itemMap = {};
    items.forEach(item => {
        itemMap[item.posicion_slot] = item;
    });

    // Generar 8 slots fijos
    for (let i = 0; i < 8; i++) {
        const slot = document.createElement('div');
        slot.className = 'inventory-slot';
        slot.dataset.slotIndex = i;

        // Eventos de Drop
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
                moverObjeto(draggedItemInfo.idRegistro, targetSlotIndex);
            }
        });
        
        if (itemMap[i]) {
            const item = itemMap[i];
            
            // Contenedor arrastrable
            const itemElement = document.createElement('div');
            itemElement.className = 'item-draggable';
            itemElement.draggable = true;
            itemElement.innerHTML = `
                <img src="${item.imagen_url}" alt="${item.nombre}" draggable="false">
                ${item.cantidad > 1 ? `<span class="item-quantity">x${item.cantidad}</span>` : ''}
            `;
            
            // Eventos de Drag
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
                document.getElementById('detail-name').innerText = "Selecciona un objeto";
                document.getElementById('detail-description').innerText = "Pasa el ratón sobre un objeto para ver sus detalles.";
            });

            slot.appendChild(itemElement);
        }
        
        grid.appendChild(slot);
    }
}

function moverObjeto(idRegistro, nuevoSlot) {
    fetch('../api/mover_objeto.php', {
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
            abrirInventario(); // Recargar tras mover
        } else {
            console.error("Error al mover objeto:", data.error);
        }
    })
    .catch(error => console.error("Error en la petición:", error));
}
