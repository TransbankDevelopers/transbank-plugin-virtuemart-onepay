# Transbank Virtuemart 3.x Onepay Plugin

## Descripción

Este plugin de Virtuemart 3.x implementa el [SDK PHP de Onepay](https://github.com/TransbankDevelopers/transbank-sdk-php) en modalidad checkout. 

## Dependencias

* transbank/transbank-sdk
* fpdf

## Nota  
- La versión del sdk de php se encuentra en el archivo `config.sh`
- La versión del sdk de javascript se encuentra en el archivo `src/transbank_onepay/library/TransbankSdkOnepay.php`

## Preparar el proyecto para bajar dependencias

    ./config.sh

## Crear una versión del plugin empaquetado 

    ./package.sh

## Desarrollo

Para apoyar el levantamiento rápido de un ambiente de desarrollo, hemos creado la especificación de contenedores a través de Docker Compose.

Para usarlo seguir el siguiente [README Virtuemart 3.x](./docker-virtuemart3)

## Instalación del plugin para un comercio

El manual de instalación para el usuario final se encuentra disponible [acá](docs/INSTALLATION.md) o en PDF [acá](https://github.com/TransbankDevelopers/transbank-plugin-virtuemart-onepay/raw/master/docs/INSTALLATION.pdf
)
