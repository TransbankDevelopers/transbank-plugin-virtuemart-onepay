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
La primera vez es necesario instalar virtuemart y datos de prueba desde el sitio administrador, ir a http://localhost/administrator e instalar Virtuemart con datos de prueba

### Archivo de logs del plugin

```
./shell
tail -f /logs/onepay-log.log
```
    
Basado en:

[Imagen docker Virtuemart](https://hub.docker.com/r/opentools/docker-virtuemart/)

[Repository Virtuemart](https://github.com/open-tools/docker-virtuemart)

[Imagen docker mysql](https://hub.docker.com/r/library/mysql/)
