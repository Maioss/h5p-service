# Guía de Inicio Rápido - Docker Desktop

## ⚠️ Antes de Continuar

Docker Desktop debe estar en ejecución para poder usar los comandos de Docker.

### Iniciar Docker Desktop

1. **Busca "Docker Desktop" en el menú de inicio de Windows**
2. **Haz clic en el icono de Docker Desktop**
3. **Espera a que el icono de Docker en la bandeja del sistema muestre que está corriendo**
   - El icono pasará de un estado de carga a un estado estable
   - Puede tardar 1-2 minutos en iniciar completamente

### Verificar que Docker está Corriendo

Abre PowerShell o CMD y ejecuta:

```bash
docker ps
```

Si ves una tabla (aunque esté vacía), Docker está funcionando correctamente.

## Comandos para Levantar el Proyecto

Una vez Docker Desktop esté corriendo:

### 1. Navegar al directorio del proyecto

```bash
cd c:\xampp\htdocs\h5p-service
```

### 2. Construir las imágenes

```bash
docker-compose build
```

⏱️ Este proceso puede tardar 3-5 minutos la primera vez.

### 3. Iniciar los contenedores

```bash
docker-compose up -d
```

### 4. Verificar que están corriendo

```bash
docker-compose ps
```

Deberías ver dos contenedores:
- `h5p-service-web` (Estado: Up)
- `h5p-service-db` (Estado: Up)

### 5. Abrir en el navegador

- Página principal: http://localhost:8080
- H5P Hub: http://localhost:8080/hub

## ¿Problemas?

### Docker Desktop no inicia

- Asegúrate de tener WSL 2 instalado (si estás en Windows)
- Reinicia tu computadora
- Verifica que la virtualización esté habilitada en la BIOS

### El puerto 8080 ya está en uso

Edita `docker-compose.yml` y cambia el puerto:

```yaml
ports:
  - "9090:80"  # Cambia 8080 por otro puerto
```

Para más detalles, consulta [README.docker.md](README.docker.md)
