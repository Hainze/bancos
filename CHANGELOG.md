# Changelog

Todos los cambios importantes en este proyecto se registran en este archivo.

El formato sigue una estructura simple por version para que sea facil mantener el historial.

## [Unreleased]

### Added

- Hub principal unificado para acceder a todos los modulos.
- Buscador global de herramientas en la pantalla inicial.
- Filtros por estado para mostrar herramientas activas o proximamente.
- Sistema de alertas en el hub para modulos que reportan vencimientos.
- Modulo de Credicoop con procesador local de archivos `.xls` a `.xlsx`.
- Documentacion actualizada del sistema completo en `README.md`.

### Changed

- Login endurecido con redireccion segura y regeneracion de sesion.
- Cookie de sesion adaptada para usar `secure` cuando el acceso es por HTTPS.
- Conteo del hub alineado con la logica real de herramientas activas.

### Fixed

- Se evito que la contraseña sea recortada al hacer login.
- Se corrigio la inconsistencia entre el contador de activas y el filtro visual.

## [2.0.0] - 2026-07-01

### Added

- Nuevo dashboard central `index.php` con tarjetas agrupadas por modulo.
- Modulos operativos para:
  - Bancos: Provincia, Nacion, Credicoop, Hipotecario y Mercado Pago.
  - Contabilidad y fiscal: Facturacion, Fiserv, IVA, Impuestos y Ganancias.
  - Gestion: Informe, Cobranza, Echeqs, Proveedores, Vencimientos y Sociedades.
  - Utilidades: Separacion y Pasos.
- Procesador dedicado para extractos de Banco Credicoop.
- Archivo de instrucciones funcionales para Credicoop.

### Changed

- Se unifico la presentacion visual del sistema bajo el concepto SmartAdmin.
- Se ordenaron los accesos por categoria funcional.

### Fixed

- Se normalizo la experiencia de navegacion entre modulos.
- Se mejoro la consistencia de nombres, descripciones y accesos del hub.

## [1.0.0]

### Added

- Base inicial del sistema de gestion bancaria.
- Autenticacion por sesion.
- Primeros modulos de carga, importacion y reporte.

