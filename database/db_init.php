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
";

$db->exec($esquema);

// INSERTS
$inserts = "
-- Insertar Armas
INSERT OR IGNORE INTO catalogo_armas (nombre, dano_porcentaje, ruta_exclusiva, descripcion, imagen_url) VALUES
('Pistola M19', 25, 'ambos', 'Arma reglamentaria. Daño bajo. La munición se crea combinando 2 pólvoras grises.', '../img/PistolaM19.png'),
('Escopeta W-870', 75, 'chico', 'Ideal para distancias cortas. Daño letal. La munición se crea con 1 pólvora amarilla y 1 gris.', '../img/EscopetaW-870.webp'),
('Fusil de Cerrojo', 75, 'chica', 'Alta precisión y gran poder de detención. La munición se crea con 1 pólvora roja y 1 gris.', '../img/FusildeCerrojo.webp'),
('Granada de Fragmentación', 100, 'ambos', 'Eliminación instantánea de enemigos comunes. Creada al combinar 2 pólvoras amarillas (chico) o 2 rojas (chica).', '../img/GranadaDeFragmentación.jpg');

-- Insertar Ítems
INSERT OR IGNORE INTO catalogo_items (nombre, tipo, ruta_exclusiva, descripcion, imagen_url) VALUES
('Hierba Verde', 'curacion', 'ambos', 'Planta medicinal local. Cura un 25% de salud. Se pueden combinar hasta 3 juntas (100% de salud).', '../img/Verde_hierva.webp'),
('Cuchillo Defensivo', 'defensa', 'ambos', 'Permite evitar un mordisco y escapar sin recibir daño. Se consume tras su uso.', '../img/Cuchillo_Defensivo.webp'),
('Pólvora Gris', 'fabricacion', 'ambos', 'Pólvora común. Requiere combinarse con otras pólvoras para ser útil.', '../img/polvora_gris.png'),
('Pólvora Amarilla', 'fabricacion', 'chico', 'Combínala con gris para cartuchos de escopeta, o con otra amarilla para una granada.', '../img/polvora_amarilla.png'),
('Pólvora Roja', 'fabricacion', 'chica', 'Combínala con gris para munición de fusil, o con otra roja para una granada.', '../img/Pólvora_roja.png'),
('Cinta de Guardado', 'clave', 'ambos', 'Una cinta magnética para máquina de escribir. Permite registrar tu progreso una sola vez. Úsala con sabiduría.', '../img/cinta_de_tinta.webp'),
('Medallon de León', 'clave', 'ambos', 'Un pesado medallon de plata con el emblema de un león', '../img/medallon_de_leon.png'),
('Medallon de Unicornio', 'clave', 'ambos', 'Un pesado medallon de bronce con el emblema de un unicornio', '../img/medallon_de_unicornio.png'),
('Medallon de Doncella', 'clave', 'ambos', 'Un pesado medallon de oro con el emblema de una doncella', '../img/medallon_de_doncella.png'),
('Caja Fuerte Portatil', 'clave', 'ambos', 'Una pequeña caja fuerte con combinación, si la consigues abrir puede traer buenas recompensas', '../img/Caja_Fuerte_Portatil.png'),
('Llave de Diamante', 'clave', 'ambos', 'Una llave que tiene la forma del diamate', '../img/llave_de_diamante.png'),
('Llave de Pica', 'clave', 'ambos', 'Una llave que tiene la forma de la pica', '../img/llave_de_pica.png'),
('Cortacadenas', 'clave', 'ambos', 'Herramienta útil para cortar cadenas que impidan el paso', '../img/corta_cadenas.png');

