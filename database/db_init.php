<?php
//BLOQUE 1: USUARIOS Y LOGROS GLOBALES

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS catalogo_logros (
    id_logro INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    descripcion TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS logros_desbloqueados (
    id_usuario INTEGER,
    id_logro INTEGER,
    fecha_desbloqueo DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(id_usuario) REFERENCES usuarios(id_usuario),
    FOREIGN KEY(id_logro) REFERENCES catalogo_logros(id_logro),
    PRIMARY KEY(id_usuario, id_logro)
);

//BLOQUE 2: CATÁLOGOS (Datos fijos que lee el JavaScript)

CREATE TABLE IF NOT EXISTS catalogo_armas (
    id_arma INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    dano_porcentaje INTEGER NOT NULL,
    ruta_exclusiva TEXT NOT NULL, //'chico', 'chica', o 'ambos'
    descripcion TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS catalogo_items (
    id_item INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    tipo TEXT NOT NULL, //'curacion', 'defensa', 'fabricacion', 'clave'
    descripcion TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS catalogo_archivos (
    id_archivo INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    informacion TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS catalogo_enemigos (
    id_enemigo INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    tipo TEXT NOT NULL,
    vida_maxima INTEGER NOT NULL,
    dano_base INTEGER NOT NULL,
    esquive_base INTEGER NOT NULL
);

//BLOQUE 3: DATOS DE PARTIDA (Lo que se guarda en la máquina de escribir)

CREATE TABLE IF NOT EXISTS partida (
    id_partida INTEGER PRIMARY KEY AUTOINCREMENT,
    id_usuario INTEGER NOT NULL,
    ruta TEXT NOT NULL, //'chico' o 'chica'
    sala_actual TEXT NOT NULL,
    fecha_guardado DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(id_usuario) REFERENCES usuarios(id_usuario)
);

CREATE TABLE IF NOT EXISTS estado_personaje (
    id_partida INTEGER PRIMARY KEY,
    vida_actual INTEGER DEFAULT 100,
    cuchillos_defensivos INTEGER DEFAULT 0,
    FOREIGN KEY(id_partida) REFERENCES partida(id_partida)
);

CREATE TABLE IF NOT EXISTS inventario (
    id_registro INTEGER PRIMARY KEY AUTOINCREMENT,
    id_partida INTEGER NOT NULL,
    tipo_objeto TEXT NOT NULL, //'arma', 'item' o 'archivo'
    id_objeto INTEGER NOT NULL, //El ID que corresponde a su catálogo respectivo
    cantidad INTEGER DEFAULT 1,
    FOREIGN KEY(id_partida) REFERENCES partida(id_partida)
);

CREATE TABLE IF NOT EXISTS estado_enemigos (
    id_registro INTEGER PRIMARY KEY AUTOINCREMENT,
    id_partida INTEGER NOT NULL,
    id_enemigo INTEGER NOT NULL,
    sala_ubicacion TEXT NOT NULL,
    vida_restante INTEGER NOT NULL,
    estado TEXT DEFAULT 'vivo', //'vivo', 'muerto', 'aturdido'
    FOREIGN KEY(id_partida) REFERENCES partida(id_partida),
    FOREIGN KEY(id_enemigo) REFERENCES catalogo_enemigos(id_enemigo)
);

?>