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

  setTimeout(() => {
    notif.classList.remove("show");
    setTimeout(() => {
      notif.style.display = "none";
    }, 300);
  }, 3000);
}

function ejecutarEvento(evento, event) {
  console.log("Evento clickeado:", evento);

  if (evento.script && typeof window[evento.script] === "function" &&
    evento.script !== "ejecutarEvento" &&
    evento.script !== "intentarAbrir" &&
    evento.script !== "recogerObjeto" &&
    evento.script !== "añadirInventario" &&
    evento.script !== "abrirMenuArchivo") {
    window[evento.script](evento, event);
    return;
  }

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
        target.onclick = null;
      }
      break;

    case "leer_archivo":
    case "nota":
      const noteViewer = document.getElementById("note-viewer");
      const titleEl = document.getElementById("note-title");
      const bodyEl = document.getElementById("note-body");
      const imgEl = document.getElementById("note-img");

      if (imgEl) {
        imgEl.src = "../img/nota.png";
        imgEl.style.display = "block";
      }

      if (evento.tipo_accion === "leer_archivo") {
        const idArchivo = evento.contenido_accion;
        const archivo =
          typeof catalogoArchivos !== "undefined"
            ? catalogoArchivos.find((a) => a.id_archivo == idArchivo)
            : null;

        if (archivo) {
          titleEl.innerText = archivo.nombre;
          bodyEl.innerText = archivo.informacion;
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
        if (
          typeof estadoActual !== "undefined" &&
          typeof ESTADOS_JUEGO !== "undefined"
        ) {
          estadoActual = ESTADOS_JUEGO.PAUSA;
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

    case "jefe_final":
      mostrarMensajeEnPantalla("[CRÍTICO] ¡ALGO SE MUEVE TRAS LA PUERTA!");
      const bossId = evento.contenido_accion;

      const formData = new FormData();
      formData.append("id_boss", bossId);
      formData.append("sala", salaActual);

      fetch("../src/api/spawn_jefe.php", {
        method: "POST",
        body: formData,
      })
        .then((r) => r.json())
        .then((data) => {
          if (data.success) {
            setTimeout(() => {
              window.location.href = `combate.php?id_registro=${data.id_registro}&vuelta=${salaActual}`;
            }, 1000);
          } else {
            console.error("Error spawn jefe:", data.error);
          }
        })
        .catch((err) => console.error("Error en petición spawn:", err));
      break;

    case "desbloquear":
      usarHerramienta(evento, event);
      break;

    case "mensaje":
      mostrarMensajeEnPantalla(`[AVISO] ${evento.contenido_accion}`);
      break;

    default:
      console.warn("Tipo de acción no reconocido:", evento.tipo_accion);
      break;
  }
}

function usarHerramienta(evento, event) {
  const itemRequerido = evento.requiere_item;
  if (!itemRequerido) {
    ejecutarEvento(evento, event);
    return;
  }

  mostrarMensajeEnPantalla(`[INVESTIGAR] ${evento.nombre_objeto}. Parece que requiere algo...`);

  // Verificar inventario
  fetch("../src/api/get_inventario.php")
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        const itemObj = data.inventario.find(
          (i) => i.nombre.toLowerCase().includes(itemRequerido.toLowerCase())
        );

        if (itemObj) {
          if (confirm(`¿Quieres usar el ${itemRequerido}?`)) {
            // Consumir el objeto
            fetch("../includes/usar_item.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                id_registro: itemObj.id_registro,
                fuente: itemObj.fuente || "db",
                sesion_idx: itemObj.sesion_idx,
                tipo: "herramienta",
                nombre: itemObj.nombre
              }),
            });

            registrarRecogida(evento.id_evento, "item", 0);
            mostrarMensajeEnPantalla(`[ÉXITO] Has usado el ${itemRequerido}.`);
            if (event && event.currentTarget) {
              event.currentTarget.style.display = "none";
            }
            if (evento.tipo_accion === "desbloquear") {
              setTimeout(() => window.location.reload(), 1500);
            }
          }
        } else {
          setTimeout(() => {
            mostrarMensajeEnPantalla(`[AVISO] Necesitas el objeto: ${itemRequerido}`);
          }, 1500);
        }
      }
    })
    .catch((err) => console.error("Error en usarHerramienta:", err));
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
        if (typeof actualizarInventarioSilent === "function") {
          actualizarInventarioSilent();
        }
      } else {
        console.error("Error al registrar:", data.error);
      }
    })
    .catch((error) => console.error("Error en la petición:", error));
}

