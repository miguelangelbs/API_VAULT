# API VAULT - API de Videojuegos

API VAULT es una API REST desarrollada en PHP con CodeIgniter 4 que proporciona información completa sobre videojuegos. La aplicación se integra con la API externa RAWG para obtener datos actualizados de videojuegos y los almacena en una base de datos PostgreSQL. También permite la gestión de videojuegos creados por administradores y utiliza Cloudinary para el almacenamiento de imágenes.

---

## Tabla de Contenidos

1. [Características Principales](#características-principales)
2. [Stack Tecnológico](#stack-tecnológico)
3. [Estructura del Proyecto](#estructura-del-proyecto)
4. [Requisitos del Sistema](#requisitos-del-sistema)
5. [Instalación](#instalación)
6. [Configuración](#configuración)
7. [Uso de la API](#uso-de-la-api)
8. [Modelos de Datos](#modelos-de-datos)
9. [Respuestas de la API](#respuestas-de-la-api)
10. [Contribución](#contribución)
11. [Licencia](#licencia)

---

## Características Principales

- **Integración con RAWG API**: Sincronización automática de datos de videojuegos desde la API externa RAWG
- **Gestión de Contenido**: Los administradores pueden crear, editar y eliminar videojuegos directamente
- **Sistema de Imágenes**: Almacenamiento y gestión de imágenes via Cloudinary
- **Filtrado Avanzado**: Búsqueda por géneros, plataformas, tiendas, desarrolladores y publishers
- **Seguridad**: Validación de API Key para todos los endpoints protegidos
- **Integración con Steam**: Obtención de AppIDs para gráficos de Steam
- **Transacciones**: Manejo de transacciones para garantizar la integridad de los datos

---

## Stack Tecnológico

| Tecnología | Descripción |
|------------|-------------|
| **PHP 8.1+** | Lenguaje de programación principal |
| **CodeIgniter 4** | Framework PHP para desarrollo web |
| **PostgreSQL** | Sistema de gestión de base de datos (Neon Tech) |
| **RAWG API** | API externa para datos de videojuegos |
| **Steam API** | API de Steam para obtener AppIDs |
| **Cloudinary** | Almacenamiento y gestión de imágenes |
| **Composer** | Gestión de dependencias PHP |

---

## Estructura del Proyecto

```
API_VAULT/
├── app/
│   ├── Controllers/
│   │   ├── ApiController.php        # Gestión de datos desde RAWG API
│   │   ├── DataController.php       # Endpoints de datos y gestión
│   │   └── BaseController.php       # Controlador base
│   ├── Models/
│   │   ├── VideojuegoModelo.php     # Modelo de videojuegos
│   │   ├── GeneroModelo.php         # Modelo de géneros
│   │   ├── PlataformaModelo.php     # Modelo de plataformas
│   │   ├── TiendaModelo.php         # Modelo de tiendas
│   │   ├── PublisherModelo.php      # Modelo de publishers
│   │   ├── DesarrolladoraModelo.php # Modelo de desarrolladoras
│   │   └── AdministradoresModelo.php # Modelo de administradores
│   ├── Services/
│   │   └── ApiKeyValidator.php     # Validación de API keys
│   └── Config/
│       ├── Routes.php               # Definición de rutas
│       ├── Database.php             # Configuración de base de datos
│       └── ...
├── public/                          # Archivos públicos
├── vendor/                          # Dependencias de Composer
├── writable/                        # Archivos escribibles
└── .env                            # Variables de entorno
```

---

## Requisitos del Sistema

- **PHP 8.1** o superior
- **Extensiones PHP**: `intl`, `mbstring`
- **PostgreSQL** 12 o superior
- **Composer** para gestión de dependencias
- **Servidor Web**: Apache, Nginx o el servidor integrado de PHP

---

## Instalación

### 1. Clonar el Repositorio

```bash
git clone https://github.com/tu-usuario/api-vault.git
cd api-vault
```

### 2. Instalar Dependencias

```bash
composer install
```

### 3. Configurar Variables de Entorno

Crea un archivo `.env` en la raíz del proyecto con la siguiente configuración:

```env
# Configuración de la base de datos
database.default.host = tu_host
database.default.port = 5432
database.default.database = tu_base_de_datos
database.default.username = tu_usuario
database.default.password = tu_password

# Configuración de RAWG API
RAWG_API_KEY = tu_api_key_rawg
RAWG_API_URL = https://api.rawg.io/api

# Configuración de Steam
STEAM_API_KEY = tu_api_key_steam

# Configuración de Cloudinary
CLOUDINARY_CLOUD_NAME = tu_cloud_name
CLOUDINARY_API_KEY = tu_api_key
CLOUDINARY_API_SECRET = tu_api_secret

# Configuración de la aplicación
app.baseURL = http://localhost:8080
app.indexPage =
```

### 4. Configurar el Servidor Web

#### Usando el servidor integrado de PHP (desarrollo):

```bash
php spark serve
```

El servidor estará disponible en `http://localhost:8080`.

#### Usando Apache o Nginx:

Configura tu servidor web para que apunte a la carpeta `public/` del proyecto.

**Para Apache (.htaccess):**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]
```

**Para Nginx:**
```nginx
location / {
    try_files $uri $uri/ /index.php/$query_string;
}
```

### 5. Ejecutar Migraciones

Si tienes migraciones de base de datos pendientes:

```bash
php spark migrate
```

---

## Configuración

### Variables de Entorno

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `database.default.host` | Host de la base de datos PostgreSQL | db.example.com |
| `database.default.port` | Puerto de PostgreSQL | 5432 |
| `database.default.database` | Nombre de la base de datos | api_vault |
| `database.default.username` | Usuario de la base de datos | admin |
| `database.default.password` | Contraseña de la base de datos | ******** |
| `RAWG_API_KEY` | Clave de API de RAWG | your_rawg_key |
| `STEAM_API_KEY` | Clave de API de Steam | your_steam_key |
| `CLOUDINARY_CLOUD_NAME` | Nombre de cloud en Cloudinary | mycloud |
| `CLOUDINARY_API_KEY` | Clave API de Cloudinary | 123456789 |
| `CLOUDINARY_API_SECRET` | Secret de Cloudinary | ******** |
| `API_KEY` | Clave API para acceder a los endpoints | tu_api_key_segura |

---

## Uso de la API

### Autenticación

Todos los endpoints protegidos requieren una API Key válida en las cabeceras de la solicitud:

```bash
curl -H "X-API-KEY: tu_api_key" https://tu-dominio.com/endpoint
```

### Endpoints Disponibles

#### Obtención de Datos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/` | Página de inicio de la API |
| GET | `/recibirJuegos` | Obtiene todos los videojuegos |
| GET | `/recibirDatosJuego/{id}` | Obtiene datos de un juego específico |
| GET | `/recibirGeneros` | Lista de géneros disponibles |
| GET | `/recibirPlataformas` | Lista de plataformas disponibles |
| GET | `/recibirTiendas` | Lista de tiendas disponibles |
| GET | `/recibirDesarrolladoras` | Lista de desarrolladoras |
| GET | `/recibirPublishers` | Lista de publishers |
| GET | `/recibirJuegosFiltrados/{categoria}/{nombre}` | Juegos filtrados por categoría |
| GET | `/recibirJuegosAdmin` | Videojuegos creados por administradores |
| GET | `/obtenerDatosFormulario` | Datos para formularios |
| GET | `/actualizarDatosAPI` | Actualizar datos desde RAWG |
| GET | `/purgarDatos` | Resetear y recargar datos |
| GET | `/obtenerAppId` | Obtener AppID de Steam por nombre |

#### Autenticación de Administradores

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/inicioSesion` | Inicio de sesión de administrador |
| POST | `/crearAdministrador` | Crear cuenta de administrador |

#### Gestión de Videojuegos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/agregarJuego` | Agregar nuevo videojuegos |
| POST | `/editarJuego` | Editar videojuegos existente |
| POST | `/eliminarJuego` | Eliminar videojuegos |

#### Búsquedas

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/realizarBusqueda` | Búsqueda de videojuegos por nombre |
| POST | `/realizarBusquedaDesarrolladoras` | Búsqueda de desarrolladoras |
| POST | `/realizarBusquedaPublishers` | Búsqueda de publishers |

### Ejemplos de Uso

#### Obtener todos los videojuegos

```bash
curl -X GET "https://tu-dominio.com/recibirJuegos" \
     -H "X-API-KEY: tu_api_key"
```

**Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nombre": "The Legend of Zelda: Breath of the Wild",
      "nota_metacritic": 97,
      "fecha_lanzamiento": "2017-03-03",
      "imagen": "https://imagen.url/game.jpg",
      "descripcion": "Un juego de aventura..."
    }
  ]
}
```

#### Obtener un juego específico

```bash
curl -X GET "https://tu-dominio.com/recibirDatosJuego/1" \
     -H "X-API-KEY: tu_api_key"
```

#### Buscar videojuegos por nombre

```bash
curl -X POST "https://tu-dominio.com/realizarBusqueda" \
     -H "X-API-KEY: tu_api_key" \
     -H "Content-Type: application/json" \
     -d '{"nombre": "Zelda"}'
```

#### Iniciar sesión como administrador

```bash
curl -X POST "https://tu-dominio.com/inicioSesion" \
     -H "Content-Type: application/json" \
     -d '{"nombre": "admin", "password": "tu_contraseña"}'
```

#### Agregar un nuevo juego (requiere autenticación de administrador)

```bash
curl -X POST "https://tu-dominio.com/agregarJuego" \
     -H "X-API-KEY: tu_api_key" \
     -H "Content-Type: application/json" \
     -d '{
       "nombre": "Nuevo Juego",
       "descripcion": "Descripción del juego",
       "nota_metacritic": 85,
       "fecha_lanzamiento": "2024-01-01",
       "imagen": "https://imagen.url/nuevo.jpg"
     }'
```

---

## Modelos de Datos

### Videojuego

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INTEGER | Identificador único del juego |
| `nombre` | VARCHAR(255) | Título del videojuego |
| `nota_metacritic` | INTEGER | Puntuación de Metacritic (0-100) |
| `fecha_lanzamiento` | DATE | Fecha de lanzamiento del juego |
| `sitio_web` | TEXT | URL oficial del juego |
| `imagen` | TEXT | URL de la imagen del juego |
| `plataformas_principales` | JSON | Array de plataformas principales |
| `desarrolladoras` | JSON | Array de desarrolladoras |
| `publishers` | JSON | Array de publishers |
| `tiendas` | JSON | Array de tiendas donde comprar |
| `generos` | JSON | Array de géneros del juego |
| `descripcion` | TEXT | Descripción detallada del juego |
| `creado_por_admin` | BOOLEAN | Indica si fue creado por un administrador |

### Género

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INTEGER | Identificador único del género |
| `nombre` | VARCHAR(100) | Nombre del género |
| `cantidad_juegos` | INTEGER | Número de juegos en este género |
| `imagen` | TEXT | URL de la imagen representativa |

### Plataforma

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INTEGER | Identificador único de la plataforma |
| `nombre` | VARCHAR(100) | Nombre de la plataforma |
| `cantidad_juegos` | INTEGER | Número de juegos en esta plataforma |
| `imagen` | TEXT | URL de la imagen representativa |

### Tienda

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INTEGER | Identificador único de la tienda |
| `nombre` | VARCHAR(100) | Nombre de la tienda |
| `dominio` | VARCHAR(100) | Dominio de la tienda |
| `cantidad_juegos` | INTEGER | Número de juegos en esta tienda |
| `imagen` | TEXT | URL de la imagen representativa |

### Publisher

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INTEGER | Identificador único del publisher |
| `nombre` | VARCHAR(100) | Nombre del publisher |
| `cantidad_juegos` | INTEGER | Número de juegos publicados |
| `imagen` | TEXT | URL de la imagen representativa |

### Desarrolladora

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INTEGER | Identificador único de la desarrolladora |
| `nombre` | VARCHAR(100) | Nombre de la desarrolladora |
| `cantidad_juegos` | INTEGER | Número de juegos desarrollados |
| `imagen` | TEXT | URL de la imagen representativa |

### Administrador

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INTEGER | Identificador único del administrador |
| `nombre` | VARCHAR(100) | Nombre de usuario |
| `password` | VARCHAR(255) | Contraseña encriptada (hash) |
| `fecha_creacion` | DATETIME | Fecha de creación de la cuenta |
| `fecha_ultimo_login` | DATETIME | Fecha del último inicio de sesión |

---

## Respuestas de la API

La API utiliza códigos de estado HTTP estándar para indicar el resultado de las solicitudes:

| Código | Estado | Descripción |
|--------|--------|-------------|
| `200` | OK | Solicitud exitosa |
| `400` | Bad Request | Datos inválidos o mal formados |
| `401` | Unauthorized | Credenciales incorrectas o API Key inválida |
| `404` | Not Found | Recurso no encontrado |
| `500` | Internal Server Error | Error del servidor |

### Formato de Respuesta Exitosa

```json
{
  "success": true,
  "data": { ... }
}
```

### Formato de Respuesta de Error

```json
{
  "success": false,
  "message": "Descripción del error"
}
```

## Licencia

Este proyecto está bajo la Licencia MIT.

---

## Contacto

Para preguntas o sugerencias, por favor abre un issue en el repositorio de GitHub.

---

*Documentación generada para API VAULT - API de Videojuegos*