const ESTADOS_JUEGO = {
  INTERACTIVO: "interactivo",
  INVENTARIO: "inventario",
  BATALLA: "batalla",
  PAUSA: "pausa",
};

let estadoActual = ESTADOS_JUEGO.INTERACTIVO;

const teclasPresionadas = {
  w: false,
  a: false,
  s: false,
  d: false,
};

function mostrarMensajeEnPantalla(mensaje) {
  const messageBox = document.querySelector(".message-box p");
  if (messageBox) {
    messageBox.innerHTML = `<strong>> ${mensaje}</strong>`;
  }
  console.log(mensaje);
}

const gameActions = {
  elegirRuta: function (direccion) {
    let selector = "";
    if (direccion === "w") selector = ".north";
    if (direccion === "s") selector = ".south";
    if (direccion === "d") selector = ".east";
    if (direccion === "a") selector = ".west";

    const flecha = document.querySelector(`.nav-btn${selector}`);
    if (flecha) {
      mostrarMensajeEnPantalla(`Avanzando hacia la siguiente sala...`);
      window.location.href = flecha.href;
    } else {
      mostrarMensajeEnPantalla(
        `No hay camino en esa dirección. No puedes avanzar.`,
      );
    }
  },
  abrirMenuInventario: function () {
    mostrarMensajeEnPantalla(
      "[Menú de Inventario Abierto]<br> Opciones: [F] Combinar - [I] Examinar - [ESC] Salir",
    );
    estadoActual = ESTADOS_JUEGO.INVENTARIO;
  },
  salirJuegoOPausa: function () {
    mostrarMensajeEnPantalla("Juego en Pausa.");
    estadoActual = ESTADOS_JUEGO.PAUSA;

    const pauseMenu = document.getElementById('pause-menu');
    if (pauseMenu) pauseMenu.style.display = 'flex';
  },
  reanudarJuego: function () {
    mostrarMensajeEnPantalla("Juego reanudado.");
    estadoActual = ESTADOS_JUEGO.INTERACTIVO;

    const pauseMenu = document.getElementById('pause-menu');
    if (pauseMenu) pauseMenu.style.display = 'none';
  },
  cargarPartida: function () {
    mostrarMensajeEnPantalla("Cargando partida... (Funcionalidad pendiente)");
  },
  salirAlMenuPrincipal: function () {
    mostrarMensajeEnPantalla("Saliendo del juego...");
    window.location.href = '../index.php';
  },
  combinarItems: function () {
    mostrarMensajeEnPantalla(
      "[Inventario] Has seleccionado combinar items... (Falta lógica visual)",
    );
  },
  examinarItem: function () {
    mostrarMensajeEnPantalla("[Inventario] Examinando item detalladamente...");
  },
  cerrarMenuInventario: function () {
    mostrarMensajeEnPantalla(
      "Has cerrado el inventario. Volviendo a exploración.",
    );
    estadoActual = ESTADOS_JUEGO.INTERACTIVO;
  },
  disparar: function () {
    mostrarMensajeEnPantalla(" Has disparado al enemigo.");
  },
  huir: function () {
    mostrarMensajeEnPantalla(
      " Has huido del combate de forma cobarde. Volviendo a la exploración.",
    );
    estadoActual = ESTADOS_JUEGO.INTERACTIVO;
  },
  entrarEnBatalla: function () {
    mostrarMensajeEnPantalla(
      "Has entrado en modo combate.<br> Opciones: [G] Disparar - [H] Huir",
    );
    estadoActual = ESTADOS_JUEGO.BATALLA;
  },
};

document.addEventListener("DOMContentLoaded", () => {
  const btnContinuar = document.getElementById('btn-continuar');
  const btnCargar = document.getElementById('btn-cargar');
  const btnSalir = document.getElementById('btn-salir');

  if (btnContinuar) btnContinuar.addEventListener('click', gameActions.reanudarJuego);
  if (btnCargar) btnCargar.addEventListener('click', gameActions.cargarPartida);
  if (btnSalir) btnSalir.addEventListener('click', gameActions.salirAlMenuPrincipal);
});

window.addEventListener("keydown", (e) => {
  const key = e.key.toLowerCase();

  switch (estadoActual) {
    case ESTADOS_JUEGO.INTERACTIVO:
      if (["w", "a", "s", "d"].includes(key)) {
        if (!teclasPresionadas[key]) {
          teclasPresionadas[key] = true;
          gameActions.elegirRuta(key);
        }
      }
      if (key === "e") {
        gameActions.abrirMenuInventario();
      }
      if (key === "escape") {
        gameActions.salirJuegoOPausa();
      }
      if (key === "b") {
        gameActions.entrarEnBatalla();
      }
      break;

    case ESTADOS_JUEGO.INVENTARIO:
      if (key === "f") {
        gameActions.combinarItems();
      }
      if (key === "i") {
        gameActions.examinarItem();
      }
      if (key === "escape") {
        gameActions.cerrarMenuInventario();
      }
      break;

    case ESTADOS_JUEGO.BATALLA:
      if (key === "g") {
        gameActions.disparar();
      }
      if (key === "h") {
        gameActions.huir();
      }
      break;

    case ESTADOS_JUEGO.PAUSA:
      if (key === "escape") {
        gameActions.reanudarJuego();
      }
      break;
  }
});

window.addEventListener("keyup", (e) => {
  const key = e.key.toLowerCase();

  if (estadoActual === ESTADOS_JUEGO.INTERACTIVO) {
    if (["w", "a", "s", "d"].includes(key)) {
      teclasPresionadas[key] = false;
    }
  }
});
