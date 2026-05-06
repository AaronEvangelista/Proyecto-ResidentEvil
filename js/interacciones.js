function mostrarMensajeEnPantalla(mensaje) {
  const messageBox = document.querySelector(".message-box p");
  if (messageBox) {
    messageBox.innerHTML = mensaje;
  }
}

function ejecutarEvento(evento, event) {
  console.log("Evento clickeado:", evento);

  switch (evento.tipo_accion) {
    case "recoger_item":
    case "recoger_arma":
      const tipo = evento.tipo_accion === "recoger_arma" ? "arma" : "item";
      console.log(`Acción: Recoger ${tipo} ID`, evento.contenido_accion);
      mostrarMensajeEnPantalla(
        `[OBJETO] Has obtenido: ${evento.nombre_objeto}`,
      );

      registrarRecogida(evento.id_evento, tipo, evento.contenido_accion);

      const target = event.currentTarget;
      if (target) target.style.display = "none";
      break;

    case "nota":
      const noteViewer = document.getElementById("note-viewer");
      document.getElementById("note-title").innerText = evento.nombre_objeto;
      document.getElementById("note-body").innerText = evento.contenido_accion;
      noteViewer.style.display = "flex";
      break;

    case "puzzle":
      if (typeof abrirMenuPuzzle === "function") {
        abrirMenuPuzzle(evento.contenido_accion);
      } else {
        mostrarMensajeEnPantalla(
          `[PUZZLE] Se requiere resolver: ${evento.contenido_accion}`,
        );
      }
      break;

    case "guardar":
      abrirMenuGuardado();
      break;

    case "transicion":
      mostrarMensajeEnPantalla("Cambiando de zona...");
      window.location.href = `juego.php?sala=${evento.contenido_accion}`;
      break;

    default:
      console.warn("Tipo de acción no reconocido:", evento.tipo_accion);
      break;
  }
}

function registrarRecogida(idEvento, tipoObjeto, idObjeto) {
  fetch("../src/api/recoger_objeto.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      id_evento: idEvento,
      tipo_objeto: tipoObjeto,
      id_objeto: idObjeto,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        console.log("Objeto recogido en sesión:", idEvento);
      } else {
        console.error("Error al registrar:", data.error);
      }
    })
    .catch((error) => console.error("Error en la petición:", error));
}

function abrirMenuGuardado() {
  const menu = document.getElementById("save-menu");
  const ribbonSpan = document.getElementById("ribbon-count");

  fetch("../src/api/get_save_slots.php")
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        ribbonSpan.innerText = data.cintas;

        document.querySelectorAll(".save-slot").forEach((slotDiv) => {
          const num = slotDiv.dataset.slot;
          const info = data.slots[num];
          const statusSpan = slotDiv.querySelector(".slot-status");
          const dateSpan = slotDiv.querySelector(".slot-date");

          if (info) {
            statusSpan.innerText = info.ubicacion;
            dateSpan.innerText = info.fecha;
            slotDiv.classList.add("occupied");
          } else {
            statusSpan.innerText = "VACÍO";
            dateSpan.innerText = "--/--/-- --:--";
            slotDiv.classList.remove("occupied");
          }

          slotDiv.onclick = () => {
            if (data.cintas > 0) {
              const confirmMsg = info
                ? "¿Sobreescribir partida existente?"
                : "¿Guardar en este slot?";
              if (confirm(confirmMsg)) {
                guardarEnSlot(num);
              }
            } else {
              mostrarMensajeEnPantalla("[AVISO] No tienes Cintas de Guardado.");
            }
          };
        });

        menu.style.display = "flex";
      }
    });
}

function cerrarMenuGuardado() {
  document.getElementById("save-menu").style.display = "none";
}

// ════════════════════════════════════════════════
//  PUZZLE DE MEDALLONES
// ════════════════════════════════════════════════

// Mapa id_item -> nombre del slot HTML
const MEDALLON_SLOTS = {
  7: "leon",
  8: "unicornio",
  9: "doncella",
};

const MEDALLON_NOMBRES = {
  7: "León",
  8: "Unicornio",
  9: "Doncella",
};

let medallonesPuzzleState = {
  disponibles: [], // IDs que tiene el jugador en inventario
  colocados: [], // IDs colocados en slots por el jugador
};

/**
 * Punto de entrada llamado desde ejecutarEvento() cuando tipo_accion = 'puzzle'.
 */
function abrirMenuPuzzle(tipo) {
  if (tipo === "medallones") {
    abrirPuzzleMedallones();
  } else if (tipo === "caja_fuerte") {
    abrirCajaFuerte();
  } else {
    mostrarMensajeEnPantalla(`[PUZZLE] Se requiere resolver: ${tipo}`);
  }
}