-- Insertar Enemigos
INSERT OR IGNORE INTO catalogo_enemigos (nombre, tipo, vida_maxima, dano_base, esquive_base, precision_cabeza, precision_torso, precision_piernas, prob_aturdir_piernas, 
multiplicador_cabeza, imagen_url) VALUES
-- Zombie Común: Equilibrado.
('Zombie Hombre', 'comun', 50, 25, 35, 25, 75, 55, 50, 2.0, '../img/zombie_civil_hombre.png'),
('Zombie Mujer', 'comun', 50, 25, 35, 25, 75, 55, 50, 2.0, '../img/zombie_civil_mujer.png'),
('Zombie Recluso', 'comun', 50, 25, 35, 25, 75, 55, 50, 2.0, '../img/zombie_recluso.png'),
('Zombie Uniforme', 'comun', 50, 25, 35, 25, 75, 55, 50, 2.0, '../img/zombie_uniforme.png'),
-- Licker: Muy difícil de dar en la cabeza por su postura, pero vulnerable al torso.
('Licker', 'mutante', 75, 50, 25, 15, 60, 45, 30, 2.5, '../img/licker.png'),
-- Lastre: Muy fácil de acertar (grande y lento), pero difícil de aturdir por su masa.
('Lastre', 'zombie_pesado', 120, 15, 60, 20, 85, 40, 20, 1.5, '../img/laste_enemigo.png'),
-- Espasmo: Muy difícil de apuntar (errático), pero si le das en las piernas se nota.
('Espasmo', 'zombie_agil', 40, 50, 15, 10, 50, 40, 60, 2.0, '../img/espasmo_enemigo.png'),
-- FASE 1: El Recopilador (Científico Translúcido)
('El Recopilador - Fase 1', 'boss', 300, 35, 10, 20, 70, 50, 40, 2.0, '../img/boss_fase1.png'),
-- FASE 2: El Recopilador (Mutación Blindada)
('El Recopilador - Fase 2', 'boss', 600, 55, 5, 15, 50, 40, 25, 3.0, '../img/boss_fase2.png'),
-- FASE 3: El Recopilador (Forma Final / Coloso de Memorias)
('El Recopilador - Fase 3', 'boss', 1000, 80, 5, 10, 60, 30, 10, 4.0, '../img/boss_fase3.png');

-- CATALOGO_SALAS COMPLETO 
INSERT OR IGNORE INTO catalogo_salas (id_sala, nombre_visual, descripcion, capitulo, norte, sur, este, oeste, imagen_url) VALUES
-- PLANTA BAJA
('banos_inicio', 'Baños (Inicio)', 'Un lugar lúgubre donde comenzó la pesadilla.', 1, 'lobby_principal', NULL, NULL, NULL, '../img/sala_baños.png'),
('lobby_principal', 'Lobby Principal', 'Hub central de la comisaría. Estatua de los medallones.', 1, 'oficina_este', 'banos_inicio', 'biblioteca', 'sala_espera', '../img/lobby_principal.png'),
('sala_espera', 'Sala de Espera', 'Sillas volcadas y rastros de evacuación.', 1, 'oficina_oeste', NULL, 'lobby_principal', NULL, '../img/sala_de_espera.png'),
('oficina_oeste', 'Oficina Oeste', 'Departamento de investigación. Huele a químicos.', 1, NULL, 'sala_espera', NULL, 'sala_archivos', '../img/oficina_oeste.png'),
('oficina_este', 'Oficina Este', 'Área administrativa. Hay una puerta con cadena.', 1, 'sala_descanso', 'lobby_principal', NULL, NULL, '../img/oficina_este.png'),
('sala_archivos', 'Sala de Archivos', 'Estanterías llenas de documentos y papel.', 1, NULL, NULL, 'oficina_oeste', NULL, '../img/sala_de_archivos.png'),
('sala_descanso', 'Sala de Descanso', 'Un pequeño refugio con literas y una cafetera.', 1, 'sala_interrogatorios', 'oficina_este', NULL, NULL, '../img/sala_de_descanso.png'),
('sala_interrogatorios', 'Sala de Interrogatorios', 'Fría y oscura. El espejo está agrietado.', 1, NULL, 'sala_descanso', NULL, NULL, '../img/sale_de_interrogatorio.png'),

-- SEGUNDA PLANTA
('biblioteca', 'Biblioteca (2F)', 'Suelos de madera que crujen y estanterías móviles.', 1, 'sala_arte', NULL, NULL, 'lobby_principal', NULL),
('sala_arte', 'Sala de Arte', 'Exposición de estatuas y cuadros clásicos.', 1, 'oficina_capitan', 'biblioteca', NULL, NULL, NULL),
('oficina_capitan', 'Oficina del Capitán', 'Lujosa pero desordenada. Caja fuerte grande.', 1, 'sala_electrica', 'sala_arte', NULL, NULL, NULL),
('sala_electrica', 'Sala Eléctrica', 'Paneles de fusibles y zumbido constante.', 1, NULL, 'oficina_capitan', NULL, NULL, NULL);