function abrirMenuGuardado() {
  const menu = document.getElementById("save-menu");
  const ribbonSpan = document.getElementById("ribbon-count");
  if (!menu) {
    console.error("save-menu not found");
    return;
  }

  fetch("../src/api/get_save_slots.php")
    .then((r) => r.json())
    .then((data) => {
      if (!data.success) return;

      if (ribbonSpan) ribbonSpan.innerText = data.cintas;

      document.querySelectorAll(".save-slot").forEach((slotDiv) => {
        const num = slotDiv.dataset.slot;
        const info = data.slots[num];
        const statusSpan = slotDiv.querySelector(".slot-status");
        const dateSpan = slotDiv.querySelector(".slot-date");

        if (info) {
          if (statusSpan) statusSpan.innerText = info.ubicacion;
          if (dateSpan) dateSpan.innerText = info.fecha;
          slotDiv.classList.add("occupied");
        } else {
          if (statusSpan) statusSpan.innerText = "VACÍO";
          if (dateSpan) dateSpan.innerText = "--/--/-- --:--";
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
            mostrarMensajeEnPantalla(
              "[AVISO] Necesitas una Cinta de Guardado para guardar.",
            );
          }
        };
      });

      menu.style.display = "flex";
    })
    .catch((err) => console.error("Error en abrirMenuGuardado:", err));
}

function cerrarMenuGuardado() {
  document.getElementById("save-menu").style.display = "none";
}

const MEDALLON_SLOTS = {
  6: "leon",
  7: "unicornio",
  8: "doncella",
};

const MEDALLON_NOMBRES = {
  6: "León",
  7: "Unicornio",
  8: "Doncella",
};

let medallonesPuzzleState = {
  disponibles: [],
  colocados: [],
};


function abrirMenuPuzzle(tipo, event) {
  if (typeof tipo === "object" && tipo !== null && tipo.contenido_accion) {
    tipo = tipo.contenido_accion;
  }
  if (tipo === "medallones") {
    abrirPuzzleMedallones();
  } else if (tipo === "caja_fuerte") {
    abrirCajaFuerte();
  } else if (tipo === "portable") {
    abrirPortableSafe();
  } else if (tipo === "electricidad") {
    abrirPuzzleElectricidad();
  } else if (tipo.startsWith("puzzle_")) {
    abrirEstatuaPuzzle(tipo);
  } else {
    mostrarMensajeEnPantalla(`[PUZZLE] Se requiere resolver: ${tipo}`);
  }
}

const SIMBOLOS_PUZZLE = [
  "Leon",
  "Rama",
  "Ave",
  "Pez",
  "Escorpion",
  "Jarra",
  "Mujer",
  "Arco",
  "Serpiente",
  "Lobo",
  "Aguila",
  "Calavera",
];

let estatuaPuzzleState = {
  tipo: "",
  valores: [0, 0, 0], // Índices en SIMBOLOS_PUZZLE
};

function abrirEstatuaPuzzle(tipo) {
  const modal = document.getElementById("estatua-puzzle");
  if (!modal) return;

  estatuaPuzzleState.tipo = tipo;
  estatuaPuzzleState.valores = [0, 0, 0];

  const titulos = {
    puzzle_leon: "ESTATUA DEL LEÓN",
    puzzle_unicornio: "ESTATUA DEL UNICORNIO",
    puzzle_doncella: "ESTATUA DE LA DONCELLA",
  };

  document.getElementById("estatua-titulo").textContent =
    titulos[tipo] || "ESTATUA";
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
    document.getElementById(`symbol-${i}`).textContent =
      SIMBOLOS_PUZZLE[valIdx];
  }
}

