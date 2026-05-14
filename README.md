# Resident Evil - The Survival Horror

Juego de terror y supervivencia basado en navegador, inspirado en la saga clasica de Resident Evil. Desarrollado como proyecto final utilizando PHP, SQLite, HTML, CSS y JavaScript. El jugador explora las habitaciones interconectadas de una comisaria de policia, resuelve puzzles, gestiona su inventario y se enfrenta a enemigos en combates por turnos.

---

## Indice

- [Descripcion General](#descripcion-general)
- [Funcionalidades](#funcionalidades)
- [Estructura del Proyecto](#estructura-del-proyecto)
- [Tecnologias Utilizadas](#tecnologias-utilizadas)
- [Esquema de Base de Datos](#esquema-de-base-de-datos)
- [Mecanicas de Juego](#mecanicas-de-juego)
- [Salas y Navegacion](#salas-y-navegacion)
- [Enemigos](#enemigos)
- [Objetos y Armas](#objetos-y-armas)
- [Puzzles](#puzzles)
- [Sistema de Guardado](#sistema-de-guardado)
- [Logros](#logros)
- [Panel de Administracion](#panel-de-administracion)
- [Instalacion](#instalacion)
- [Credenciales por Defecto](#credenciales-por-defecto)
- [Nota sobre las Imagenes](#nota-sobre-las-imagenes)

---

## Descripcion General

Este proyecto es un juego de terror en navegador ambientado en la comisaria infestada de Raccoon City. El jugador encarna a un superviviente que debe explorar el edificio, recoger suministros, resolver puzzles ambientales y enfrentarse al jefe final en tres fases, conocido como "El Recopilador", para lograr escapar.

El juego incluye dos rutas de personaje (chico/chica), cada una con objetos exclusivos y elementos narrativos propios. Todo el estado de la partida se guarda por usuario en una base de datos SQLite, permitiendo guardar y retomar el progreso en cualquier momento.

---

## Funcionalidades

- Exploracion por clic en 15 habitaciones interconectadas
- Sistema de combate por turnos con zonas de impacto (cabeza, torso, piernas)
- Gestion de inventario con 8 ranuras de objetos
- Multiples tipos de puzzle: estatua de medallones, panel electrico, caja fuerte portatil y cerraduras con llaves
- Sistema de doble ruta de personaje (Chico / Chica) con armas e items exclusivos
- Sistema de guardado mediante maquinas de escribir con cintas de tinta consumibles
- Sistema de logros con 9 desbloqueos disponibles
- Enciclopedia de Raccoon City con entradas de lore y catalogo de enemigos
- Panel de administracion para gestion de roles y visibilidad de zombies
- Efecto visual de pantalla CRT en el menu principal
- Sonido ambiental y audio atmosferico
- Zonas de interaccion calculadas mediante coordenadas porcentuales sobre la imagen
- Sistema de notas ocultas con 7 documentos de historia secreta

---

## Estructura del Proyecto

```
Proyecto-ResidentEvil/
├── index.php                  # Menu principal y punto de entrada de autenticacion
├── check_sala.php             # Validacion de acceso a salas
├── database/
│   ├── db_init.php            # Inicializacion de base de datos, esquema y datos iniciales
│   └── resident_evil.sqlite3  # Archivo SQLite (generado automaticamente en el primer arranque)
├── includes/
│   └── conexion.php           # Conexion PDO a la base de datos
├── pages/
│   ├── juego.php              # Motor principal del juego y renderizador de salas
│   ├── combate.php            # Pantalla de combate y logica de batalla por turnos
│   ├── enciclopedia.php       # Enciclopedia de enemigos y lore
│   ├── perfil.php             # Pagina de perfil del usuario
│   ├── logros.php             # Pantalla de logros
│   └── admin.php              # Panel de administracion
├── src/api/
│   ├── get_inventario.php          # Obtener inventario del jugador
│   ├── recoger_objeto.php          # Recoger objeto de la sala
│   ├── eliminar_objeto.php         # Eliminar objeto del inventario
│   ├── mover_objeto.php            # Reordenar ranuras del inventario
│   ├── combinar_objetos.php        # Combinar dos objetos
│   ├── guardar_partida.php         # Guardar estado de la partida
│   ├── get_save_slots.php          # Recuperar slots de partida guardada
│   ├── limpiar_inventario.php      # Limpiar inventario al iniciar partida nueva
│   ├── resolver_electricidad.php   # Resolver puzzle del panel electrico
│   ├── resolver_caja.php           # Resolver puzzle de la caja fuerte del capitan
│   ├── resolver_portable.php       # Resolver puzzle de la caja fuerte portatil
│   ├── resolver_puzzle_medallon.php # Resolver puzzle de la estatua de medallones
│   ├── colocar_medallones.php      # Colocar medallones en la estatua
│   ├── check_medallones.php        # Validar completitud de los medallones
│   ├── spawn_jefe.php              # Activar el encuentro con el jefe final
│   ├── admin_get_usuarios.php      # Admin: obtener lista de usuarios
│   ├── admin_cambiar_rol.php       # Admin: cambiar rol de usuario
│   └── admin_toggle_zombies.php    # Admin: alternar visibilidad de zombies
├── sessions/
│   ├── login.php
│   ├── logout.php
│   └── registro.php
├── js/
│   ├── interacciones.js    # Motor de interacciones (hotspots, eventos, modales)
│   ├── inventario.js       # Logica de la interfaz del inventario
│   └── movimientos.js      # Navegacion entre salas y controles de direccion
├── styles/
│   ├── style.css           # Estilos base globales
│   ├── index.css           # Estilos del menu principal
│   ├── juego.css           # HUD y estilos de la sala en juego
│   ├── inventario.css      # Estilos de la cuadricula del inventario
│   ├── auth.css            # Estilos de login y registro
│   ├── perfil.css          # Estilos de la pagina de perfil
│   ├── logros.css          # Estilos de la pantalla de logros
│   └── admin.css           # Estilos del panel de administracion
├── img/                    # Todos los assets del juego (salas, enemigos, objetos, UI)
└── sounds/
    └── ambiente_index.mp3  # Musica ambiental del menu
```

---

## Tecnologias Utilizadas

| Capa        | Tecnologia                     |
|-------------|-------------------------------|
| Backend     | PHP 8.x                       |
| Base de datos | SQLite3 (via PHP PDO)       |
| Frontend    | HTML5, CSS Vanilla, JS Vanilla |
| Servidor Web| Apache via XAMPP              |
| Sesiones    | Sesiones nativas de PHP       |
| Autenticacion | Hashing de contrasenas bcrypt |

No se utilizan frameworks ni librerias externas. Toda la logica esta implementada en PHP, CSS y JavaScript puros.

---

## Esquema de Base de Datos

La base de datos utiliza 12 tablas organizadas en tres bloques logicos:

### Tablas de Usuario y Progreso

| Tabla                  | Descripcion                                                    |
|------------------------|----------------------------------------------------------------|
| `usuarios`             | Usuarios registrados (nombre, email, contrasena hasheada, rol) |
| `partida`              | Slots de guardado activos por usuario (ruta, sala actual, slot)|
| `estado_personaje`     | Vida y cuchillos defensivos del jugador por partida            |
| `inventario`           | Objetos del jugador por partida (hasta 8 ranuras)              |
| `estado_enemigos`      | Vida y estado de enemigos por partida (vivo/muerto/aturdido)   |
| `eventos_completados`  | Registro de eventos interactivos ya activados                  |
| `logros_desbloqueados` | Logros desbloqueados por usuario                               |

### Tablas de Catalogo (Datos de Juego de Solo Lectura)

| Tabla               | Descripcion                                             |
|---------------------|---------------------------------------------------------|
| `catalogo_armas`    | Definicion de armas (dano, ruta, imagen)                |
| `catalogo_items`    | Definicion de objetos (tipo, efecto, imagen)            |
| `catalogo_archivos` | Notas y documentos de lore                              |
| `catalogo_enemigos` | Estadisticas de enemigos (vida, dano, precision, zonas) |
| `catalogo_salas`    | Definicion de salas y conexiones de navegacion          |
| `catalogo_logros`   | Definicion de logros                                    |

### Tabla de Eventos

| Tabla                   | Descripcion                                                            |
|-------------------------|------------------------------------------------------------------------|
| `eventos_interactivos`  | Coordenadas de hotspots y acciones por sala (recoger, puzzle, puerta...) |

---

## Mecanicas de Juego

### Exploracion

El juego renderiza una imagen de sala a pantalla completa. Sobre ella se superponen zonas invisibles de interaccion con coordenadas porcentuales (`xmin`, `xmax`, `ymin`, `ymax`) calculadas respecto a las dimensiones de la imagen. Al pasar el cursor sobre un hotspot aparece una etiqueta contextual. Al hacer clic se ejecuta la accion asociada.

Tipos de accion soportados:

| Tipo de Accion    | Descripcion                                         |
|-------------------|-----------------------------------------------------|
| `recoger_arma`    | Añadir un arma al inventario                        |
| `recoger_item`    | Añadir un objeto al inventario                      |
| `leer_archivo`    | Mostrar una nota o documento de lore                |
| `puzzle`          | Abrir un modal de puzzle                            |
| `guardar`         | Abrir el modal de guardado de partida               |
| `desbloquear`     | Usar un objeto clave para desbloquear una puerta    |
| `abrir_contenedor`| Abrir un contenedor cerrado con un objeto clave     |
| `transicion`      | Navegar a una sala conectada                        |

### Combate

Al entrar en una sala con enemigos vivos, el jugador es redirigido a `combate.php`. El combate es por turnos:

1. El jugador selecciona un arma del inventario y una zona de impacto (cabeza/torso/piernas).
2. La precision de impacto se calcula segun las probabilidades definidas por zona para cada enemigo.
3. El dano se aplica con un multiplicador para disparos a la cabeza.
4. Los disparos a las piernas tienen probabilidad de aturdir al enemigo, saltandose su siguiente turno.
5. El enemigo contraataca si no es eliminado ni aturdido.
6. El jugador puede intentar huir segun el valor `esquive_base` del enemigo.
7. Un cuchillo defensivo puede usarse para bloquear un unico ataque entrante.

### Inventario

El jugador dispone de 8 ranuras de inventario (posiciones 0-7). Los objetos se almacenan con tipo (`arma`, `item`, `archivo`) y cantidad. Los objetos pueden:

- Recogerse del entorno
- Moverse entre ranuras mediante arrastrar y soltar
- Usarse (curacion, guardado, combinacion)
- Descartarse

---

## Salas y Navegacion

El mapa del juego contiene 15 salas distribuidas en dos plantas de la comisaria de policia. La navegacion se gestiona mediante botones direccionales (Norte, Sur, Este, Oeste).

| ID de Sala             | Nombre Visual               | Planta |
|------------------------|-----------------------------|--------|
| `banos_inicio`         | Banos (Inicio)              | 1F     |
| `lobby_principal`      | Lobby Principal             | 1F     |
| `sala_espera`          | Sala de Espera              | 1F     |
| `oficina_este`         | Oficina Este                | 1F     |
| `oficina_oeste`        | Oficina Oeste               | 1F     |
| `cuarto_evidencias`    | Cuarto de Evidencias        | 1F     |
| `sala_archivos`        | Sala de Archivos            | 1F     |
| `sala_descanso`        | Sala de Descanso            | 1F     |
| `sala_interrogatorios` | Sala de Interrogatorios     | 1F     |
| `pasillo`              | Pasillo                     | 1F/2F  |
| `biblioteca`           | Biblioteca                  | 2F     |
| `sala_arte`            | Sala de Arte                | 2F     |
| `oficina_capitan`      | Oficina del Capitan         | 2F     |
| `sala_electrica`       | Sala Electrica              | 2F     |
| `sala_final`           | Sotano de la Comisaria      | B1     |

Las conexiones entre salas siguen un grafo direccional fijo. Algunas salas requieren objetos clave para acceder (por ejemplo, la puerta de la Oficina Este requiere el Cortacadenas, y la sala de archivos requiere la Llave de Pica).

---

## Enemigos

Se definen siete tipos de enemigo mas un jefe final de tres fases en el catalogo:

| Enemigo                   | Tipo            | Vida Max | Dano Base | Prob. Huida |
|---------------------------|-----------------|----------|-----------|-------------|
| Zombie Hombre             | Comun           | 50       | 25        | 35%         |
| Zombie Mujer              | Comun           | 50       | 25        | 35%         |
| Zombie Recluso            | Comun           | 50       | 25        | 35%         |
| Zombie Uniforme           | Comun           | 50       | 25        | 35%         |
| Licker                    | Mutante         | 75       | 50        | 25%         |
| Lastre                    | Zombie Pesado   | 120      | 15        | 60%         |
| Espasmo                   | Zombie Agil     | 40       | 20        | 15%         |
| El Recopilador - Fase 1   | Jefe            | 300      | 35        | 10%         |
| El Recopilador - Fase 2   | Jefe            | 400      | 25        | 0%          |
| El Recopilador - Fase 3   | Jefe            | 1000     | 80        | 5%          |

Cada enemigo tiene valores independientes de precision por zona de impacto y multiplicadores de disparo a la cabeza. El estado del enemigo (vida, vivo/muerto/aturdido) se persiste por slot de guardado, por lo que los enemigos eliminados no reaparecen.

---

## Objetos y Armas

### Armas

| Nombre                    | Dano | Ruta       |
|---------------------------|------|------------|
| Pistola M19               | 25%  | Ambas      |
| Escopeta W-870            | 75%  | Solo chico |
| Granada de Fragmentacion  | 100% | Ambas      |

### Objetos Clave

| Nombre                | Funcion                                              |
|-----------------------|------------------------------------------------------|
| Medallon de Leon      | Necesario para el puzzle de la estatua               |
| Medallon de Unicornio | Necesario para el puzzle de la estatua               |
| Medallon de Doncella  | Necesario para el puzzle de la estatua               |
| Caja Fuerte Portatil  | Contiene el Cortacadenas (combinacion: 911)          |
| Llave de Diamante     | Objeto clave de exploracion                          |
| Llave de Pica         | Desbloquea la puerta de archivos de la Oficina Oeste |
| Cortacadenas          | Desbloquea la puerta encadenada de la Oficina Este   |
| Cinta de Guardado     | Token de guardado de un solo uso para la maquina de escribir |

### Consumibles

| Nombre                 | Efecto                              |
|------------------------|-------------------------------------|
| Hierba Verde           | Restaura un 25% de vida             |
| Cuchillo Defensivo     | Bloquea un unico ataque entrante    |
| Municion de Pistola    | Municion para la Pistola M19        |
| Municion de Escopeta   | Municion para la Escopeta W-870     |

---

## Puzzles

### Estatua de Medallones (Lobby Principal)

Deben recogerse tres medallones en distintas ubicaciones del edificio y colocarlos en la estatua del lobby. Al completarlo se desbloquea el acceso a las zonas mas profundas de la comisaria.

### Panel Electrico (Sala Electrica)

Puzzle de colocacion de fusibles que debe resolverse para restablecer la electricidad. Completarlo es requisito para acceder a la sala final.

### Caja Fuerte del Capitan (Oficina del Capitan)

Cerradura de combinacion numerica. La combinacion (911) se encuentra en una nota oculta en la Sala de Archivos.

### Caja Fuerte Portatil (Oficina Oeste / Sala de Descanso)

Caja fuerte de menor tamano encontrada en el entorno. Se abre con un codigo obtenido durante la exploracion.

### Puerta Encadenada (Oficina Este)

Requiere el objeto Cortacadenas para poder avanzar. El Cortacadenas se obtiene de la caja fuerte portatil del capitan.

---

## Sistema de Guardado

El progreso se guarda mediante maquinas de escribir ubicadas en la Oficina Oeste y la Sala de Descanso. Cada guardado consume una Cinta de Guardado del inventario. Se admiten hasta 3 slots de guardado con nombre por usuario, ademas de un estado de guardado rapido. La pantalla de guardado muestra la sala actual, la marca de tiempo y el numero de slot.

---

## Logros

| Logro                    | Condicion                                                     |
|--------------------------|---------------------------------------------------------------|
| Bienvenido al Infierno   | Completar el primer capitulo                                  |
| Control de Plagas        | Eliminar a 5 zombies                                          |
| Intocable                | Huir con exito de 3 zombies                                   |
| Toma un Respiro          | Aturdir a 3 enemigos mediante disparos a las piernas          |
| El Acertijo de la Estatua| Encontrar y usar los tres medallones                          |
| Cientifico Caido         | Derrotar la primera fase de El Recopilador                    |
| Descenso a la Oscuridad  | Completar el segundo capitulo                                 |
| Superviviente Definitivo | Completar el tercer capitulo y derrotar al jefe final         |
| Fuerza Bruta             | Obtener la Escopeta W-870 (solo ruta masculina)               |

---

## Panel de Administracion

Los usuarios con rol `admin` pueden acceder al panel en `pages/admin.php`. Funciones disponibles:

- Ver la lista completa de usuarios registrados
- Ascender o degradar a cualquier usuario entre los roles `admin` y `jugador`
- Alternar el indicador de visibilidad de zombies en el juego por usuario, util para pruebas

La cuenta de administrador por defecto se crea automaticamente al inicializar la base de datos (ver Credenciales por Defecto).

---

## Instalacion

**Requisitos:**
- XAMPP (o cualquier stack Apache + PHP 8+)
- PHP compilado con las extensiones SQLite3 y PDO_SQLite habilitadas

**Pasos:**

1. Clona o copia la carpeta del proyecto en el directorio `htdocs` de XAMPP:

   ```
   C:\xampp\htdocs\ProyectoFinal\Proyecto-ResidentEvil\
   ```

2. Inicia Apache desde el Panel de Control de XAMPP.

3. Abre el navegador y navega a:

   ```
   http://localhost/ProyectoFinal/Proyecto-ResidentEvil/
   ```

4. La base de datos se inicializa automaticamente en el primer acceso mediante `database/db_init.php`. No es necesario importar ningun SQL manualmente.

5. Todos los fondos de sala, sprites de objetos y assets de enemigos deben estar presentes en el directorio `img/`. Se incluyen con el proyecto.

---

## Credenciales por Defecto

Se crea automaticamente una cuenta de administrador durante la inicializacion de la base de datos:

| Campo      | Valor                    |
|------------|--------------------------|
| Usuario    | `admin`                  |
| Email      | `admin@raccoon-city.gov` |
| Contrasena | `admin123`               |
| Rol        | `admin`                  |

Se recomienda encarecidamente cambiar esta contrasena antes de desplegar el proyecto en un servidor publico.

---

## Nota sobre las Imagenes

Todos los assets visuales del proyecto se almacenan en el directorio `img/` y se referencian mediante rutas relativas desde la base de datos y las plantillas PHP. Se incluyen las siguientes categorias de imagenes:

- **Fondos de sala** - 15 fondos PNG a tamano completo para cada ubicacion del juego
- **Sprites de enemigos** - Variantes estandar y en miniatura para cada tipo de enemigo
- **Iconos de objetos y armas** - Assets PNG transparentes para el HUD del inventario
- **Fases del jefe final** - Tres imagenes a tamano completo para el encuentro final
- **Assets de interfaz** - Textura de fondo de nota y logotipo del juego

Para añadir el proyecto a GitHub u otro sistema de control de versiones, se recomienda incluir el directorio `img/` en el repositorio, ya que no se utiliza ninguna CDN ni servicio externo de alojamiento de imagenes. Todas las rutas son relativas, por lo que el proyecto es completamente autocontenido y portable sin necesidad de cambiar ninguna configuracion de rutas.

Si faltan imagenes, el juego seguira funcionando pero los fondos de sala y los iconos de objetos no se renderizaran. Asegurate de incluir la carpeta `img/` al desplegar o compartir el proyecto.

Si quieres añadir capturas de pantalla reales al README, puedes hacerlo con la siguiente sintaxis de Markdown:

```markdown
![Descripcion de la imagen](img/nombre_del_archivo.png)
```

Esto funciona correctamente en GitHub cuando el repositorio es publico y la imagen esta incluida en el repositorio.

---

## Licencia

Este proyecto es un trabajo academico desarrollado como proyecto final. El nombre Resident Evil y la propiedad intelectual asociada pertenecen a Capcom Co., Ltd. Este proyecto no esta afiliado ni respaldado por Capcom y tiene una finalidad exclusivamente educativa.