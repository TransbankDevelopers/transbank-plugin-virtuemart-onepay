# Changelog
Todos los cambios notables a este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
y este proyecto adhiere a [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [1.0.5] - 2018-12-06
### Fixed
- Corrige compatibilidad con la app de Onepay cuando se compra mediante un dispositivo móvil.
### Added
- Agrega uso de `transactionDescription` cuando el carro tiene un item.

## [1.0.4] - 2018-11-28
### Fixed
- Corrige visualización errónea del botón de instalación de Onepay desde el App Store, que impedía que los usuarios pudieran descargar la aplicación si no la tenían instalada

## [1.0.3] - 2018-11-15
### Changed
- Mejora el comportamiento para usuarios iOS que no poseen la aplicación Onepay instalada

## [1.0.2] - 2018-10-29
### Changed
- Se corrige un problema de incompatibilidad de Onepay con virtuemart y joomla.
- Corrige un problema de comunicación entre la ventana de pago de Onepay y el servicio de pago de Onepay
- Corrige un problema al abrir la aplicación instalada de Onepay desde el browser de Android.

## [1.0.1] - 2018-10-25
### Changed
- Se actualiza sdk js a versión 1.5.8
- Se actualiza sdk php a versión 1.4.0
- Implementa soporte para items de descuentos en el carro

## [1.0.0] - 2018-10-18
### Added
- Primera versión funcional del plugin virtuemart 3.x para Onepay
- Implementa pago
