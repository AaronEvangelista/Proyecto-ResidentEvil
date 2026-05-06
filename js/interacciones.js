function mostrarMensajeEnPantalla(mensaje) {
  const messageBox = document.querySelector(".message-box p");
  if (messageBox) {
    messageBox.innerHTML = mensaje;
  }
}

function mostrarNotificacionCentrada(nombre) {
  const notif = document.getElementById("item-notification");
  const nameEl = document.getElementById("notif-item-name");
  if (!notif || !nameEl) return;

  nameEl.textContent = nombre;
  notif.style.display = "flex";
  
  // Pequeño delay para la transición de entrada
  setTimeout(() => {
    notif.classList.add("show");
  }, 10);

  // Ocultar después de 3 segundos
  setTimeout(() => {
    notif.classList.remove("show");
    setTimeout(() => {
      notif.style.display = "none";
    }, 300);
  }, 3000);
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
      mostrarNotificacionCentrada(evento.nombre_objeto);

      registrarRecogida(evento.id_evento, tipo, evento.contenido_accion);

      const target = event.currentTarget;
      if (target) {
        target.style.display = "none";
        target.onclick = null; // Evitar re-clicks si tarda en desaparecer
      }
      break;

    case "leer_archivo":
    case "nota":
      const noteViewer = document.getElementById("note-viewer");
      const titleEl = document.getElementById("note-title");
      const bodyEl = document.getElementById("note-body");
      const imgEl = document.getElementById("note-img");

      if (evento.tipo_accion === "leer_archivo") {
        const idArchivo = evento.contenido_accion;
        const archivo = typeof catalogoArchivos !== "undefined" 
          ? catalogoArchivos.find((a) => a.id_archivo == idArchivo)
          : null;

        if (archivo) {
          titleEl.innerText = archivo.nombre;
          bodyEl.innerText = archivo.informacion;
          if (archivo.imagen_url && imgEl) {
            imgEl.src = archivo.imagen_url;
            imgEl.style.display = "block";
          } else if (imgEl) {
            imgEl.src = "../img/nota.png"; // Imagen por defecto
          }
        } else {
          titleEl.innerText = evento.nombre_objeto;
          bodyEl.innerText = "No se pudo cargar el contenido del archivo.";
        }
      } else {
        titleEl.innerText = evento.nombre_objeto;
        bodyEl.innerText = evento.contenido_accion;
      }

      if (noteViewer) {
        noteViewer.style.display = "flex";
        // Actualizar estado si existe el sistema de estados
        if (typeof estadoActual !== "undefined" && typeof ESTADOS_JUEGO !== "undefined") {
          estadoActual = ESTADOS_JUEGO.PAUSA; // O un estado específico para lectura
        }
      }
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
  } else if (tipo === "portable") {
    // Esto se llama si el evento es directo, pero usualmente es desde inventario
    abrirPortableSafe();
  } else if (tipo.startsWith("puzzle_")) {
    abrirEstatuaPuzzle(tipo);
  } else {
    mostrarMensajeEnPantalla(`[PUZZLE] Se requiere resolver: ${tipo}`);
  }
}

// ════════════════════════════════════════════════
//  PUZZLE ESTATUAS (PARA OBTENER MEDALLONES)
// ════════════════════════════════════════════════

const SIMBOLOS_PUZZLE = [
  "Leon", "Rama", "Ave", "Pez", "Escorpion", "Jarra", 
  "Mujer", "Arco", "Serpiente", "Lobo", "Aguila", "Calavera"
];

let estatuaPuzzleState = {
  tipo: "",
  valores: [0, 0, 0] // Índices en SIMBOLOS_PUZZLE
};

function abrirEstatuaPuzzle(tipo) {
  const modal = document.getElementById("estatua-puzzle");
  if (!modal) return;

  estatuaPuzzleState.tipo = tipo;
  estatuaPuzzleState.valores = [0, 0, 0];

  const titulos = {
    "puzzle_leon": "ESTATUA DEL LEÓN",
    "puzzle_unicornio": "ESTATUA DEL UNICORNIO",
    "puzzle_doncella": "ESTATUA DE LA DONCELLA"
  };

  document.getElementById("estatua-titulo").textContent = titulos[tipo] || "ESTATUA";
  document.getElementById("estatua-status").textContent = "";

  actualizarVisualEstatua();
  modal.style.display = "flex";
}