-- NOTAS DE HISTORIA PRINCIPAL Y PISTAS 
INSERT OR IGNORE INTO catalogo_archivos (nombre, ruta_exclusiva, informacion, imagen_url) VALUES
('NOTA 1: Informe inicial', 'ambos', 'Fecha: 19 de septiembre. Hemos recibido múltiples llamadas sobre disturbios en la zona este. Al principio parecían ataques aislados, pero todos los testigos describen lo mismo: gente extremadamente agresiva… que no responde al dolor. El capitán ha ordenado aumentar la vigilancia. Personalmente, creo que esto va a peor.', NULL),
('NOTA 2: Registro médico improvisado', 'ambos', 'Paciente: Civil masculino (sin identificar). Mordedura profunda en el brazo. Fiebre alta (40°C). Comportamiento errático. El paciente murió a las 03:12… y volvió a moverse a las 03:27. NO es una broma.', NULL),
('NOTA 3: Orden interna', 'ambos', 'A TODO EL PERSONAL: Queda prohibido permitir la entrada a civiles con heridas abiertas o mordeduras. Cualquier individuo que muestre signos de agresividad extrema deberá ser neutralizado. Disparen a la cabeza. — Capitanía', NULL),
('NOTA 4: Mensaje personal', 'ambos', 'Sarah, si estás leyendo esto, lo siento. Dejé pasar a gente herida… niños, incluso. Pensé que estaba haciendo lo correcto. Ahora están dentro. Si no salgo de aquí, no dejes que nadie se acerque a ti si está herido. — Mike', NULL),
('NOTA 5: Informe clasificado', 'ambos', '[DOCUMENTO DAÑADO] …el brote no es natural. Se sospecha de una filtración en los laboratorios subterráneos de la ciudad. Nombre en clave: \"T-Virus\". Efectos: Reanimación post-mortem, pérdida de funciones cognitivas, agresión extrema. [FIN DEL DOCUMENTO]', NULL),
('NOTA 6: Grabación transcrita', 'ambos', '[Inicio de grabación] No queda nadie… Intentamos resistir en el vestíbulo, pero… no paran. Las puertas no aguantaron. Si alguien encuentra esto: no vengas aquí. [Golpes, gritos] Dios… están entrando— [Fin de grabación]', NULL),
('NOTA 7: Último informe del capitán', 'ambos', 'Hemos perdido la comisaría. Los supervivientes que quedan están dispersos o muertos. Esto no fue un fallo… fue una condena desde el principio. Si alguien logra escapar: La ciudad está perdida. No busques ayuda aquí. — Capitán de policía', NULL),
('Cuaderno de Leon (Pista)', 'ambos', 'Día 1: Las puertas del lobby están selladas. Parece que el código de la salida está relacionado con tres estatuas... Lobo, Águila y Serpiente.', NULL),
('Protocolo de Cierre (Pista)', 'ambos', 'Nivel de amenaza crítico. Salida del garaje bloqueada. Requiere inserción de los tres medallones tácticos en el panel inferior.', NULL),