function abrirPuzzleMedallones() {
  const modal = document.getElementById("medallones-puzzle");
  if (!modal) return;

  // Resetear estado
  medallonesPuzzleState.disponibles = [];
  medallonesPuzzleState.colocados = [];

  // Limpiar slots visualmente
  Object.values(MEDALLON_SLOTS).forEach((nombre) => {
    const slot = document.getElementById(`slot-${nombre}`);
    const placed = document.getElementById(`placed-${nombre}`);
    if (slot) slot.className = "medallon-slot";
    if (placed) placed.style.display = "none";
  });

  document.getElementById("btn-colocar-medallones").disabled = true;
  document.getElementById("medallones-status").textContent =
    "Verificando inventario...";

  modal.style.display = "flex";

  // Consultar qué medallones tiene el jugador
  fetch("../src/api/check_medallones.php")
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        medallonesPuzzleState.disponibles =
          data.medallones_disponibles.map(Number);
        actualizarSlotsMedallones();
      } else {
        document.getElementById("medallones-status").textContent =
          "⚠ Error al verificar inventario.";
      }
    })
    .catch((err) => {
      console.error("check_medallones error:", err);
      document.getElementById("medallones-status").textContent =
        "⚠ Error de conexión.";
    });
}

/**
 * Actualiza el aspecto de cada slot según el estado actual.
 */
function actualizarSlotsMedallones() {
  Object.entries(MEDALLON_SLOTS).forEach(([idStr, nombre]) => {
    const id = Number(idStr);
    const slot = document.getElementById(`slot-${nombre}`);
    const placed = document.getElementById(`placed-${nombre}`);

    // Quitar todas las clases de estado
    slot.classList.remove("available", "placed", "unavailable");

    if (medallonesPuzzleState.colocados.includes(id)) {
      // --- Ya colocado: verde, imagen visible, click = quitar ---
      slot.classList.add("placed");
      placed.style.display = "flex";
      slot.onclick = () => quitarMedallon(id);
      slot.title = "Haz clic para retirar el medallón";
    } else if (medallonesPuzzleState.disponibles.includes(id)) {
      // --- Disponible: dorado pulsante, click = colocar ---
      slot.classList.add("available");
      placed.style.display = "none";
      slot.onclick = () => colocarMedallon(id);
      slot.title = `Colocar Medallón de ${MEDALLON_NOMBRES[id]}`;
    } else {
      // --- No disponible: gris oscuro, sin interacción ---
      slot.classList.add("unavailable");
      placed.style.display = "none";
      slot.onclick = null;
      slot.title = `Necesitas el Medallón de ${MEDALLON_NOMBRES[id]}`;
    }
  });

  // Habilitar botón solo si los 3 están colocados
  const todosColocados = [7, 8, 9].every((id) =>
    medallonesPuzzleState.colocados.includes(id),
  );
  document.getElementById("btn-colocar-medallones").disabled = !todosColocados;

  // Mensaje de estado
  const faltantes = [7, 8, 9].filter(
    (id) => !medallonesPuzzleState.disponibles.includes(id),
  );
  const colocadosCount = medallonesPuzzleState.colocados.length;

  let statusMsg;
  if (faltantes.length > 0) {
    const nombres = faltantes.map((id) => MEDALLON_NOMBRES[id]).join(", ");
    statusMsg = `⚠ Te faltan: ${nombres}. Sigue explorando.`;
  } else if (todosColocados) {
    statusMsg = "✔ Los tres medallones están listos. Pulsa ACTIVAR ESTATUA.";
  } else {
    statusMsg = `${colocadosCount}/3 medallones colocados. Haz clic en los slots dorados.`;
  }
  document.getElementById("medallones-status").textContent = statusMsg;
}

function colocarMedallon(idMedallon) {
  if (!medallonesPuzzleState.disponibles.includes(idMedallon)) return;
  if (medallonesPuzzleState.colocados.includes(idMedallon)) return;
  medallonesPuzzleState.colocados.push(idMedallon);
  actualizarSlotsMedallones();
}

function quitarMedallon(idMedallon) {
  medallonesPuzzleState.colocados = medallonesPuzzleState.colocados.filter(
    (id) => id !== idMedallon,
  );
  actualizarSlotsMedallones();
}

function cerrarMenuMedallones() {
  const modal = document.getElementById("medallones-puzzle");
  if (modal) modal.style.display = "none";
  medallonesPuzzleState.colocados = [];
}

