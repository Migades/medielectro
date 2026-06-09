# Medielectro — Documentación Técnica

## Índice

1. [Visión general](#1-visión-general)
2. [Stack tecnológico](#2-stack-tecnológico)
3. [Arquitectura](#3-arquitectura)
4. [Entidades y base de datos](#4-entidades-y-base-de-datos)
5. [Controladores](#5-controladores)
6. [Servicios y comandos](#6-servicios-y-comandos)
7. [Templates](#7-templates)
8. [Seguridad](#8-seguridad)
9. [Configuración de entornos](#9-configuración-de-entornos)
10. [Decisiones de diseño](#10-decisiones-de-diseño)
11. [Instalación y puesta en marcha](#11-instalación-y-puesta-en-marcha)

---

## 1. Visión general

Medielectro es una aplicación ecommerce full-stack desarrollada a medida para una tienda de electrodomésticos en Valencia. Permite la navegación y compra de productos, gestión interna de pedidos y catálogo, e integración con sistemas externos (ERP, Icecat).

**Repositorio:** https://github.com/Migades/medielectro  
**Rama principal:** `main`  
**Entorno de producción:** `pre.medielectro.es` (servidor v-sac con Plesk)

---

## 2. Stack tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.4 + Symfony 8 |
| ORM | Doctrine ORM |
| Base de datos (local) | MariaDB 12.x |
| Base de datos (servidor) | MariaDB 10.11.17 |
| Templates | Twig |
| Assets | AssetMapper (sin bundler) |
| Control de versiones | Git + GitHub |
| Servidor local | Symfony CLI |
| Servidor producción | Plesk + Apache/Nginx |

---

## 3. Arquitectura

El proyecto sigue la arquitectura MVC estándar de Symfony:

```
medielectro/
├── src/
│   ├── Controller/       # Controladores HTTP
│   ├── Entity/           # Entidades Doctrine (modelos)
│   ├── Repository/       # Repositorios de BD
│   ├── Cart/             # Servicio y modelo del carrito
│   ├── Command/          # Comandos CLI (importación, seeds)
│   └── Mail/             # Servicio de emails
├── templates/            # Vistas Twig
├── public/               # Punto de entrada (index.php) y assets
├── config/               # Configuración Symfony
├── migrations/           # Migraciones Doctrine
└── assets/               # CSS/JS fuente
```

### Flujo de una petición

1. `public/index.php` recibe la petición
2. El kernel de Symfony la enruta al controlador correspondiente
3. El controlador consulta repositorios / servicios
4. Renderiza la respuesta con Twig
5. El navegador recibe HTML + assets (CSS/JS servidos por AssetMapper)

---

## 4. Entidades y base de datos

### Product

Entidad central del catálogo. Importada desde CSV/ODS del proveedor.

| Campo | Tipo | Descripción |
|---|---|---|
| id | int | Clave primaria autoincremental |
| article | string(100) | Referencia única del producto |
| model | string(255) | Nombre del modelo |
| ean | string(50) | Código EAN/barras |
| description | text | Descripción larga (pendiente Icecat) |
| brand | string(255) | Marca |
| price | decimal(10,2) | Precio IVA incluido |
| stock | int | Unidades disponibles |
| pvpr | decimal(10,3) | PVP recomendado (actualmente oculto) |
| digitalCanon | decimal(10,2) | Canon digital si aplica |
| slug | string(255) | URL amigable |
| isActive | bool | Visible en catálogo |
| obsolete | bool | Producto descatalogado |
| image | string(255) | URL de imagen principal |
| attributes | JSON | Atributos técnicos (filtros avanzados) |
| vatCode | string(5) | Tipo IVA (default: 21) |
| family | FK Family | Categoría principal |
| subfamily | FK Subfamily | Subcategoría |

### Family / Subfamily

Jerarquía de categorías del catálogo. Seeding inicial con `app:catalog:seed`.

| Campo | Tipo | Descripción |
|---|---|---|
| id | int | Clave primaria |
| name | string | Nombre de la categoría |
| slug | string | URL amigable |
| isVisible | bool | Visible en menú/catálogo |

`Family` tiene relación OneToMany con `Subfamily`.

### Order

Pedido realizado por un cliente.

| Campo | Tipo | Descripción |
|---|---|---|
| id | int | Clave primaria |
| reference | string(30) | Referencia legible (ME-2026-00001) |
| customer | FK Customer | Cliente asociado |
| status | string(20) | Estado actual del pedido |
| total | decimal(10,2) | Total del pedido |
| shippingAddress | string | Dirección de entrega (snapshot) |
| shippingZip | string(10) | Código postal |
| shippingCity | string(100) | Ciudad |
| notes | text | Observaciones |
| erpStatus | string(20) | Estado sincronización ERP |
| createdAt | datetime | Fecha de creación |
| updatedAt | datetime | Última modificación |

**Estados del pedido:**

| Constante | Valor | Descripción |
|---|---|---|
| STATUS_PENDING | pending | Recibido, pendiente de confirmar |
| STATUS_CONFIRMED | confirmed | Confirmado por el equipo |
| STATUS_PREPARING | preparing | En preparación en almacén |
| STATUS_READY | ready | Listo para envío |
| STATUS_SHIPPED | shipped | Enviado al cliente |
| STATUS_DELIVERED | delivered | Entregado |
| STATUS_CANCELLED | cancelled | Cancelado |

### OrderLine

Línea de pedido. Guarda un snapshot del producto en el momento de la compra — sin FK al producto para evitar problemas si se elimina.

| Campo | Tipo | Descripción |
|---|---|---|
| productArticle | string(100) | Referencia del producto |
| productTitle | string(255) | Nombre en el momento de compra |
| productBrand | string(255) | Marca en el momento de compra |
| unitPrice | decimal(10,2) | Precio unitario |
| quantity | int | Cantidad |
| subtotal | decimal(10,2) | unitPrice × quantity |

### Customer

Datos del cliente que realiza el pedido.

| Campo | Tipo |
|---|---|
| name | string(255) |
| email | string(180) |
| phone | string(20) |
| company | string(255) |
| address | string(255) |
| zip | string(10) |
| city | string(100) |
| createdAt | datetime |

### CsvImport

Registro de importaciones de catálogo. Evita reimportar el mismo fichero.

---

## 5. Controladores

### HomeController
**Ruta:** `/`  
Renderiza la página principal con productos destacados, marcas y servicios. Consulta productos con stock >= 5 ordenados por stock DESC.

### CatalogController
**Ruta:** `/catalogo`  
Listado de productos con filtros dinámicos:
- Búsqueda por texto (`q`)
- Filtro por familia (`family`)
- Filtro por precio (rango `min_price`, `max_price`)
- Filtro por marca (`brand`)
- Paginación AJAX con offset (`offset`)
- Ordenación (`sort`: relevance, price_asc, price_desc, name)

### ProductController
**Ruta:** `/producto/{article}`  
Ficha de producto individual. Consulta además hasta 4 productos relacionados de la misma subfamilia.

### SearchController
**Ruta:** `/api/search` (GET, JSON)  
Endpoint AJAX para el buscador en tiempo real. Busca por título, modelo, marca y referencia. Devuelve hasta 6 resultados con imagen, precio y URL.

### CartController
**Rutas:** `/carrito`, `/carrito/añadir/{article}`, `/carrito/eliminar/{article}`, `/carrito/checkout`  
Gestión del carrito mediante sesión PHP. El checkout crea el `Order` y `Customer` en BD y envía emails de confirmación.

### ContactController
**Ruta:** `/contacto`  
Formulario de contacto con validación. Envía email al equipo via Symfony Mailer.

### ServiceController
**Rutas:** `/reparacion`, `/instalacion`  
Páginas estáticas de servicios.

### LegalController
**Rutas:** `/legal/aviso-legal`, `/legal/privacidad`, `/legal/cookies`  
Páginas legales con placeholders `PENDIENTE_*` hasta recibir datos reales de la empresa.

### SeoController
**Rutas:** `/sitemap.xml`, `/robots.txt`  
Generación dinámica de sitemap con todas las URLs activas. El robots.txt bloquea `/admin`, `/carrito` y `/checkout`.

### AdminController
**Ruta base:** `/core-access-mel`  
Panel de administración protegido por autenticación. Funcionalidades:
- Listado de pedidos con filtros por estado, fecha y búsqueda
- Detalle de pedido con cambio de estado
- Exportar pedidos a CSV
- Imprimir hoja de pedidos del día (PDF)
- Eliminar pedidos

### AdminProductController
**Ruta base:** `/core-access-mel/productos`  
Gestión del catálogo desde el panel:
- Listado con búsqueda y filtro por estado
- Crear producto manual
- Editar producto (precio, stock, descripción, estado, familia)
- Eliminar producto
- Endpoint AJAX `/core-access-mel/productos/subfamilias/{id}` para carga dinámica de subfamilias

---

## 6. Servicios y comandos

### CartService (`src/Cart/`)
Gestiona el carrito en sesión PHP. Métodos principales: `add()`, `remove()`, `clear()`, `getItems()`, `getTotal()`.

### OrderMailer (`src/Mail/`)
Envía dos emails al confirmar un pedido:
- Confirmación al cliente con resumen del pedido
- Notificación interna al equipo

### Comandos CLI

| Comando | Descripción |
|---|---|
| `app:catalog:seed` | Inserta familias y subfamilias desde JSON |
| `app:import:csv` | Importa productos desde CSV del proveedor |
| `app:import:ods` | Importa productos desde ODS (HEPECASA) |
| `app:import:attributes` | Importa atributos de filtro desde Excel |
| `app:product:fetch-images` | Busca imágenes en Open Icecat |
| `app:product:stock-images` | Asigna imágenes de loremflickr por subfamilia |

---

## 7. Templates

Todos los templates extienden `base.html.twig` que incluye:
- Header con logo, buscador AJAX, botón carrito
- Mega-menú de categorías con submenús desplegables
- Footer con enlaces a secciones, marcas y páginas legales
- Script de Google Analytics 4 (`G-74PTX4E8B1`) con Consent Mode
- Banner de cookies LOPD (aceptar/rechazar con localStorage)
- CSS global con variables: `--brand` (#e30613), `--card`, `--muted`, `--border`, `--radius`

**Estructura de templates:**
```
templates/
├── base.html.twig
├── home/index.html.twig
├── catalog/index.html.twig
├── product/show.html.twig
├── cart/
│   ├── index.html.twig
│   ├── checkout.html.twig
│   └── confirmed.html.twig
├── contact/index.html.twig
├── service/
│   ├── instalacion.html.twig
│   └── reparacion.html.twig
├── legal/
│   ├── aviso-legal.html.twig
│   ├── privacidad.html.twig
│   └── cookies.html.twig
├── admin/
│   ├── login.html.twig
│   ├── index.html.twig
│   ├── order_show.html.twig
│   ├── print_orders.html.twig
│   └── products/
│       ├── index.html.twig
│       └── form.html.twig
├── seo/
│   ├── sitemap.xml.twig
│   └── robots.txt.twig
├── mail/
│   ├── order_confirmation.html.twig
│   └── order_internal.html.twig
└── bundles/TwigBundle/Exception/
    └── error404.html.twig
```

---

## 8. Seguridad

### Autenticación
- Usuario único en memoria (`admin`) con contraseña hasheada con bcrypt (coste 13)
- Sesión con cookie `HttpOnly`, `SameSite: lax`, expiración a 1 hora
- Ruta del panel: `/core-access-mel` (no predecible, no indexada)

### Rate limiting
- Máximo 5 intentos de login fallidos por IP
- Bloqueo de 15 minutos tras superar el límite
- Implementado con `login_throttling` de Symfony

### Acceso
- Todas las rutas `/core-access-mel/*` requieren `ROLE_ADMIN`
- Login en `/core-access-mel/login` es la única ruta pública del panel
- Robots.txt bloquea `/admin` (ruta antigua, retorna 404)

---

## 9. Configuración de entornos

### `.env.local` (no subir a Git)
```
APP_ENV=dev
APP_DEBUG=false
DATABASE_URL="mysql://root:@127.0.0.1:3306/medielectro"
MAILER_DSN=gmail+smtp://email%40gmail.com:APP_PASSWORD@default
```

### Variables de entorno necesarias en producción
```
APP_ENV=prod
APP_DEBUG=false
APP_SECRET=<clave_aleatoria_32_chars>
DATABASE_URL=<url_mariadb_produccion>
MAILER_DSN=<smtp_produccion>
```

---

## 10. Decisiones de diseño

**Sin framework CSS externo:** Todo el CSS es propio con variables CSS. Evita dependencias de Bootstrap o Tailwind y permite control total del diseño.

**AssetMapper en vez de Webpack:** Symfony 8 recomienda AssetMapper para proyectos sin necesidades complejas de bundling. Más simple, sin Node.js en producción.

**Snapshot de productos en OrderLine:** Los datos del producto se copian en la línea del pedido en el momento de la compra. Si el producto cambia de precio o se elimina, el historial de pedidos permanece íntegro.

**Carrito en sesión PHP:** Para este volumen de tráfico es suficiente y no requiere BD adicional. Si escala se migraría a Redis.

**MariaDB en lugar de PostgreSQL:** Se migró desde PostgreSQL a MariaDB por compatibilidad con el hosting de producción (Plesk) y el CSV del proveedor.

**CSS inline en templates:** Los estilos específicos de cada página van en `{% block body %}` del propio template para evitar archivos CSS fragmentados y facilitar el mantenimiento.

---

## 11. Instalación y puesta en marcha

### Requisitos
- PHP 8.4+
- Composer
- Symfony CLI
- MariaDB 10.11+
- Git

### Pasos

```bash
# 1. Clonar repositorio
git clone https://github.com/Migades/medielectro.git
cd medielectro

# 2. Instalar dependencias
composer install

# 3. Configurar entorno
cp .env .env.local
# Editar .env.local con DATABASE_URL y MAILER_DSN

# 4. Crear base de datos
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Crear familias y subfamilias
php bin/console app:catalog:seed

# 6. Importar productos
php -d memory_limit=512M bin/console app:import:csv "ruta/al/archivo.csv"

# 7. Arrancar servidor
symfony serve --no-tls
```

### Credenciales de admin (desarrollo)
- **URL:** `http://localhost:8000/core-access-mel`
- **Usuario:** `admin`
- **Contraseña:** definida en `config/packages/security.yaml`

> En producción cambiar la contraseña con `php bin/console security:hash-password`