function intentarResolverEstatua() {
  const comb = estatuaPuzzleState.valores.map((idx) => SIMBOLOS_PUZZLE[idx]);
  const btn = document.getElementById("btn-resolver-estatua");
  const status = document.getElementById("estatua-status");

  btn.disabled = true;
  status.textContent = "Verificando...";

  fetch("../src/api/resolver_puzzle_medallon.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      puzzle: estatuaPuzzleState.tipo,
      combinacion: comb,
    }),
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        cerrarEstatuaPuzzle();
        mostrarMensajeEnPantalla("[ESTATUA] " + data.message);
        mostrarNotificacionCentrada(data.nombre_objeto);
        if (typeof actualizarInventarioSilent === "function") {
          actualizarInventarioSilent();
        }

        const hotspots = document.querySelectorAll(".hotspot");
        hotspots.forEach((h) => {
          if (
            h.onclick &&
            h.onclick.toString().includes(estatuaPuzzleState.tipo)
          ) {
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

  medallonesPuzzleState.disponibles = [];
  medallonesPuzzleState.colocados = [];

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

function actualizarSlotsMedallones() {
  Object.entries(MEDALLON_SLOTS).forEach(([idStr, nombre]) => {
    const id = Number(idStr);
    const slot = document.getElementById(`slot-${nombre}`);
    const placed = document.getElementById(`placed-${nombre}`);

    slot.classList.remove("available", "placed", "unavailable");

    if (medallonesPuzzleState.colocados.includes(id)) {
      slot.classList.add("placed");
      placed.style.display = "flex";
      slot.onclick = () => quitarMedallon(id);
      slot.title = "Haz clic para retirar el medallón";
    } else if (medallonesPuzzleState.disponibles.includes(id)) {
      slot.classList.add("available");
      placed.style.display = "none";
      slot.onclick = () => colocarMedallon(id);
      slot.title = `Colocar Medallón de ${MEDALLON_NOMBRES[id]}`;
    } else {
      slot.classList.add("unavailable");
      placed.style.display = "none";
      slot.onclick = null;
      slot.title = `Necesitas el Medallón de ${MEDALLON_NOMBRES[id]}`;
    }
  });

  const todosColocados = [6, 7, 8].every((id) =>
    medallonesPuzzleState.colocados.includes(id),
  );
  document.getElementById("btn-colocar-medallones").disabled = !todosColocados;
  const faltantes = [6, 7, 8].filter(
    (id) => !medallonesPuzzleState.disponibles.includes(id),
  );
  const colocadosCount = medallonesPuzzleState.colocados.length;

  let statusMsg;
  if (faltantes.length > 0) {
    const nombres = faltantes.map((id) => MEDALLON_NOMBRES[id]).join(", ");
    statusMsg = `Te faltan: ${nombres}. Sigue explorando.`;
  } else if (todosColocados) {
    statusMsg = "Los tres medallones están listos. Pulsa ACTIVAR ESTATUA.";
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
        if (typeof actualizarInventarioSilent === "function") {
          actualizarInventarioSilent();
        }

        document.querySelectorAll(".hotspot").forEach((h) => {
          if (h.title === "ESTATUA") h.style.display = "none";
        });
        setTimeout(() => {
          window.location.reload();
        }, 2000);
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
        " Error de conexión.";
      btn.disabled = false;
      btn.textContent = "ACTIVAR ESTATUA";
    });
}

let dialValues = [0, 0, 0];

function abrirCajaFuerte() {
  const modal = document.getElementById("caja-fuerte-puzzle");
  if (!modal) return;

  fetch("../src/api/resolver_caja.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ combinacion: "__check__" }),
  })
    .then((r) => r.json())
    .then((data) => {
      if (
        !data.success &&
        data.error &&
        data.error.includes("ya fue abierta")
      ) {
        mostrarMensajeEnPantalla(
          "[CAJA FUERTE] Ya fue abierta. No hay nada más dentro.",
        );
        document.querySelectorAll(".hotspot").forEach((h) => {
          if (h.title && h.title.includes("CAJA FUERTE"))
            h.style.display = "none";
        });
        return;
      }
      dialValues = [0, 0, 0];
      for (let i = 0; i < 3; i++) {
        document.getElementById(`dial-${i}`).textContent = "0";
      }
      document.getElementById("caja-fuerte-status").textContent = "";
      modal.style.display = "flex";
    })
    .catch(() => {
      dialValues = [0, 0, 0];
      for (let i = 0; i < 3; i++) {
        document.getElementById(`dial-${i}`).textContent = "0";
      }
      document.getElementById("caja-fuerte-status").textContent = "";
      modal.style.display = "flex";
    });
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
        if (typeof actualizarInventarioSilent === "function") {
          actualizarInventarioSilent();
        }

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


document.addEventListener("DOMContentLoaded", () => {
  const btnCancel = document.getElementById("btn-cancelar-guardado");
  if (btnCancel) btnCancel.onclick = cerrarMenuGuardado;

  const btnCerrarNota = document.getElementById("btn-cerrar-nota");
  if (btnCerrarNota) {
    btnCerrarNota.addEventListener("click", () => {
      document.getElementById("note-viewer").style.display = "none";
      if (
        typeof estadoActual !== "undefined" &&
        typeof ESTADOS_JUEGO !== "undefined"
      ) {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    });
  }


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
      if (
        typeof estadoActual !== "undefined" &&
        typeof ESTADOS_JUEGO !== "undefined"
      ) {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }

    const saveMenu = document.getElementById("save-menu");
    if (saveMenu && saveMenu.style.display === "flex") {
      cerrarMenuGuardado();
      if (
        typeof estadoActual !== "undefined" &&
        typeof ESTADOS_JUEGO !== "undefined"
      ) {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }

    // ESC también cierra el puzzle de medallones
    const medallonesPuzzle = document.getElementById("medallones-puzzle");
    if (medallonesPuzzle && medallonesPuzzle.style.display === "flex") {
      cerrarMenuMedallones();
      if (
        typeof estadoActual !== "undefined" &&
        typeof ESTADOS_JUEGO !== "undefined"
      ) {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }

    // ESC también cierra la estatua puzzle
    const estatuaPuzzle = document.getElementById("estatua-puzzle");
    if (estatuaPuzzle && estatuaPuzzle.style.display === "flex") {
      cerrarEstatuaPuzzle();
      if (
        typeof estadoActual !== "undefined" &&
        typeof ESTADOS_JUEGO !== "undefined"
      ) {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }

    // ESC también cierra la portable
    const portableSafe = document.getElementById("portable-safe-puzzle");
    if (portableSafe && portableSafe.style.display === "flex") {
      cerrarPortableSafe();
      if (
        typeof estadoActual !== "undefined" &&
        typeof ESTADOS_JUEGO !== "undefined"
      ) {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }

    // ESC también cierra la caja fuerte
    const cajaPuzzle = document.getElementById("caja-fuerte-puzzle");
    if (cajaPuzzle && cajaPuzzle.style.display === "flex") {
      cerrarCajaFuerte();
      if (
        typeof estadoActual !== "undefined" &&
        typeof ESTADOS_JUEGO !== "undefined"
      ) {
        estadoActual = ESTADOS_JUEGO.INTERACTIVO;
      }
    }
  }
});

let psafeState = {
  grid: [],
  idRegistro: null,
};

function abrirPortableSafe(idRegistro = null) {
  const modal = document.getElementById("portable-safe-puzzle");
  if (!modal) return;

  psafeState.idRegistro = idRegistro;

  psafeState.grid = Array(9).fill(true);
  const moves = 10 + Math.floor(Math.random() * 8);
  for (let m = 0; m < moves; m++) {
    psafeToggleCell(Math.floor(Math.random() * 9), false);
  }
  if (psafeState.grid.every((v) => v)) psafeToggleCell(4, false);

  document.getElementById("portable-status").textContent = "";
  document.getElementById("portable-status").style.color = "#0af";

  psafeRender();
  psafeRenderRing();
  modal.style.display = "flex";
}

function cerrarPortableSafe() {
  const modal = document.getElementById("portable-safe-puzzle");
  if (modal) modal.style.display = "none";
}

function psafeToggleCell(idx, rerender = true) {
  const neighbors = psafeGetNeighbors(idx);
  [idx, ...neighbors].forEach((i) => {
    psafeState.grid[i] = !psafeState.grid[i];
  });
  if (rerender) {
    psafeRender();
    psafeRenderRing();
    if (psafeState.grid.every((v) => v)) setTimeout(psafeSolved, 250);
  }
}

function psafeGetNeighbors(idx) {
  const row = Math.floor(idx / 3),
    col = idx % 3;
  const nbrs = [];
  if (row > 0) nbrs.push(idx - 3);
  if (row < 2) nbrs.push(idx + 3);
  if (col > 0) nbrs.push(idx - 1);
  if (col < 2) nbrs.push(idx + 1);
  return nbrs;
}

function psafeRender() {
  const grid = document.getElementById("psafe-grid");
  if (!grid) return;
  grid.innerHTML = "";
  psafeState.grid.forEach((lit, idx) => {
    const btn = document.createElement("button");
    btn.className = "psafe-btn" + (lit ? " lit" : "");
    btn.innerHTML = '<div class="btn-light"></div>';
    btn.addEventListener("click", () => psafeToggleCell(idx));
    grid.appendChild(btn);
  });
}

function psafeRenderRing() {
  const ring = document.getElementById("light-ring");
  if (!ring) return;

  // Mantener el escudo, limpiar los dots
  ring.querySelectorAll(".light-dot").forEach((d) => d.remove());

  const litCount = psafeState.grid.filter((v) => v).length; // 0-9
  // 8 leds en el anillo, iluminar proporcionalmente
  for (let i = 0; i < 8; i++) {
    const dot = document.createElement("div");
    dot.className =
      "light-dot" + (i < Math.round((litCount * 8) / 9) ? " active" : "");
    const angle = i * 45 - 90;
    const radius = 54;
    const x = Math.cos((angle * Math.PI) / 180) * radius;
    const y = Math.sin((angle * Math.PI) / 180) * radius;
    dot.style.left = `calc(50% + ${x}px - 6.5px)`;
    dot.style.top = `calc(50% + ${y}px - 6.5px)`;
    ring.appendChild(dot);
  }
}

function psafeSolved() {
  const status = document.getElementById("portable-status");
  status.style.color = "#0f0";
  status.textContent = "✓ ACCESO CONCEDIDO";

  setTimeout(() => {
    fetch("../src/api/resolver_portable.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id_registro: psafeState.idRegistro }),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.success) {
          cerrarPortableSafe();
          mostrarNotificacionCentrada(data.nombre_objeto);
          mostrarMensajeEnPantalla(`[PORTÁTIL] ${data.message}`);
          if (typeof actualizarInventarioSilent === "function")
            actualizarInventarioSilent();
        } else {
          status.style.color = "#f33";
          status.textContent = data.error || "Error al resolver.";
        }
      })
      .catch((err) => console.error("Error portable safe:", err));
  }, 1200);
}

// ════════════════════════════════════════════════════════
//  PUZZLE ELÉCTRICO — CIRCUITO DE FUSIBLES
// ════════════════════════════════════════════════════════
const ELEC_CONNS = {
  I: [
    ["E", "W"],
    ["N", "S"],
    ["E", "W"],
    ["N", "S"],
  ],
  L: [
    ["E", "S"],
    ["S", "W"],
    ["N", "W"],
    ["N", "E"],
  ],
  SRC: [["E"], ["E"], ["E"], ["E"]],
  TGT: [["W"], ["W"], ["W"], ["W"]],
  B: [[], [], [], []],
};

function elecGetSVGPath(type, rot) {
  if (type === "B") return "";
  if (type === "SRC") return "M4,40 L76,40";
  if (type === "TGT") return "M4,40 L76,40";
  const r = rot % (type === "I" ? 2 : 4);
  if (type === "I") return r === 0 ? "M0,40 L80,40" : "M40,0 L40,80";
  return [
    "M80,40 Q40,40 40,80",
    "M40,80 Q40,40 0,40",
    "M0,40 Q40,40 40,0",
    "M40,0 Q40,40 80,40",
  ][r];
}

// Grid 5×4. Solution: SRC(1,0)→L1(1,1)→L3(2,1)→I0(2,2)→L2(2,3)→L0(1,3)→TGT(1,4)
let elecGrid = null;
function elecInitGrid() {
  return [
    [
      { t: "B" },
      { t: "L", r: 3, sr: 0 },
      { t: "I", r: 0, sr: 1 },
      { t: "L", r: 2, sr: 1 },
      { t: "B" },
    ],
    [
      { t: "SRC", r: 0, fixed: true },
      { t: "L", r: 0, sr: 1 },
      { t: "B" },
      { t: "L", r: 1, sr: 0 },
      { t: "TGT", r: 0, fixed: true },
    ],
    [
      { t: "B" },
      { t: "L", r: 1, sr: 3 },
      { t: "I", r: 1, sr: 0 },
      { t: "L", r: 0, sr: 2 },
      { t: "B" },
    ],
    [
      { t: "B" },
      { t: "L", r: 0, sr: 3 },
      { t: "I", r: 0, sr: 1 },
      { t: "L", r: 1, sr: 0 },
      { t: "B" },
    ],
  ];
}

function abrirPuzzleElectricidad() {
  const modal = document.getElementById("elec-puzzle");
  if (!modal) return;
  elecGrid = elecInitGrid();
  elecRenderGrid();
  modal.style.display = "flex";
}

function cerrarPuzzleElectricidad() {
  const modal = document.getElementById("elec-puzzle");
  if (modal) modal.style.display = "none";
}

function elecRenderGrid() {
  const gridEl = document.getElementById("elec-grid");
  if (!gridEl || !elecGrid) return;
  gridEl.innerHTML = "";
  const energized = elecGetEnergized();

  elecGrid.forEach((row, ri) => {
    row.forEach((cell, ci) => {
      const div = document.createElement("div");
      div.className = "elec-cell" + (cell.t === "B" ? " elec-blank" : "");

      if (cell.t !== "B") {
        const isOn = energized.has(`${ri},${ci}`);
        const svg = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "svg",
        );
        svg.setAttribute("viewBox", "0 0 80 80");
        svg.setAttribute("width", "100%");
        svg.setAttribute("height", "100%");

        const pathStr = elecGetSVGPath(cell.t, cell.r || 0);
        if (pathStr) {
          const p = document.createElementNS(
            "http://www.w3.org/2000/svg",
            "path",
          );
          p.setAttribute("d", pathStr);
          p.setAttribute("stroke", isOn ? "#7f0" : "#2a3a2a");
          p.setAttribute("stroke-width", "9");
          p.setAttribute("fill", "none");
          p.setAttribute("stroke-linecap", "round");
          if (isOn)
            p.style.filter =
              "drop-shadow(0 0 5px #7f0) drop-shadow(0 0 10px #4a0)";
          svg.appendChild(p);
        }

        const dot = document.createElementNS(
          "http://www.w3.org/2000/svg",
          "circle",
        );
        dot.setAttribute("cx", "40");
        dot.setAttribute("cy", "40");
        dot.setAttribute("r", "6");
        dot.setAttribute("fill", isOn ? "#9f2" : "#1a281a");
        dot.setAttribute("stroke", isOn ? "#7f0" : "#2a3a2a");
        dot.setAttribute("stroke-width", "2");
        if (isOn) dot.style.filter = "drop-shadow(0 0 6px #9f2)";
        svg.appendChild(dot);
        div.appendChild(svg);

        if (cell.t === "SRC") {
          const lbl = document.createElement("span");
          lbl.className = "elec-label energized";
          lbl.textContent = "PWR";
          div.appendChild(lbl);
        }
        if (cell.t === "TGT") {
          const lbl = document.createElement("span");
          lbl.className = "elec-label" + (isOn ? " energized" : "");
          lbl.textContent = isOn ? "✓ ON" : "OFF";
          div.appendChild(lbl);
        }

        if (!cell.fixed) {
          div.style.cursor = "pointer";
          div.addEventListener("click", () => {
            cell.r = ((cell.r || 0) + 1) % (cell.t === "I" ? 2 : 4);
            elecRenderGrid();
            if (elecCheckSolved()) setTimeout(elecOnSolved, 300);
          });
        }
      }
      gridEl.appendChild(div);
    });
  });
}

function elecGetEnergized() {
  const energized = new Set();
  const queue = [{ r: 1, c: 0, from: "W" }];
  const OPP = { N: "S", S: "N", E: "W", W: "E" };
  const DELTA = { N: [-1, 0], S: [1, 0], E: [0, 1], W: [0, -1] };

  while (queue.length) {
    const { r, c, from } = queue.shift();
    const key = `${r},${c}`;
    if (energized.has(key)) continue;
    const cell = elecGrid[r]?.[c];
    if (!cell) continue;
    const rot = cell.r || 0;
    const conns =
      (ELEC_CONNS[cell.t] || [[]])[rot % (cell.t === "I" ? 2 : 4)] || [];
    if (cell.t !== "SRC" && !conns.includes(from)) continue;
    energized.add(key);
    for (const dir of conns) {
      if (dir === from) continue;
      const [dr, dc] = DELTA[dir];
      const nr = r + dr,
        nc = c + dc;
      if (nr < 0 || nr >= 4 || nc < 0 || nc >= 5) continue;
      queue.push({ r: nr, c: nc, from: OPP[dir] });
    }
  }
  return energized;
}

function elecCheckSolved() {
  return elecGetEnergized().has("1,4");
}

function elecOnSolved() {
  const gridEl = document.getElementById("elec-grid");
  const statusEl = document.getElementById("elec-status");
  const wireR = document.querySelector(".elec-wire-right");
  if (gridEl) gridEl.classList.add("elec-solved");
  if (wireR) wireR.style.opacity = "1";
  if (statusEl) {
    statusEl.textContent = "⚡ CIRCUITO COMPLETADO — SISTEMA RESTAURADO";
    statusEl.style.color = "#7f0";
    statusEl.style.textShadow = "0 0 8px #4a0";
  }
  setTimeout(() => {
    fetch("../src/api/resolver_electricidad.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ accion: "completar" }),
    })
      .then((r) => r.json())
      .then((data) => {
        cerrarPuzzleElectricidad();
        mostrarMensajeEnPantalla(
          "[ELÉCTRICO] " + (data.message || "Sistema restaurado."),
        );
        document.querySelectorAll(".hotspot").forEach((h) => {
          if (h.title === "PUZZLE FUSIBLES") h.style.display = "none";
        });
        if (typeof actualizarInventarioSilent === "function")
          actualizarInventarioSilent();
      })
      .catch((err) => console.error("Error electricidad:", err));
  }, 1800);
}

window.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    const m = document.getElementById("elec-puzzle");
    if (m && m.style.display === "flex") cerrarPuzzleElectricidad();
  }
});
