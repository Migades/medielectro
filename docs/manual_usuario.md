# Medielectro — Manual de Usuario
## Panel de administración

**Versión:** 1.0  
**Acceso:** `https://medielectro.es/core-access-mel`

---

## Índice

1. [Acceso al panel](#1-acceso-al-panel)
2. [Pantalla principal — Pedidos](#2-pantalla-principal--pedidos)
3. [Gestión de pedidos](#3-gestión-de-pedidos)
4. [Flujo de trabajo de un pedido](#4-flujo-de-trabajo-de-un-pedido)
5. [Exportar pedidos a Excel](#5-exportar-pedidos-a-excel)
6. [Hoja de pedidos del día](#6-hoja-de-pedidos-del-día)
7. [Gestión de productos](#7-gestión-de-productos)
8. [Cierre de sesión](#8-cierre-de-sesión)
9. [Preguntas frecuentes](#9-preguntas-frecuentes)

---

## 1. Acceso al panel

1. Abre el navegador y ve a `https://medielectro.es/core-access-mel`
2. Introduce tu usuario y contraseña
3. Pulsa **Entrar**

> Si introduces mal la contraseña 5 veces seguidas, el sistema bloqueará el acceso durante 15 minutos por seguridad.

> La sesión se cierra automáticamente tras 1 hora de inactividad.

---

## 2. Pantalla principal — Pedidos

Al entrar verás el panel principal con:

**Estadísticas rápidas** en la parte superior:
- Total de pedidos
- Pedidos nuevos (sin confirmar)
- Confirmados
- En preparación
- Listos para envío
- Enviados

**Filtros de fecha:**
- Todos
- Hoy
- Esta semana
- Este mes

**Filtros de estado:**
- Nuevo
- Confirmado
- En preparación
- Listo para envío
- Enviado
- Entregado
- Cancelado

**Buscador:** por referencia del pedido, nombre o email del cliente.

**Listado de pedidos** con columnas: referencia, fecha, cliente, total y estado.

---

## 3. Gestión de pedidos

### Ver el detalle de un pedido

Haz clic en cualquier fila del listado para abrir el detalle del pedido. Verás:

- Datos del cliente (nombre, email, teléfono)
- Dirección de entrega
- Productos pedidos con cantidades y precios
- Total del pedido
- Historial de estado

### Cambiar el estado de un pedido

Desde el detalle del pedido, verás los botones de estado en la parte derecha:

1. **Nuevo** → pedido recién recibido, nadie lo ha gestionado aún
2. **Confirmado** → has revisado el pedido y confirmas que hay stock
3. **En preparación** → el almacén está preparando el pedido
4. **Listo para envío** → el pedido está empaquetado y listo para que lo recoja el transportista
5. **Enviado** → el transportista ha recogido el pedido
6. **Entregado** → el cliente ha recibido el pedido
7. **Cancelado** → el pedido se cancela por cualquier motivo

Pulsa el botón del estado al que quieres avanzar. El cambio se guarda al instante.

### Eliminar un pedido

Desde el detalle del pedido, pulsa el botón **Eliminar pedido** en la parte superior derecha.

> Esta acción es irreversible. Solo elimina pedidos cancelados o erróneos.

---

## 4. Flujo de trabajo de un pedido

Este es el proceso recomendado cuando llega un pedido nuevo:

```
NUEVO
  ↓ Revisar el pedido, comprobar stock
CONFIRMADO
  ↓ Imprimir la hoja de pedidos y pasarla al almacén
EN PREPARACIÓN
  ↓ El almacén prepara y empaqueta
LISTO PARA ENVÍO
  ↓ El transportista recoge el pedido
ENVIADO
  ↓ El cliente confirma la recepción
ENTREGADO
```

### Pasos detallados

**1. Llega un pedido nuevo**
- El sistema envía un email automático al equipo
- Entra al panel y localiza el pedido en la lista (aparecerá con estado "Nuevo")

**2. Revisar el pedido**
- Haz clic en el pedido para ver el detalle
- Comprueba que los productos tienen stock suficiente
- Si todo está correcto → cambia a **Confirmado**
- Si hay algún problema → cambia a **Cancelado** y contacta al cliente

**3. Preparar el pedido**
- Cambia el estado a **En preparación**
- Imprime la hoja del pedido (ver sección 6) y entrégala al almacén

**4. Pedido listo**
- Cuando el almacén termine → cambia a **Listo para envío**

**5. Entrega al transportista**
- Cuando el transportista recoja el pedido → cambia a **Enviado**

**6. Confirmación de entrega**
- Cuando el cliente reciba el pedido → cambia a **Entregado**

---

## 5. Exportar pedidos a Excel

Para exportar los pedidos a un archivo Excel/CSV:

1. Aplica los filtros que quieras (fecha, estado, búsqueda)
2. Pulsa el botón verde **Exportar CSV** en la parte superior del panel
3. Se descargará un archivo con los pedidos filtrados

El archivo incluye: referencia, fecha, estado, cliente, email, teléfono, dirección, total y productos.

> Puedes abrirlo directamente en Excel o Google Sheets.

---

## 6. Hoja de pedidos del día

La hoja de pedidos es un documento imprimible para el almacén con todos los pedidos del día.

1. Pulsa el botón **Imprimir hoy** en la parte superior del panel
2. Se abrirá una nueva pestaña con la hoja formateada para imprimir
3. Pulsa el botón **Imprimir** o usa `Ctrl+P` / `Cmd+P`

La hoja incluye por cada pedido:
- Referencia y hora del pedido
- Datos del cliente y dirección de entrega
- Tabla de productos con referencia, descripción y cantidad
- Total del pedido
- Espacio para firma de "Preparado por" y "Entregado/Recogido"

> Los pedidos cancelados no aparecen en la hoja de impresión.

---

## 7. Gestión de productos

Accede a la sección de productos pulsando el botón **Productos** en el menú del panel.

### Buscar un producto

Usa el buscador para filtrar por referencia, modelo o marca. También puedes filtrar por estado (activo/inactivo).

### Añadir un producto

1. Pulsa **+ Añadir producto**
2. Rellena los campos:
   - **Referencia** — código único del producto (obligatorio)
   - **Modelo** — nombre del modelo (obligatorio)
   - **Marca** — fabricante
   - **Precio** — precio con IVA incluido (obligatorio)
   - **Stock** — unidades disponibles (obligatorio)
   - **Familia** — categoría principal (obligatorio)
   - **Subfamilia** — subcategoría (se carga al seleccionar familia)
   - **Descripción** — texto descriptivo
   - **Imagen** — URL de la imagen del producto
   - **Estado** — Activo o Inactivo
3. Pulsa **Crear producto**

### Editar un producto

1. Localiza el producto en el listado
2. Pulsa **Editar**
3. Modifica los campos necesarios
4. Pulsa **Guardar cambios**

> La referencia no se puede cambiar una vez creado el producto.

### Eliminar un producto

1. Localiza el producto en el listado
2. Pulsa **Eliminar**
3. Confirma la acción en el diálogo

> Esta acción es irreversible. Los productos eliminados no afectan a los pedidos ya realizados porque los pedidos guardan una copia de los datos del producto.

---

## 8. Cierre de sesión

Pulsa **Cerrar sesión** en la parte superior del panel. También puedes cerrar el navegador — la sesión se cerrará automáticamente tras 1 hora.

---

## 9. Preguntas frecuentes

**¿Qué pasa si olvido la contraseña?**  
Contacta con el administrador técnico para que regenere la contraseña desde el servidor.

**¿Puedo tener varios usuarios en el panel?**  
De momento el sistema tiene un único usuario. Si se necesitan más usuarios hay que modificar la configuración de seguridad.

**¿Los pedidos cancelados se eliminan solos?**  
No, permanecen en el sistema con estado "Cancelado". Hay que eliminarlos manualmente si se desea.

**¿Qué pasa si el sistema se bloquea tras varios intentos de login?**  
El bloqueo dura 15 minutos. Espera ese tiempo e inténtalo de nuevo con la contraseña correcta.

**¿Puedo acceder al panel desde el móvil?**  
Sí, el panel es accesible desde cualquier dispositivo con navegador, aunque está optimizado para escritorio.

**¿Con qué frecuencia se actualiza el catálogo de productos?**  
El catálogo se importa manualmente desde el CSV del proveedor cuando hay novedades, mediante el comando `app:import:csv`.
