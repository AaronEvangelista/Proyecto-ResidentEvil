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
  arrowup: false,
  arrowdown: false,
  arrowleft: false,
  arrowright: false
};

const gameActions = {
  elegirRuta: function (direccion) {
    let selector = "";
    //Mapeo de teclas a selectores
    const mapeo = {
        'w': '.north', 'arrowup': '.north',
        's': '.south', 'arrowdown': '.south',
        'd': '.east',  'arrowright': '.east',
        'a': '.west',  'arrowleft': '.west'
    };
    
    selector = mapeo[direccion] || "";

    const flecha = document.querySelector(`.nav-btn${selector}`);
    if (flecha) {
      if (typeof mostrarMensajeEnPantalla === 'function') {
        mostrarMensajeEnPantalla(`Avanzando hacia la siguiente sala...`);
      }
      window.location.href = flecha.href;
    } else {
      if (typeof mostrarMensajeEnPantalla === 'function') {
        mostrarMensajeEnPantalla(`No hay camino en esa dirección. No puedes avanzar.`);
      }
    }
  },
  abrirMenuInventario: function () {
    estadoActual = ESTADOS_JUEGO.INVENTARIO;
    if (typeof abrirInventario === 'function') abrirInventario();
  },
  salirJuegoOPausa: function () {
    estadoActual = ESTADOS_JUEGO.PAUSA;
    const pauseMenu = document.getElementById('pause-menu');
    if (pauseMenu) pauseMenu.style.display = 'flex';
    if (typeof mostrarMensajeEnPantalla === 'function') {
        mostrarMensajeEnPantalla("Juego en Pausa.");
    }
  },
  reanudarJuego: function () {
    estadoActual = ESTADOS_JUEGO.INTERACTIVO;
    const pauseMenu = document.getElementById('pause-menu');
    if (pauseMenu) pauseMenu.style.display = 'none';
    if (typeof mostrarMensajeEnPantalla === 'function') {
        mostrarMensajeEnPantalla("Juego reanudado.");
    }
  },
  cargarPartida: function () {
    if (typeof mostrarMensajeEnPantalla === 'function') {
        mostrarMensajeEnPantalla("Cargando partida... (Funcionalidad pendiente)");
    }
  },
  salirAlMenuPrincipal: function () {
    window.location.href = '../index.php';
  },
  cerrarMenuInventario: function () {
    estadoActual = ESTADOS_JUEGO.INTERACTIVO;
    if (typeof cerrarInventario === 'function') cerrarInventario();
  },
  disparar: function () {
    if (typeof mostrarMensajeEnPantalla === 'function') {
        mostrarMensajeEnPantalla(" Has disparado al enemigo.");
    }
  },
  huir: function () {
    estadoActual = ESTADOS_JUEGO.INTERACTIVO;
    if (typeof mostrarMensajeEnPantalla === 'function') {
        mostrarMensajeEnPantalla(" Has huido del combate. Volviendo a la exploración.");
    }
  },
  entrarEnBatalla: function () {
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
  
  //Lista de IDs de elementos que bloquean el movimiento
  const modalesBloqueantes = [
    'inventory-screen', 'note-viewer', 'save-menu', 
    'medallones-puzzle', 'estatua-puzzle', 'portable-safe-puzzle', 'caja-fuerte-puzzle'
  ];
  
  const algunModalAbierto = modalesBloqueantes.some(id => {
    const el = document.getElementById(id);
    return el && el.style.display === 'flex';
  });

  const hayCombate = typeof tension !== 'undefined' && tension === 'alta';

  if (algunModalAbierto || hayCombate) {
    if (key === 'escape' && !hayCombate) return; //Dejar que otros scripts cierren modales
    if (["w", "a", "s", "d", "arrowup", "arrowdown", "arrowleft", "arrowright"].includes(key)) {
      return; //Bloquear movimiento
    }
  }

  //Si estamos en pausa, solo escuchamos Escape para reanudar
  if (estadoActual === ESTADOS_JUEGO.PAUSA) {
    if (key === "escape") {
      gameActions.reanudarJuego();
      e.preventDefault();
    }
    return;
  }

  switch (estadoActual) {
    case ESTADOS_JUEGO.INTERACTIVO:
      if (["w", "a", "s", "d", "arrowup", "arrowdown", "arrowleft", "arrowright"].includes(key)) {
        if (!teclasPresionadas[key]) {
          teclasPresionadas[key] = true;
          gameActions.elegirRuta(key);
        }
      }
      if (key === "escape") {
        gameActions.salirJuegoOPausa();
        e.preventDefault();
      }
      break;

    case ESTADOS_JUEGO.INVENTARIO:
      if (key === "escape") {
        gameActions.cerrarMenuInventario();
      }
      break;

    case ESTADOS_JUEGO.BATALLA:
      if (key === "g") gameActions.disparar();
      if (key === "h") gameActions.huir();
      break;
  }
});

window.addEventListener("keyup", (e) => {
  const key = e.key.toLowerCase();
  if (teclasPresionadas.hasOwnProperty(key)) {
    teclasPresionadas[key] = false;
  }
});

