![Virtuemart](https://virtuemart.net/images/banners/vm-logo-220.png)

#  Virtuemart Docker para desarrollo

### PHP 7.2 + Mysql 5.5 + Virtuemart 3.2

### Requerimientos

**MacOS:**

Instalar [Docker](https://docs.docker.com/docker-for-mac/install/), [Docker-compose](https://docs.docker.com/compose/install/#install-compose) y [Docker-sync](https://github.com/EugenMayer/docker-sync/wiki/docker-sync-on-OSX).

**Windows:**

Instalar [Docker](https://docs.docker.com/docker-for-windows/install/), [Docker-compose](https://docs.docker.com/compose/install/#install-compose) y [Docker-sync](https://github.com/EugenMayer/docker-sync/wiki/docker-sync-on-Windows).

**Linux:**

Instalar [Docker](https://docs.docker.com/engine/installation/linux/docker-ce/ubuntu/) y [Docker-compose](https://docs.docker.com/compose/install/#install-compose).

### Como usar

Para instalar Virtuemart, hacer lo siguiente:

```
./start
./shell
```

### Paneles

**Web server:** http://localhost/

**Admin:** http://localhost/administrator

    user: admin
    password: password

### Importante
La primera vez es necesario instalar virtuemart y datos de prueba desde el sitio administrador, ir a http://localhost/administrator e instalar Virtuemart con datos de prueba.

![paso 1](img/paso1.png)

![paso 2](img/paso2.png)

Debes habilitar el registro de usuarios clickeando "Manage" bajo el menú "Users" y una vez que has ingresado presionar el botón [Options].

![paso 3](img/paso3.png)

- Allow User registration: **Yes**

Luego presionar el botón [Save] para guardar los cambios.

![paso 4](img/paso4.png)

### Configurar moneda Chilena

Ir a (VirtueMart / Shop) y en sección "Currency" elegir "Chilean Peso", luego presionar el botón [Save] para guardar los cambios.

![moneda 1](img/moneda1.png)

Ir al menu izquierdo (Configuration / Currencies) y seleccionar "Chilean peso"

![moneda 2](img/moneda2.png)

Dejar los valores como se muestran en la siguiente imagen.

- Decimals: 0

Luego presionar el botón [Save] para guardar los cambios.

![moneda 3](img/moneda3.png)

## Extras

### Habilitar y activar usuario registrado para pruebas

Si registras un usuario de prueba para el comercio, luego de registrarlo deberás habilitarlo y activarlo en la sección de usuarios.

![user 1](img/user1.png)

Enabled: Checked  
Activated: Checked

![user 2](img/user2.png)

### Configurar error de permisos

Si en algún momento en cualquier pantalla de Joomla aparece el siguiente error, seguir mediante "setup wizard" para corregir.

![paso 5](img/paso5.png)

Presionar el botón con el texto "Create and configure safepath using the administrator com_virtuemart folder"

![paso 6](img/paso6.png)

Aceptar el diálogo presionando "Ok"

![paso 7](img/paso7.png)

Mostrará que ha sido ejecutado correctamente.

![paso 8](img/paso8.png)

### Archivo de logs del plugin

```
./shell
tail -f /var/www/html/administrator/logs/onepay-log.log.php
```
    
Basado en:

[Imagen docker Virtuemart](https://hub.docker.com/r/opentools/docker-virtuemart/)

[Repository Virtuemart](https://github.com/open-tools/docker-virtuemart)

[Imagen docker mysql](https://hub.docker.com/r/library/mysql/)
