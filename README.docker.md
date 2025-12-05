# H5P Service - Docker Setup

Este documento explica cómo ejecutar el servicio H5P usando Docker.

## Requisitos Previos

- Docker Desktop instalado ([Descargar aquí](https://www.docker.com/products/docker-desktop))
- Docker Compose (incluido con Docker Desktop)

## Inicio Rápido

### 1. Construir las imágenes

```bash
cd c:\xampp\htdocs\h5p-service
docker-compose build
```

### 2. Levantar los contenedores

```bash
docker-compose up -d
```

Esto iniciará dos contenedores:
- **h5p-service-web**: Aplicación PHP con Apache (puerto 8080)
- **h5p-service-db**: Base de datos MySQL 8.0 (puerto 3307)

### 3. Verificar que está funcionando

Abre tu navegador en:
- **Página principal**: http://localhost:8080
- **H5P Hub**: http://localhost:8080/hub

## Comandos Útiles

### Ver logs de los contenedores

```bash
# Todos los logs
docker-compose logs

# Solo del contenedor web
docker-compose logs web

# Solo de la base de datos
docker-compose logs db

# Ver logs en tiempo real
docker-compose logs -f
```

### Ver estado de los contenedores

```bash
docker-compose ps
```

### Detener los contenedores

```bash
docker-compose down
```

### Detener y eliminar volúmenes (CUIDADO: borra la base de datos)

```bash
docker-compose down -v
```

### Reiniciar los contenedores

```bash
docker-compose restart
```

### Reconstruir las imágenes (después de cambios)

```bash
docker-compose up -d --build
```

### Acceder a la shell del contenedor web

```bash
docker-compose exec web bash
```

### Acceder a MySQL desde línea de comandos

```bash
docker-compose exec db mysql -u h5p_user -ph5p_password h5p_service
```

## Estructura de Puertos

- **8080**: Aplicación web (HTTP)
- **3307**: MySQL (para evitar conflictos con XAMPP que usa 3306)

## Variables de Entorno

Las variables de entorno están definidas en `docker-compose.yml`:

```yaml
DB_HOST=db
DB_NAME=h5p_service
DB_USER=h5p_user
DB_PASS=h5p_password
```

Puedes modificarlas directamente en el archivo si es necesario.

## Volúmenes

Los siguientes directorios son persistentes:

- `./storage`: Contenido H5P, librerías, archivos temporales
- `db_data`: Datos de MySQL (volumen de Docker)
- `vendor_data`: Dependencias de Composer

## Troubleshooting

### El puerto 8080 ya está en uso

Cambia el puerto en `docker-compose.yml`:

```yaml
ports:
  - "9090:80"  # Cambia 8080 por 9090 u otro puerto
```

### Error de conexión a la base de datos

1. Verifica que el contenedor de MySQL está corriendo:
   ```bash
   docker-compose ps
   ```

2. Revisa los logs del contenedor de base de datos:
   ```bash
   docker-compose logs db
   ```

3. Reinicia los contenedores:
   ```bash
   docker-compose restart
   ```

### Los cambios en el código no se reflejan

Los archivos de `src/`, `public/` y `config/` están montados como volúmenes, por lo que los cambios deberían reflejarse inmediatamente. Si no es así:

```bash
docker-compose restart web
```

### Limpiar todo y empezar de nuevo

```bash
# Detener y eliminar contenedores
docker-compose down

# Eliminar volúmenes (opcional, borra la BD)
docker-compose down -v

# Reconstruir desde cero
docker-compose build --no-cache
docker-compose up -d
```

## Notas Importantes

- **XAMPP sigue funcionando**: Esta configuración Docker no afecta tu instalación XAMPP existente. Usa diferentes puertos.
- **Desarrollo**: Los archivos fuente están montados como volúmenes, así que puedes editar el código y ver los cambios inmediatamente.
- **Persistencia**: Los datos de H5P y la base de datos se mantienen entre reinicios.
- **Producción**: Para producción, considera cambiar `H5P_DEVELOPMENT_MODE=false` y ajustar otras variables de entorno.

## Migración desde XAMPP

Si tienes datos en tu base de datos XAMPP y quieres migrarlos a Docker:

1. Exporta la base de datos de XAMPP:
   ```bash
   mysqldump -u root h5p_service > backup.sql
   ```

2. Importa en el contenedor Docker:
   ```bash
   docker-compose exec -T db mysql -u h5p_user -ph5p_password h5p_service < backup.sql
   ```

## Soporte

Si encuentras problemas, revisa:
1. Los logs con `docker-compose logs`
2. El estado de los contenedores con `docker-compose ps`
3. La documentación oficial de Docker
