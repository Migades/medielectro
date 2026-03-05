# Medielectro

Proyecto web desarrollado con **Symfony** para gestionar un catálogo online sincronizado con datos externos y conectado con **A3ERP**.

## Objetivo del proyecto

Construir una web que permita:

- importar productos, precios y stock desde un **CSV** y/o desde **A3ERP**
- mostrar un **catálogo online**
- permitir el flujo de **carrito y checkout**
- guardar los **pedidos de la web**
- enviar la información del pedido al **ERP**
- generar o registrar la **factura**
- enviar confirmaciones y documentación por **email**
- mantener **logs** de importación e integración

## Flujo general del sistema

```text
CSV / A3ERP
    ↓
Importador Symfony
    ↓
Base de datos de la web
    ↓
Catálogo / carrito / checkout
    ↓
Pedido web guardado
    ↓
Envío a A3ERP
    ↓
Factura / respuesta / email / logs