function cerrarEstatuaPuzzle() {
  const modal = document.getElementById("estatua-puzzle");
  if (modal) modal.style.display = "none";
}

function cambiarSimbolo(index, delta) {
  let val = estatuaPuzzleState.valores[index] + delta;
  if (val >= SIMBOLOS_PUZZLE.length) val = 0;
  if (val < 0) val = SIMBOLOS_PUZZLE.length - 1;
  
  estatuaPuzzleState.valores[index] = val;
  actualizarVisualEstatua();
}

function actualizarVisualEstatua() {
  for (let i = 0; i < 3; i++) {
    const valIdx = estatuaPuzzleState.valores[i];
    document.getElementById(`symbol-${i}`).textContent = SIMBOLOS_PUZZLE[valIdx];
  }
}

function intentarResolverEstatua() {
  const comb = estatuaPuzzleState.valores.map(idx => SIMBOLOS_PUZZLE[idx]);
  const btn = document.getElementById("btn-resolver-estatua");
  const status = document.getElementById("estatua-status");

  btn.disabled = true;
  status.textContent = "Verificando...";

  fetch("../src/api/resolver_puzzle_medallon.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ 
      puzzle: estatuaPuzzleState.tipo,
      combinacion: comb 
    }),
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        cerrarEstatuaPuzzle();
        mostrarMensajeEnPantalla("[ESTATUA] " + data.message);
        mostrarNotificacionCentrada(data.nombre_objeto);
        
        // El hotspot se oculta automáticamente al recargar o si manejamos el DOM
        // Pero como estamos en una SPA-like, podemos ocultarlo manualmente
        const hotspots = document.querySelectorAll(".hotspot");
        hotspots.forEach(h => {
            // Buscamos el hotspot que corresponde a este puzzle
            if (h.onclick && h.onclick.toString().includes(estatuaPuzzleState.tipo)) {
                h.style.display = "none";
            }
        });
      } else {
        status.textContent = "⚠ " + data.error;
      }
    })
    .catch((err) => {
      console.error("Error al resolver estatua:", err);
      status.textContent = "⚠ Error de conexión";
    })
    .finally(() => {
      btn.disabled = false;
    });
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
        mostrarNotificacionCentrada(data.nombre_objeto);
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
      if (typeof estadoActual !== "undefined" && typeof ESTADOS_JUEGO !== "undefined") {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
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
      if (typeof estadoActual !== "undefined" && typeof ESTADOS_JUEGO !== "undefined") {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }

    const saveMenu = document.getElementById("save-menu");
    if (saveMenu && saveMenu.style.display === "flex") {
      cerrarMenuGuardado();
      if (typeof estadoActual !== "undefined" && typeof ESTADOS_JUEGO !== "undefined") {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }

    // ESC también cierra el puzzle de medallones
    const medallonesPuzzle = document.getElementById("medallones-puzzle");
    if (medallonesPuzzle && medallonesPuzzle.style.display === "flex") {
      cerrarMenuMedallones();
      if (typeof estadoActual !== "undefined" && typeof ESTADOS_JUEGO !== "undefined") {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }

    // ESC también cierra la estatua puzzle
    const estatuaPuzzle = document.getElementById("estatua-puzzle");
    if (estatuaPuzzle && estatuaPuzzle.style.display === "flex") {
      cerrarEstatuaPuzzle();
      if (typeof estadoActual !== "undefined" && typeof ESTADOS_JUEGO !== "undefined") {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }

    // ESC también cierra la portable
    const portableSafe = document.getElementById("portable-safe-puzzle");
    if (portableSafe && portableSafe.style.display === "flex") {
      cerrarPortableSafe();
      if (typeof estadoActual !== "undefined" && typeof ESTADOS_JUEGO !== "undefined") {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }

    // ESC también cierra la caja fuerte
    const cajaPuzzle = document.getElementById("caja-fuerte-puzzle");
    if (cajaPuzzle && cajaPuzzle.style.display === "flex") {
      cerrarCajaFuerte();
      if (typeof estadoActual !== "undefined" && typeof ESTADOS_JUEGO !== "undefined") {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }
  }
});
// ════════════════════════════════════════════════
//  PUZZLE CAJA FUERTE PORTÁTIL
// ════════════════════════════════════════════════

let portableSafeState = {
  active: false,
  idRegistro: null,
  sequence: [], // Orden correcto de botones
  currentIndex: 0, // Cuántos ha acertado seguidos
  lights: [] // Referencias a los elementos DOM de las luces
};

function abrirPortableSafe(idRegistro = null) {
  const modal = document.getElementById("portable-safe-puzzle");
  if (!modal) return;

  portableSafeState.active = true;
  portableSafeState.idRegistro = idRegistro;
  portableSafeState.currentIndex = 0;
  
  // Generar secuencia aleatoria de 0 a 7
  const buttons = [0, 1, 2, 3, 4, 5, 6, 7];
  portableSafeState.sequence = buttons.sort(() => Math.random() - 0.5);

  document.getElementById("portable-status").textContent = "";
  
  // Generar luces en círculo
  const ring = document.getElementById("light-ring");
  // Limpiar luces previas pero mantener el logo si existe
  const dots = ring.querySelectorAll('.light-dot');
  dots.forEach(d => d.remove());

  portableSafeState.lights = [];
  for (let i = 0; i < 8; i++) {
    const dot = document.createElement("div");
    dot.className = "light-dot";
    
    // Posicionamiento circular
    const angle = (i * 45) - 90; // Empezar arriba
    const radius = 70;
    const x = Math.cos(angle * (Math.PI / 180)) * radius;
    const y = Math.sin(angle * (Math.PI / 180)) * radius;
    
    dot.style.left = `calc(50% + ${x}px - 7.5px)`;
    dot.style.top = `calc(50% + ${y}px - 7.5px)`;
    
    ring.appendChild(dot);
    portableSafeState.lights.push(dot);
  }

  modal.style.display = "flex";
}

function cerrarPortableSafe() {
  const modal = document.getElementById("portable-safe-puzzle");
  if (modal) modal.style.display = "none";
  portableSafeState.active = false;
}

function pressPortableButton(btnIndex) {
  if (!portableSafeState.active) return;

  const expectedBtn = portableSafeState.sequence[portableSafeState.currentIndex];

  if (btnIndex === expectedBtn) {
    // Acierto
    portableSafeState.lights[portableSafeState.currentIndex].classList.add("active");
    portableSafeState.currentIndex++;

    if (portableSafeState.currentIndex === 8) {
      resolverPortableSafe();
    }
  } else {
    // Fallo: Reset
    portableSafeState.currentIndex = 0;
    portableSafeState.lights.forEach(l => l.classList.remove("active"));
    
    const status = document.getElementById("portable-status");
    status.textContent = "ERROR: SECUENCIA REINICIADA";
    setTimeout(() => { if(status && status.textContent.includes("ERROR")) status.textContent = ""; }, 1000);
  }
}

function resolverPortableSafe() {
  const status = document.getElementById("portable-status");
  status.style.color = "#00ff00";
  status.textContent = "ACCESO CONCEDIDO";

  fetch("../src/api/resolver_portable.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id_registro: portableSafeState.idRegistro }),
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        setTimeout(() => {
          cerrarPortableSafe();
          mostrarNotificacionCentrada(data.nombre_objeto);
          mostrarMensajeEnPantalla(`[PORTÁTIL] ${data.message}`);
          if (typeof abrirInventario === "function") abrirInventario();
        }, 1500);
      } else {
        status.style.color = "#ff3333";
        status.textContent = data.error;
      }
    })
    .catch((err) => {
      console.error("Error al resolver portable:", err);
    });
}