function completarPuzzleMedallones() {
  const btn = document.getElementById("btn-colocar-medallones");
  btn.disabled = true;
  btn.textContent = "Activando...";

  fetch("../src/api/colocar_medallones.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ accion: "completar" }),
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        cerrarMenuMedallones();
        mostrarMensajeEnPantalla("[ESTATUA] " + data.message);

        // Ocultar el hotspot de la estatua
        document.querySelectorAll(".hotspot").forEach((h) => {
          if (h.title === "ESTATUA") h.style.display = "none";
        });
      } else {
        document.getElementById("medallones-status").textContent =
          "⚠ " + data.error;
        btn.disabled = false;
        btn.textContent = "ACTIVAR ESTATUA";
      }
    })
    .catch((err) => {
      console.error("colocar_medallones error:", err);
      document.getElementById("medallones-status").textContent =
        "⚠ Error de conexión.";
      btn.disabled = false;
      btn.textContent = "ACTIVAR ESTATUA";
    });
}

// ════════════════════════════════════════════════
//  PUZZLE CAJA FUERTE
// ════════════════════════════════════════════════

let dialValues = [0, 0, 0];

function abrirCajaFuerte() {
  const modal = document.getElementById("caja-fuerte-puzzle");
  if (!modal) return;

  dialValues = [0, 0, 0];
  for (let i = 0; i < 3; i++) {
    document.getElementById(`dial-${i}`).textContent = "0";
  }

  document.getElementById("caja-fuerte-status").textContent = "";
  modal.style.display = "flex";
}

function cerrarCajaFuerte() {
  const modal = document.getElementById("caja-fuerte-puzzle");
  if (modal) modal.style.display = "none";
}

function cambiarDial(index, delta) {
  dialValues[index] += delta;
  if (dialValues[index] > 9) dialValues[index] = 0;
  if (dialValues[index] < 0) dialValues[index] = 9;
  document.getElementById(`dial-${index}`).textContent = dialValues[index];
  document.getElementById("caja-fuerte-status").textContent = "";
}

function intentarAbrirCaja() {
  const comb = dialValues.join("");
  const btn = document.getElementById("btn-abrir-caja");
  const status = document.getElementById("caja-fuerte-status");

  btn.disabled = true;
  btn.textContent = "ABRIENDO...";

  fetch("../src/api/resolver_caja.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ combinacion: comb }),
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        cerrarCajaFuerte();
        mostrarMensajeEnPantalla("[CAJA FUERTE] " + data.message);
        // Ocultar la caja fuerte interactiva
        document.querySelectorAll(".hotspot").forEach((h) => {
          if (h.title.includes("CAJA FUERTE")) h.style.display = "none";
        });
      } else {
        status.textContent = "⚠ " + data.error;
      }
    })
    .catch((err) => {
      console.error("Error al abrir caja:", err);
      status.textContent = "⚠ Error de conexión";
    })
    .finally(() => {
      btn.disabled = false;
      btn.textContent = "ABRIR";
    });
}

function guardarEnSlot(slotNumero) {
  fetch("../src/api/guardar_partida.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ slot_numero: slotNumero }),
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        mostrarMensajeEnPantalla(data.message);
        cerrarMenuGuardado();
      } else {
        mostrarMensajeEnPantalla("[ERROR] " + data.error);
      }
    })
    .catch((error) => console.error("Error al guardar:", error));
}

// Listeners globales de DOMContentLoaded
document.addEventListener("DOMContentLoaded", () => {
  const btnCancel = document.getElementById("btn-cancelar-guardado");
  if (btnCancel) btnCancel.onclick = cerrarMenuGuardado;

  const btnCerrarNota = document.getElementById("btn-cerrar-nota");
  if (btnCerrarNota) {
    btnCerrarNota.addEventListener("click", () => {
      document.getElementById("note-viewer").style.display = "none";
    });
  }

  // Botón de completar puzzle de medallones
  const btnColocar = document.getElementById("btn-colocar-medallones");
  if (btnColocar) {
    btnColocar.addEventListener("click", completarPuzzleMedallones);
  }
});

window.addEventListener("keydown", (e) => {
  if (e.key.toLowerCase() === "escape") {
    const viewer = document.getElementById("note-viewer");
    if (viewer && viewer.style.display === "flex") {
      viewer.style.display = "none";
    }

    const saveMenu = document.getElementById("save-menu");
    if (saveMenu && saveMenu.style.display === "flex") {
      cerrarMenuGuardado();
    }

    // ESC también cierra el puzzle de medallones
    const medallonesPuzzle = document.getElementById("medallones-puzzle");
    if (medallonesPuzzle && medallonesPuzzle.style.display === "flex") {
      cerrarMenuMedallones();
    }

    // ESC también cierra la caja fuerte
    const cajaPuzzle = document.getElementById("caja-fuerte-puzzle");
    if (cajaPuzzle && cajaPuzzle.style.display === "flex") {
      cerrarCajaFuerte();
    }
  }
});