-- 2. NOTAS OCULTAS - RUTA CHICA 
('NOTA OCULTA 1: Carta sellada', 'chica', 'No deberíamos haber aceptado ese “apoyo corporativo”. Dijeron que era para mejorar la seguridad… pero lo único que han traído son cajas selladas y órdenes que no podemos cuestionar. Nadie sabe qué hay realmente en el subsuelo de la ciudad. Y sinceramente… no quiero saberlo.', NULL),
('NOTA OCULTA 2: Informe técnico escondido', 'chica', 'Proyecto: T-Virus (fase de pruebas urbanas). Objetivo: evaluar comportamiento en entorno real. Resultados iniciales: Alta tasa de infección, propagación incontrolable. Recomendación: aislamiento total de la zona afectada. Nota: Las fuerzas locales no deben conocer el alcance real del experimento.', NULL),
('NOTA OCULTA 3: Grabación privada', 'chica', '[Voz distorsionada] La comisaría funcionará como punto de contención. Si el virus se extiende, los supervivientes acudirán allí por instinto. Es el lugar perfecto para observar la evolución del sujeto infectado… en masa. [Fin]', NULL),
('NOTA OCULTA 4: Diario científico fragmentado', 'chica', 'Día 3: El sujeto mantiene movilidad tras la muerte cerebral. Día 5: Responde a estímulos sonoros. Día 7: Hambre constante. Día 8: He dejado de verlo como una persona. Día 9: Creo que yo soy el siguiente.', NULL),
('NOTA OCULTA 5: Plano oculto de la comisaría', 'chica', 'El edificio no es lo que parece. Existen accesos sellados que conectan con instalaciones subterráneas. Solo personal autorizado puede acceder. Código de acceso: [BORRADO]', NULL),
('NOTA OCULTA 6: Mensaje cifrado', 'chica', 'Si estás leyendo esto, ya sabes la verdad. No intentes escapar por la ciudad. Van a borrar todo. Incluyéndonos.', NULL),
('NOTA OCULTA 7: Última confesión', 'chica', 'Yo autoricé la entrada de los contenedores. Firmé los permisos. Ignoré las advertencias. Pensé que era solo otro contrato… otra donación… Si alguien encuentra esto: esto es culpa nuestra.', NULL),

-- 3. NOTAS OCULTAS - RUTA CHICO 
('NOTA OCULTA 1: Registro de guardia', 'chico', 'Ayer trajeron a un tipo que mordió a dos oficiales en la calle. Lo metimos en la celda 3. No reacciona al dolor, no parpadea. Le dimos con las porras y ni siquiera se quejó. El Capitán dice que es solo pánico inducido por drogas, pero los que fueron mordidos hoy tienen una fiebre altísima y los ojos inyectados en sangre.', NULL),
('NOTA OCULTA 2: Memorándum denegado', 'chico', 'SOLICITUD DENEGADA. Bajo ninguna circunstancia se debe distribuir munición pesada o armamento antidisturbios a los oficiales de patrulla. Órdenes estrictas de los benefactores corporativos: dejen que la situación \"se desarrolle\" con el equipo estándar.', NULL),
('NOTA OCULTA 3: Transcripción de radio interceptada', 'chico', '[Canal Táctico Cifrado] Equipo Echo a Base. La contención del recinto ha fallado. Repito, contención fallida. Base: Entendido, Echo. Los \"Limpiadores\" están en camino. Abandone a las fuerzas locales. Aseguren la muestra y eliminen cualquier cabo suelto.', NULL),
('NOTA OCULTA 4: Bitácora forense', 'chico', 'Las heridas se vuelven necróticas en cuestión de minutos. La coagulación se detiene por completo. Traté de amputarle el brazo al oficial Gómez para detener la infección, pero el miembro amputado… seguía teniendo espasmos. Ya no es humano. El cerebro está muerto, pero el cuerpo sigue buscando alimento.', NULL),
('NOTA OCULTA 5: Mensaje en la pared', 'chico', 'NO VAYAN A LAS ALCANTARILLAS. Están subiendo por los túneles. No son los muertos de la calle. Son otra cosa. Tienen garras. No tienen piel. Cazan por el sonido.', NULL),
('NOTA OCULTA 6: Orden de evacuación VIP', 'chico', 'Lista de Extracción Prioritaria. Solo personal de nivel 4 o superior. El perímetro de la ciudad se cerrará permanentemente a las 00:00 horas. Directiva: Ningún civil, policía o personal médico está autorizado a salir. Disparen a matar a cualquiera que se acerque a los muros.', NULL),
('NOTA OCULTA 7: Nota ensangrentada de un mercenario', 'chico', 'Aseguramos la muestra del virus, pero esa maldita aberración gigante mató a Jenkins. Escondí el maletín en el cuarto del generador principal. Si algún desgraciado sigue vivo y lee esto… no confíes en el helicóptero de extracción. Nos traicionaron a todos.', NULL);

-- LOGROS GLOBALES
INSERT OR IGNORE INTO catalogo_logros (nombre, descripcion) VALUES 
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