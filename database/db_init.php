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
    ruta_exclusiva TEXT NOT NULL, 
    descripcion TEXT NOT NULL,
    imagen_url TEXT DEFAULT NULL 
);

CREATE TABLE IF NOT EXISTS catalogo_items (
    id_item INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    tipo TEXT NOT NULL, 
    ruta_exclusiva TEXT NOT NULL, 
    descripcion TEXT NOT NULL,
    imagen_url TEXT DEFAULT NULL 
);

CREATE TABLE IF NOT EXISTS catalogo_archivos (
    id_archivo INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    ruta_exclusiva TEXT NOT NULL,
    informacion TEXT NOT NULL,
    imagen_url TEXT DEFAULT NULL 
);

CREATE TABLE IF NOT EXISTS catalogo_enemigos (
    id_enemigo INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    tipo TEXT NOT NULL,
    vida_maxima INTEGER NOT NULL,
    dano_base INTEGER NOT NULL,
    esquive_base INTEGER NOT NULL, //Probabilidad del jugador de huir
    //Probabilidades de acierto del jugador (0-100)
    precision_cabeza INTEGER DEFAULT 25,
    precision_torso INTEGER DEFAULT 75,
    precision_piernas INTEGER DEFAULT 55,
    //Multiplicadores y efectos
    multiplicador_cabeza FLOAT DEFAULT 2.0,
    prob_aturdir_piernas INTEGER DEFAULT 50,
    imagen_url TEXT DEFAULT NULL
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


//INSERTS
//Insertar Armas
INSERT INTO catalogo_armas (nombre, dano_porcentaje, ruta_exclusiva, descripcion, imagen_url) VALUES
('Pistola M19', 25, 'ambos', 'Arma reglamentaria. Daño bajo. La munición se crea combinando 2 pólvoras grises.', NULL),
('Escopeta W-870', 75, 'chico', 'Ideal para distancias cortas. Daño letal. La munición se crea con 1 pólvora amarilla y 1 gris.', NULL),
('Fusil de Cerrojo', 75, 'chica', 'Alta precisión y gran poder de detención. La munición se crea con 1 pólvora roja y 1 gris.', NULL),
('Granada de Fragmentación', 100, 'ambos', 'Eliminación instantánea de enemigos comunes. Creada al combinar 2 pólvoras amarillas (chico) o 2 rojas (chica).', NULL);

//Insertar Ítems (AÚN QUEDA COMPLETARLO AÑADIENDO MÁS ITEMS !!!!!!!!!!!)
INSERT INTO catalogo_items (nombre, tipo, ruta_exclusiva, descripcion, imagen_url) VALUES
('Hierba Verde', 'curacion', 'ambos', 'Planta medicinal local. Cura un 25% de salud. Se pueden combinar hasta 3 juntas (100% de salud).', NULL),
('Cuchillo Defensivo', 'defensa', 'ambos', 'Permite evitar un mordisco y escapar sin recibir daño. Se consume tras su uso.', NULL),
('Pólvora Gris', 'fabricacion', 'ambos', 'Pólvora común. Requiere combinarse con otras pólvoras para ser útil.', NULL),
('Pólvora Amarilla', 'fabricacion', 'chico', 'Combínala con gris para cartuchos de escopeta, o con otra amarilla para una granada.', NULL),
('Pólvora Roja', 'fabricacion', 'chica', 'Combínala con gris para munición de fusil, o con otra roja para una granada.', NULL),
('Cinta de Guardado', 'clave', 'ambos', 'Una cinta magnética para máquina de escribir. Permite registrar tu progreso una sola vez. Úsala con sabiduría.', NULL),
('Medallon de León', 'clave', 'ambos', 'Un pesado medallon de plata con el emblema de un león', NULL),
('Medallon de Unicornio', 'clave', 'ambos', 'Un pesado medallon de bronce con el emblema de un unicornio', NULL),
('Medallon de Doncella', 'clave', 'ambos', 'Un pesado medallon de oro con el emblema de una doncella', NULL);

//Insertar Enemigos (AÚN QUEDA COMPLETARLO AÑADIENDO MÁS !!!!!!!!!!!)
INSERT INTO catalogo_enemigos (nombre, tipo, vida_maxima, dano_base, esquive_base, precision_cabeza, precision_torso, precision_piernas, prob_aturdir_piernas, 
multiplicador_cabeza, imagen_url) VALUES
//Zombie Común: Equilibrado.
('Zombie', 'comun', 50, 25, 35, 25, 75, 55, 50, 2.0, NULL),
//Licker: Muy difícil de dar en la cabeza por su postura, pero vulnerable al torso.
('Licker', 'mutante', 75, 50, 25, 15, 60, 45, 30, 2.5, NULL),
//Lastre: Muy fácil de acertar (grande y lento), pero difícil de aturdir por su masa.
('Lastre', 'zombie_pesado', 120, 15, 60, 20, 85, 40, 20, 1.5, NULL),
//Espasmo: Muy difícil de apuntar (errático), pero si le das en las piernas se nota.
('Espasmo', 'zombie_agil', 40, 50, 15, 10, 50, 40, 60, 2.0, NULL);

//NOTAS DE HISTORIA PRINCIPAL Y PISTAS (Comunes para ambos) SOLO TENEMOS EL 1R CAP
INSERT INTO catalogo_archivos (nombre, ruta_exclusiva, informacion, imagen_url) VALUES
('NOTA 1: Informe inicial', 'ambos', 'Fecha: 19 de septiembre. Hemos recibido múltiples llamadas sobre disturbios en la zona este. Al principio parecían ataques aislados, pero todos los testigos describen lo mismo: gente extremadamente agresiva… que no responde al dolor. El capitán ha ordenado aumentar la vigilancia. Personalmente, creo que esto va a peor.', NULL),
('NOTA 2: Registro médico improvisado', 'ambos', 'Paciente: Civil masculino (sin identificar). Mordedura profunda en el brazo. Fiebre alta (40°C). Comportamiento errático. El paciente murió a las 03:12… y volvió a moverse a las 03:27. NO es una broma.', NULL),
('NOTA 3: Orden interna', 'ambos', 'A TODO EL PERSONAL: Queda prohibido permitir la entrada a civiles con heridas abiertas o mordeduras. Cualquier individuo que muestre signos de agresividad extrema deberá ser neutralizado. Disparen a la cabeza. — Capitanía', NULL),
('NOTA 4: Mensaje personal', 'ambos', 'Sarah, si estás leyendo esto, lo siento. Dejé pasar a gente herida… niños, incluso. Pensé que estaba haciendo lo correcto. Ahora están dentro. Si no salgo de aquí, no dejes que nadie se acerque a ti si está herido. — Mike', NULL),
('NOTA 5: Informe clasificado', 'ambos', '[DOCUMENTO DAÑADO] …el brote no es natural. Se sospecha de una filtración en los laboratorios subterráneos de la ciudad. Nombre en clave: "T-Virus". Efectos: Reanimación post-mortem, pérdida de funciones cognitivas, agresión extrema. [FIN DEL DOCUMENTO]', NULL),
('NOTA 6: Grabación transcrita', 'ambos', '[Inicio de grabación] No queda nadie… Intentamos resistir en el vestíbulo, pero… no paran. Las puertas no aguantaron. Si alguien encuentra esto: no vengas aquí. [Golpes, gritos] Dios… están entrando— [Fin de grabación]', NULL),
('NOTA 7: Último informe del capitán', 'ambos', 'Hemos perdido la comisaría. Los supervivientes que quedan están dispersos o muertos. Esto no fue un fallo… fue una condena desde el principio. Si alguien logra escapar: La ciudad está perdida. No busques ayuda aquí. — Capitán de policía', NULL),
('Cuaderno de Leon (Pista)', 'ambos', 'Día 1: Las puertas del lobby están selladas. Parece que el código de la salida está relacionado con tres estatuas... Lobo, Águila y Serpiente.', NULL),
('Protocolo de Cierre (Pista)', 'ambos', 'Nivel de amenaza crítico. Salida del garaje bloqueada. Requiere inserción de los tres medallones tácticos en el panel inferior.', NULL);

//2. NOTAS OCULTAS - RUTA CHICA / REPARTIRLOS POR CAPS
INSERT INTO catalogo_archivos (nombre, ruta_exclusiva, informacion, imagen_url) VALUES
('NOTA OCULTA 1: Carta sellada', 'chica', 'No deberíamos haber aceptado ese “apoyo corporativo”. Dijeron que era para mejorar la seguridad… pero lo único que han traído son cajas selladas y órdenes que no podemos cuestionar. Nadie sabe qué hay realmente en el subsuelo de la ciudad. Y sinceramente… no quiero saberlo.', NULL),
('NOTA OCULTA 2: Informe técnico escondido', 'chica', 'Proyecto: T-Virus (fase de pruebas urbanas). Objetivo: evaluar comportamiento en entorno real. Resultados iniciales: Alta tasa de infección, propagación incontrolable. Recomendación: aislamiento total de la zona afectada. Nota: Las fuerzas locales no deben conocer el alcance real del experimento.', NULL),
('NOTA OCULTA 3: Grabación privada', 'chica', '[Voz distorsionada] La comisaría funcionará como punto de contención. Si el virus se extiende, los supervivientes acudirán allí por instinto. Es el lugar perfecto para observar la evolución del sujeto infectado… en masa. [Fin]', NULL),
('NOTA OCULTA 4: Diario científico fragmentado', 'chica', 'Día 3: El sujeto mantiene movilidad tras la muerte cerebral. Día 5: Responde a estímulos sonoros. Día 7: Hambre constante. Día 8: He dejado de verlo como una persona. Día 9: Creo que yo soy el siguiente.', NULL),
('NOTA OCULTA 5: Plano oculto de la comisaría', 'chica', 'El edificio no es lo que parece. Existen accesos sellados que conectan con instalaciones subterráneas. Solo personal autorizado puede acceder. Código de acceso: [BORRADO]', NULL),
('NOTA OCULTA 6: Mensaje cifrado', 'chica', 'Si estás leyendo esto, ya sabes la verdad. No intentes escapar por la ciudad. Van a borrar todo. Incluyéndonos.', NULL),
('NOTA OCULTA 7: Última confesión', 'chica', 'Yo autoricé la entrada de los contenedores. Firmé los permisos. Ignoré las advertencias. Pensé que era solo otro contrato… otra donación… Si alguien encuentra esto: esto es culpa nuestra.', NULL);

//3. NOTAS OCULTAS - RUTA CHICO / REPARTIRLOS POR CAPS
INSERT INTO catalogo_archivos (nombre, ruta_exclusiva, informacion, imagen_url) VALUES
('NOTA OCULTA 1: Registro de guardia', 'chico', 'Ayer trajeron a un tipo que mordió a dos oficiales en la calle. Lo metimos en la celda 3. No reacciona al dolor, no parpadea. Le dimos con las porras y ni siquiera se quejó. El Capitán dice que es solo pánico inducido por drogas, pero los que fueron mordidos hoy tienen una fiebre altísima y los ojos inyectados en sangre.', NULL),
('NOTA OCULTA 2: Memorándum denegado', 'chico', 'SOLICITUD DENEGADA. Bajo ninguna circunstancia se debe distribuir munición pesada o armamento antidisturbios a los oficiales de patrulla. Órdenes estrictas de los benefactores corporativos: dejen que la situación "se desarrolle" con el equipo estándar.', NULL),
('NOTA OCULTA 3: Transcripción de radio interceptada', 'chico', '[Canal Táctico Cifrado] Equipo Echo a Base. La contención del recinto ha fallado. Repito, contención fallida. Base: Entendido, Echo. Los "Limpiadores" están en camino. Abandone a las fuerzas locales. Aseguren la muestra y eliminen cualquier cabo suelto.', NULL),
('NOTA OCULTA 4: Bitácora forense', 'chico', 'Las heridas se vuelven necróticas en cuestión de minutos. La coagulación se detiene por completo. Traté de amputarle el brazo al oficial Gómez para detener la infección, pero el miembro amputado… seguía teniendo espasmos. Ya no es humano. El cerebro está muerto, pero el cuerpo sigue buscando alimento.', NULL),
('NOTA OCULTA 5: Mensaje en la pared', 'chico', 'NO VAYAN A LAS ALCANTARILLAS. Están subiendo por los túneles. No son los muertos de la calle. Son otra cosa. Tienen garras. No tienen piel. Cazan por el sonido.', NULL),
('NOTA OCULTA 6: Orden de evacuación VIP', 'chico', 'Lista de Extracción Prioritaria. Solo personal de nivel 4 o superior. El perímetro de la ciudad se cerrará permanentemente a las 00:00 horas. Directiva: Ningún civil, policía o personal médico está autorizado a salir. Disparen a matar a cualquiera que se acerque a los muros.', NULL),
('NOTA OCULTA 7: Nota ensangrentada de un mercenario', 'chico', 'Aseguramos la muestra del virus, pero esa maldita aberración gigante mató a Jenkins. Escondí el maletín en el cuarto del generador principal. Si algún desgraciado sigue vivo y lee esto… no confíes en el helicóptero de extracción. Nos traicionaron a todos.', NULL);
?>