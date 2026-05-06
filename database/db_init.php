<?php
$db = new SQLite3(__DIR__ . '/resident_evil.sqlite3');

$db->exec('PRAGMA foreign_keys = ON;');

$esquema = "
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS catalogo_logros (
    id_logro INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT UNIQUE NOT NULL,
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

-- BLOQUE 2: CATÁLOGOS (Datos fijos que lee el JavaScript)

CREATE TABLE IF NOT EXISTS catalogo_armas (
    id_arma INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT UNIQUE NOT NULL,
    dano_porcentaje INTEGER NOT NULL,
    ruta_exclusiva TEXT NOT NULL, 
    descripcion TEXT NOT NULL,
    imagen_url TEXT DEFAULT NULL 
);

CREATE TABLE IF NOT EXISTS catalogo_items (
    id_item INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT UNIQUE NOT NULL,
    tipo TEXT NOT NULL, 
    ruta_exclusiva TEXT NOT NULL, 
    descripcion TEXT NOT NULL,
    imagen_url TEXT DEFAULT NULL 
);

CREATE TABLE IF NOT EXISTS catalogo_archivos (
    id_archivo INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT UNIQUE NOT NULL,
    ruta_exclusiva TEXT NOT NULL,
    informacion TEXT NOT NULL,
    imagen_url TEXT DEFAULT NULL 
);

CREATE TABLE IF NOT EXISTS catalogo_enemigos (
    id_enemigo INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT UNIQUE NOT NULL,
    tipo TEXT NOT NULL,
    vida_maxima INTEGER NOT NULL,
    dano_base INTEGER NOT NULL,
    esquive_base INTEGER NOT NULL, -- Probabilidad del jugador de huir
    -- Probabilidades de acierto del jugador (0-100)
    precision_cabeza INTEGER DEFAULT 25,
    precision_torso INTEGER DEFAULT 75,
    precision_piernas INTEGER DEFAULT 55,
    -- Multiplicadores y efectos
    multiplicador_cabeza FLOAT DEFAULT 2.0,
    prob_aturdir_piernas INTEGER DEFAULT 50,
    imagen_url TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS catalogo_salas (
    id_sala TEXT PRIMARY KEY, 
    nombre_visual TEXT NOT NULL, 
    descripcion TEXT, 
    capitulo INTEGER DEFAULT 1,
    -- Conexiones (esto se puede manejar aquí o en una tabla aparte)
    norte TEXT, 
    sur TEXT,
    este TEXT,
    oeste TEXT,
    imagen_url TEXT DEFAULT NULL 
);

-- BLOQUE 3: DATOS DE PARTIDA (Lo que se guarda en la máquina de escribir)

CREATE TABLE IF NOT EXISTS partida (
    id_partida INTEGER PRIMARY KEY AUTOINCREMENT,
    id_usuario INTEGER NOT NULL,
    ruta TEXT NOT NULL, -- 'chico' o 'chica'
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
    tipo_objeto TEXT NOT NULL, -- 'arma', 'item' o 'archivo'
    id_objeto INTEGER NOT NULL, -- El ID que corresponde a su catálogo respectivo
    cantidad INTEGER DEFAULT 1,
    posicion_slot INTEGER DEFAULT 0, -- Posición en la cuadrícula del inventario (0-7)
    FOREIGN KEY(id_partida) REFERENCES partida(id_partida)
);

CREATE TABLE IF NOT EXISTS estado_enemigos (
    id_registro INTEGER PRIMARY KEY AUTOINCREMENT,
    id_partida INTEGER NOT NULL,
    id_enemigo INTEGER NOT NULL,
    sala_ubicacion TEXT NOT NULL,
    vida_restante INTEGER NOT NULL,
    estado TEXT DEFAULT 'vivo', -- 'vivo', 'muerto', 'aturdido'
    FOREIGN KEY(id_partida) REFERENCES partida(id_partida),
    FOREIGN KEY(id_enemigo) REFERENCES catalogo_enemigos(id_enemigo)
);

CREATE TABLE IF NOT EXISTS eventos_interactivos(
    id_evento INTEGER PRIMARY KEY AUTOINCREMENT,
    id_sala TEXT NOT NULL,
    nombre_objeto TEXT NOT NULL,
    xmin FLOAT NOT NULL,
    xmax FLOAT NOT NULL,
    ymin FLOAT NOT NULL,
    ymax FLOAT NOT NULL,
    tipo_accion TEXT NOT NULL,
    contenido_accion TEXT NOT NULL,
    requiere_item TEXT NOT NULL,
    script TEXT NOT NULL,
    FOREIGN KEY(id_sala) REFERENCES catalogo_salas(id_sala) on delete cascade
);

CREATE TABLE IF NOT EXISTS eventos_completados (
    id_registro INTEGER PRIMARY KEY AUTOINCREMENT,
    id_partida INTEGER NOT NULL,
    id_evento INTEGER NOT NULL,
    fecha_recogida DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(id_partida) REFERENCES partida(id_partida),
    FOREIGN KEY(id_evento) REFERENCES eventos_interactivos(id_evento),
    UNIQUE(id_partida, id_evento)
);
";

$db->exec($esquema);

// INSERTS
$inserts = "
-- Insertar Armas
INSERT OR REPLACE INTO catalogo_armas (nombre, dano_porcentaje, ruta_exclusiva, descripcion, imagen_url) VALUES
('Pistola M19', 25, 'ambos', 'Arma reglamentaria. Daño bajo. Utiliza munición de pistola.', '../img/PistolaM19.png'),
('Escopeta W-870', 75, 'chico', 'Ideal para distancias cortas. Daño letal. Utiliza cartuchos de escopeta.', '../img/EscopetaW-870.png'),
('Fusil de Cerrojo', 75, 'chica', 'Alta precisión y gran poder de detención. Utiliza munición de fusil.',  '../img/FusildeCerrojo.png'),
('Granada de Fragmentación', 100, 'ambos', 'Eliminación instantánea de enemigos comunes.', '../img/GranadaDeFragmentación.png');

-- Insertar Ítems
INSERT OR REPLACE INTO catalogo_items (nombre, tipo, ruta_exclusiva, descripcion, imagen_url) VALUES
('Hierba Verde', 'curacion', 'ambos', 'Planta medicinal local. Cura un 25% de salud. Se pueden combinar hasta 3 juntas (100% de salud).', '../img/Verde_hierva.png'),
('Cuchillo Defensivo', 'defensa', 'ambos', 'Permite evitar un mordisco y escapar sin recibir daño. Se consume tras su uso.', '../img/Cuchillo_Defensivo.png'),
('Munición de Pistola', 'municion', 'ambos', 'Balas de 9mm para armas de mano.', '../img/municion_pistola.png'),
('Munición de Escopeta', 'municion', 'ambos', 'Cartuchos potentes para combate cercano.', '../img/municion_escopeta.png'),
('Munición de Fusil', 'municion', 'ambos', 'Balas de alto calibre para fusil.', '../img/municion_fusil.png'),
('Cinta de Guardado', 'clave', 'ambos', 'Una cinta magnética para máquina de escribir. Permite registrar tu progreso una sola vez. Úsala con sabiduría.', '../img/cinta_de_tinta.webp'),
('Medallon de León', 'clave', 'ambos', 'Un pesado medallon de plata con el emblema de un león', '../img/medallon_de_leon.png'),
('Medallon de Unicornio', 'clave', 'ambos', 'Un pesado medallon de bronce con el emblema de un unicornio', '../img/medallon_de_unicornio.png'),
('Medallon de Doncella', 'clave', 'ambos', 'Un pesado medallon de oro con el emblema de una doncella', '../img/medallon_de_doncella.png'),
('Caja Fuerte Portatil', 'clave', 'ambos', 'Una pequeña caja fuerte con combinación, si la consigues abrir puede traer buenas recompensas', '../img/Caja_Fuerte_Portatil.png'),
('Llave de Diamante', 'clave', 'ambos', 'Una llave que tiene la forma del diamate', '../img/llave_de_diamante.png'),
('Llave de Pica', 'clave', 'ambos', 'Una llave que tiene la forma de la pica', '../img/llave_de_pica.png'),
('Cortacadenas', 'clave', 'ambos', 'Herramienta útil para cortar cadenas que impidan el paso', '../img/corta_cadenas.png');

-- Insertar Enemigos
INSERT OR REPLACE INTO catalogo_enemigos (nombre, tipo, vida_maxima, dano_base, esquive_base, precision_cabeza, precision_torso, precision_piernas, prob_aturdir_piernas, 
multiplicador_cabeza, imagen_url) VALUES
('Zombie Hombre', 'comun', 50, 25, 35, 25, 75, 55, 50, 2.0, '../img/zombie_civil_hombre.png'),
('Zombie Mujer', 'comun', 50, 25, 35, 25, 75, 55, 50, 2.0, '../img/zombie_civil_mujer.png'),
('Zombie Recluso', 'comun', 50, 25, 35, 25, 75, 55, 50, 2.0, '../img/zombie_recluso.png'),
('Zombie Uniforme', 'comun', 50, 25, 35, 25, 75, 55, 50, 2.0, '../img/zombie_uniforme.png'),
('Licker', 'mutante', 75, 50, 25, 15, 60, 45, 30, 2.5, '../img/licker.png'),
('Lastre', 'zombie_pesado', 120, 15, 60, 20, 85, 40, 20, 1.5, '../img/laste_enemigo.png'),
('Espasmo', 'zombie_agil', 40, 50, 15, 10, 50, 40, 60, 2.0, '../img/espasmo_enemigo.png'),
('El Recopilador - Fase 1', 'boss', 300, 35, 10, 20, 70, 50, 40, 2.0, '../img/boss_fase1.png'),
('El Recopilador - Fase 2', 'boss', 600, 55, 5, 15, 50, 40, 25, 3.0, '../img/boss_fase2.png'),
('El Recopilador - Fase 3', 'boss', 1000, 80, 5, 10, 60, 30, 10, 4.0, '../img/boss_fase3.png');

-- CATALOGO_SALAS COMPLETO 
INSERT OR REPLACE INTO catalogo_salas (id_sala, nombre_visual, descripcion, capitulo, norte, sur, este, oeste, imagen_url) VALUES
('banos_inicio', 'Baños (Inicio)', 'Un lugar lúgubre donde comenzó la pesadilla.', 1, NULL, NULL, 'lobby_principal', NULL, '../img/sala_baños.png'),
('lobby_principal', 'Lobby Principal', 'Hub central de la comisaría. Estatua de los medallones.', 1, 'pasillo', 'banos_inicio', 'oficina_este', 'sala_espera', '../img/lobby_principal.png'),
('sala_espera', 'Sala de Espera', 'Sillas volcadas y rastros de evacuación.', 1, 'oficina_oeste', NULL, 'lobby_principal', NULL, '../img/sala_de_espera.png'),
('oficina_oeste', 'Oficina Oeste', 'Departamento de investigación. Huele a químicos.', 1, NULL, 'sala_espera', 'sala_archivos', NULL, '../img/oficina-oeste.png'),
('oficina_este', 'Oficina Este', 'Área administrativa. Hay una puerta con cadena.', 1, 'sala_descanso', NULL, NULL, 'lobby_principal', '../img/oficina_este.png'),
('sala_archivos', 'Sala de Archivos', 'Estanterías llenas de documentos y papel.', 1, NULL, NULL, NULL, 'oficina_oeste', '../img/sala_de_archivos.png'),
('sala_descanso', 'Sala de Descanso', 'Un pequeño refugio con literas y una cafetera.', 1, 'sala_interrogatorios', 'oficina_este', NULL, NULL, '../img/sala_de_descanso.png'),
('sala_interrogatorios', 'Sala de Interrogatorios', 'Fría y oscura. El espejo está agrietado.', 1, NULL, 'sala_descanso', NULL, NULL, '../img/sale_de_interrogatorio.png'),
('pasillo', 'Pasillo', 'Pasillo que conecta la 1r y 2nd planta', 1, NULL, 'lobby_principal', 'sala_arte', 'oficina_capitan', '../img/pasillo.png'),
('biblioteca', 'Biblioteca (2F)', 'Suelos de madera que crujen y estanterías móviles.', 1, 'sala_arte', NULL, NULL, NULL, '../img/biblioteca.png'),
('sala_arte', 'Sala de Arte', 'Exposición de estatuas y cuadros clásicos.', 1, NULL, 'biblioteca', NULL, 'pasillo', '../img/sala_arte.png'), 
('oficina_capitan', 'Oficina del Capitán', 'Lujosa pero desordenada. Caja fuerte grande.', 1, NULL, 'sala_electrica', 'pasillo', NULL, '../img/oficina_capitan.png'), 
('sala_electrica', 'Sala Eléctrica', 'Paneles de fusibles y zumbido constante.', 1, 'oficina_capitan', NULL, NULL, NULL, '../img/sala_electrica.png');

-- INSERTAR EVENTOS
INSERT OR IGNORE INTO eventos_interactivos 
(id_sala, nombre_objeto, xmin, xmax, ymin, ymax, tipo_accion, contenido_accion, requiere_item, script) VALUES 
('banos_inicio', 'NOTA 1 INICIO DEL BROTE', 17.0, 29.0, 65.0, 85.0, 'leer_archivo', '1', '', 'abrirMenuArchivo'),
('banos_inicio', 'NOTA OCULTA 6', 32.0, 37.0, 66.0, 69.0, 'leer_archivo', '15', '', 'abrirMenuArchivo'),
('lobby_principal', 'ESTATUA', 39.0, 62.0, 26.0, 74.0, 'puzzle', 'medallones', '', 'abrirMenuPuzzle'),
('lobby_principal', 'PISTOLA', 90.0, 98.0, 54.0, 60.0, 'recoger_arma', '1', '', 'añadirInventario'),
('lobby_principal', 'ITEM RANDOM', 14.0, 16.0, 62.0, 63.0, 'recoger_item', 'random', '', 'añadirInventario'),
('sala_espera', 'NOTA 3', 85.0, 94.0, 72.0, 77.0, 'leer_archivo', '3', '', 'abrirMenuArchivo'),
('sala_espera', 'PISTA CUADERNO LEON', 11.0, 20.0, 62.0, 68.0, 'leer_archivo', '8', '', 'abrirMenuArchivo'),
('sala_espera', 'ITEM RANDOM', 55.0, 59.0, 61.0, 62.0, 'recoger_item', 'random', '', 'añadirInventario'),
('oficina_oeste', 'NOTA 2', 37.0, 40.0, 35.0, 42.0, 'leer_archivo', '2', '', 'abrirMenuArchivo'),
('oficina_oeste', 'NOTA OCULTA 1', 96.0, 99.0, 71.0, 72.0, 'leer_archivo', '10', '', 'abrirMenuArchivo'),
('oficina_oeste', 'CAJA FUERTE PORTATIL', 53.0, 59.0, 82.0, 86.0, 'recoger_item', '10', '', 'añadirInventario'),
('oficina_oeste', 'CAJÓN CON LLAVE', 12.0, 16.0, 77.0, 81.0, 'abrir_contenedor', 'item', 'Llave de Pica', 'intentarAbrir'),
('oficina_oeste', 'PUNTO DE GUARDADO', 13.0, 21.0, 67.0, 72.0, 'guardar', 'maquina_escribir', 'Cinta de Guardado', 'abrirMenuGuardado'),
('sala_archivos', 'NOTA 6', 56.0, 60.0, 68.0, 71.0, 'leer_archivo', '6', '', 'abrirMenuArchivo'),
('sala_archivos', 'MEDALLON UNICORNIO', 71.0, 79.0, 73.0, 82.0, 'recoger_item', '8', '', 'añadirInventario'),
('sala_archivos', 'ITEM RANDOM', 68.0, 72.0, 38.0, 42.0, 'recoger_item', 'random', '', 'añadirInventario'),
('sala_archivos', 'ITEM RANDOM', 12.0, 16.0, 47.0, 52.0, 'recoger_item', 'random', '', 'añadirInventario'),
('oficina_este', 'NOTA 4', 21.0, 25.0, 64.0, 66.0, 'leer_archivo', '4', '', 'abrirMenuArchivo'),
('oficina_este', 'LLAVE PICA', 86.0, 91.0, 79.0, 83.0, 'recoger_item', '12', '', 'añadirInventario'),
('oficina_este', 'PUERTA PARA CORTAR', 59.0, 63.0, 44.0, 50.0, 'desbloquear', 'puerta', 'Cortacadenas', 'usarHerramienta'),
('oficina_este', 'ITEM RANDOM', 74.0, 78.0, 52.0, 57.0, 'recoger_item', 'random', '', 'añadirInventario'),
('oficina_capitan', 'NOTA 7', 57.0, 59.0, 80.0, 81.0, 'leer_archivo', '7', '', 'abrirMenuArchivo'),
('oficina_capitan', 'CAJA FUERTE CORTACADENAS', 48.0, 54.0, 56.0, 70.0, 'puzzle', 'caja_fuerte', '', 'abrirMenuPuzzle'),
('oficina_capitan', 'BOTELLA RON', 26.0, 30.0, 84.0, 94.0, 'recoger_item', 'consumible', '', 'añadirInventario'),
('oficina_capitan', 'NOTA OCULTA 4', 85.0, 92.0, 87.0, 98.0, 'leer_archivo', '13', '', 'abrirMenuArchivo'),
('oficina_capitan', 'PUERTA IZQ', 4.0, 12.0, 36.0, 94.0, 'transicion', 'sala_electrica', '', 'cambiarSala'),
('oficina_capitan', 'ITEM RANDOM', 61.0, 64.0, 59.0, 63.0, 'recoger_item', 'random', '', 'añadirInventario'),
('sala_electrica', 'PUZZLE FUSIBLES', 16.0, 22.0, 24.0, 42.0, 'puzzle', 'electricidad', '', 'abrirMenuPuzzle'),
('sala_electrica', 'NOTA OCULTA 2', 86.0, 94.0, 64.0, 74.0, 'leer_archivo', '11', '', 'abrirMenuArchivo'),
('sala_electrica', 'ITEM RANDOM', 77.0, 80.0, 19.0, 25.0, 'recoger_item', 'random', '', 'añadirInventario'),
('pasillo', 'ESTATUA LEON', 32.0, 50.0, 25.0, 74.0, 'puzzle', 'puzzle_leon', '', 'abrirMenuPuzzle'),
('pasillo', 'ITEM RANDOM', 11.0, 15.0, 25.0, 30.0, 'recoger_item', 'random', '', 'añadirInventario'),
('pasillo', 'ITEM RANDOM', 75.0, 78.0, 47.0, 51.0, 'recoger_item', 'random', '', 'añadirInventario'),
('sala_arte', 'ESTATUA DONCELLA', 44.0, 55.0, 26.0, 80.0, 'puzzle', 'puzzle_doncella', '', 'abrirMenuPuzzle'),
('sala_arte', 'NOTA OCULTA 5', 64.0, 67.0, 76.0, 79.0, 'leer_archivo', '14', '', 'abrirMenuArchivo'),
('sala_arte', 'ITEM RANDOM', 30.0, 33.0, 67.0, 70.0, 'recoger_item', 'random', '', 'añadirInventario'),
('biblioteca', 'NOTA 5', 68.0, 71.0, 62.0, 62.0, 'leer_archivo', '5', '', 'abrirMenuArchivo'),
('biblioteca', 'NOTA OCULTA 3', 42.0, 45.0, 81.0, 83.0, 'leer_archivo', '12', '', 'abrirMenuArchivo'),
('biblioteca', 'ITEM RANDOM', 8.0, 11.0, 63.0, 66.0, 'recoger_item', 'random', '', 'añadirInventario'),
('sala_descanso', 'ESCOPETA ROTA', 58.0, 60.0, 49.0, 76.0, 'recoger_item', 'arma_rota', '', 'añadirInventario'),
('sala_descanso', 'CAJA FUERTE PORTATIL', 2.0, 7.0, 61.0, 65.0, 'recoger_item', '10', '', 'añadirInventario'),
('sala_descanso', 'GUARDADO', 17.0, 33.0, 63.0, 82.0, 'guardar', 'maquina_escribir', 'Cinta de Guardado', 'abrirMenuGuardado'),
('sala_descanso', 'ITEM RANDOM', 84.0, 87.0, 22.0, 28.0, 'recoger_item', 'random', '', 'añadirInventario'),
('sala_interrogatorios', 'PROTOCOLO DE CIERRE', 37.0, 45.0, 69.0, 77.0, 'leer_archivo', '9', '', 'abrirMenuArchivo'),
('sala_interrogatorios', 'NOTA OCULTA 7', 27.0, 31.0, 47.0, 54.0, 'leer_archivo', '16', '', 'abrirMenuArchivo');

-- NOTAS DE HISTORIA PRINCIPAL Y NOTAS OCULTAS
INSERT OR REPLACE INTO catalogo_archivos (nombre, ruta_exclusiva, informacion, imagen_url) VALUES
('NOTA 1: Informe inicial', 'ambos', 'Fecha: 19 de septiembre. Hemos recibido múltiples llamadas sobre disturbios en la zona este. Al principio parecían ataques aislados, pero todos los testigos describen lo mismo: gente extremadamente agresiva… que no responde al dolor. El capitán ha ordenado aumentar la vigilancia. Personalmente, creo que esto va a peor.', NULL),
('NOTA 2: Registro médico improvisado', 'ambos', 'Paciente: Civil masculino (sin identificar). Mordedura profunda en el brazo. Fiebre alta (40°C). Comportamiento errático. El paciente murió a las 03:12… y volvió a moverse a las 03:27. NO es una broma.', NULL),
('NOTA 3: Orden interna', 'ambos', 'A TODO EL PERSONAL: Queda prohibido permitir la entrada a civiles con heridas abiertas o mordeduras. Cualquier individuo que muestre signos de agresividad extrema deberá ser neutralizado. Disparen a la cabeza. — Capitanía', NULL),
('NOTA 4: Mensaje personal', 'ambos', 'Sarah, si estás leyendo esto, lo siento. Dejé pasar a gente herida… niños, incluso. Pensé que estaba haciendo lo correcto. Ahora están dentro. Si no salgo de aquí, no dejes que nadie se acerque a ti si está herido. — Mike', NULL),
('NOTA 5: Informe clasificado', 'ambos', '[DOCUMENTO DAÑADO] …el brote no es natural. Se sospecha de una filtración en los laboratorios subterráneos de la ciudad. Nombre en clave: \"T-Virus\". Efectos: Reanimación post-mortem, pérdida de funciones cognitivas, agresión extrema. [FIN DEL DOCUMENTO]', NULL),
('NOTA 6: Grabación transcrita', 'ambos', '[Inicio de grabación] No queda nadie… Intentamos resistir en el vestíbulo, pero… no paran. Las puertas no aguantaron. Si alguien encuentra esto: no vengas aquí. [Golpes, gritos] Dios… están entrando— [Fin de grabación]', NULL),
('NOTA 7: Último informe del capitán', 'ambos', 'Hemos perdido la comisaría. Los supervivientes que quedan están dispersos o muertos. Esto no fue un fallo… fue una condena desde el principio. Si alguien logra escapar: La ciudad está perdida. No busques ayuda aquí. — Capitán de policía', NULL),
('Cuaderno de Leon (Pista)', 'ambos', 'Día 1: Las puertas del lobby están selladas. Parece que el código de la salida está relacionado con tres estatuas... Lobo, Águila y Serpiente.', NULL),
('Protocolo de Cierre (Pista)', 'ambos', 'Nivel de amenaza crítico. Salida del garaje bloqueada. Requiere inserción de los tres medallones tácticos en el panel inferior.', NULL),

-- NOTAS OCULTAS
('NOTA OCULTA 1', 'ambos', 'Fecha: 19 de septiembre. Hemos recibido múltiples llamadas sobre disturbios en la zona este. Al principio parecían ataques aislados, pero todos los testigos describen lo mismo: gente extremadamente agresiva… que no responde al dolor. El capitán ha ordenado aumentar la vigilancia. Personalmente, creo que esto va a peor.', NULL),
('NOTA OCULTA 2: Correspondencia corporativa', 'ambos', 'No deberíamos haber aceptado ese “apoyo”. Dijeron que era para mejorar la seguridad, pero solo han traído cajas selladas y órdenes extrañas. El Capitán ha recibido pagos de una cuenta fantasma. Nadie sabe qué hay realmente en el subsuelo, y los que preguntan, simplemente desaparecen.', NULL), 
('NOTA OCULTA 3: Informe Técnico T-102', 'ambos', 'Proyecto: T-Virus. Objetivo: evaluar propagación en entorno real. Resultados: Alta tasa de infección. Recomendación: Aislamiento total. Las fuerzas locales funcionarán como punto de contención para observar la evolución de los sujetos infectados en masa. No deben conocer el alcance real del experimento.', NULL), 
('NOTA OCULTA 4: Bitácora forense', 'ambos', 'Las heridas se vuelven necróticas en minutos. Traté de amputarle el brazo al oficial Gómez para detener la infección, pero el miembro amputado… seguía teniendo espasmos. El cerebro está muerto, pero el cuerpo sigue buscando alimento. Ya no son humanos, son solo estómagos con piernas.', NULL), 
('NOTA OCULTA 5: Aviso de supervivencia', 'ambos', '¡NO VAYAN A LAS ALCANTARILLAS! Están subiendo por los túneles. No son los muertos de la calle, son otra cosa… no tienen piel, tienen garras y cazan por el sonido. Si escuchas un chasquido metálico o un roce en el techo, ya estás muerto.', NULL), 
('NOTA OCULTA 6: Orden de contención final', 'ambos', 'Directiva de Limpieza: El perímetro de la ciudad se cerrará permanentemente a las 00:00. Ningún civil, policía o personal médico está autorizado a salir. Disparen a matar a cualquiera que se acerque a los muros. No debe quedar ningún testigo que pueda hablar sobre el origen del brote.', NULL), 
('NOTA OCULTA 7: Última confesión', 'ambos', 'Yo firmé los permisos. Ignoré las advertencias por dinero. Escondí la muestra del virus en el cuarto del generador principal, pero esa aberración gigante mató a todo mi equipo antes de llegar. Si lees esto: no confíes en el helicóptero de extracción. Nos han abandonado para borrar el rastro.', NULL); 



-- LOGROS GLOBALES
INSERT OR REPLACE INTO catalogo_logros (nombre, descripcion) VALUES 
('Bienvenido al Infierno', 'Has logrado sobrevivir y completar el primer capítulo de la pesadilla.'),
('Control de Plagas', 'Elimina a 5 zombies. No dejes que se vuelvan a levantar.'),
('Intocable', 'Demuestra tus reflejos esquivando con éxito a 3 zombies.'),
('Toma un Respiro', 'Aturde a 3 enemigos atacando a sus puntos débiles (piernas).'),
('El Acertijo de la Estatua', 'Encuentra los medallones del León, el Unicornio y la Doncella.'),
('Científico Caído', 'Derrota la primera fase de \"El Recopilador\" en los laboratorios.'),
('Descenso a la Oscuridad', 'Has completado el segundo capítulo. La verdad está cada vez más cerca.'),
('Superviviente Definitivo', 'Has completado el tercer capítulo y superado los horrores de la ciudad.'),
('Fuerza Bruta', 'Consigue la Escopeta W-870 en la ruta de Chico.'),
('Muerte a Distancia', 'Consigue el Fusil de Cerrojo en la ruta de Chica.');
";

$db->exec($inserts);

$db->close();
?>