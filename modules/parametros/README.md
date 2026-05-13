# Sección de Parámetros - Gestión de Equipos RGE

Esta sección permite a los administradores gestionar los parámetros del sistema que son utilizados en toda la aplicación.

## Acceso

Esta sección solo está disponible para usuarios con tipo de usuario "Administrador" (user_type = 1).

## Módulos Disponibles

### 1. Información de la Empresa (`empresa.php`)
- **Propósito**: Gestionar la información general de la empresa
- **Campos**:
  - Nombre de la empresa
  - Slogan
  - Leyenda 1 (usada en tickets/comprobantes)
  - Leyenda 2 (usada en tickets/comprobantes)
  - IVA (porcentaje de impuesto)

### 2. Estados de Orden (`estados_orden.php`)
- **Propósito**: Gestionar los estados posibles para las órdenes de trabajo
- **Campos**:
  - Nombre del estado
  - Descripción
  - Color (para identificación visual)
- **Estados por defecto**:
  - Pendiente (amarillo)
  - En Proceso (azul)
  - Completado (verde)
  - Entregado (verde oscuro)
  - Cancelado (rojo)

### 3. Tipos de Equipo (`tipos_equipo.php`)
- **Propósito**: Gestionar las categorías de equipos que se pueden registrar
- **Campos**:
  - Nombre del tipo de equipo
- **Tipos por defecto**:
  - Laptop
  - Desktop
  - Impresora
  - Monitor
  - Otro

### 4. Tipos de Servicio (`tipos_servicio.php`)
- **Propósito**: Gestionar los tipos de servicios que ofrece la empresa
- **Campos**:
  - Nombre del servicio
  - Descripción
- **Servicios por defecto**:
  - Mantenimiento Preventivo
  - Reparación Hardware
  - Actualización Software

## Funcionalidades

### CRUD Completo
Cada módulo incluye:
- **Crear**: Agregar nuevos registros
- **Leer**: Ver lista de registros existentes
- **Actualizar**: Editar registros existentes
- **Eliminar**: Desactivar registros (borrado lógico)

### Características
- **Validación de permisos**: Solo administradores pueden acceder
- **Notificaciones**: Mensajes de éxito y error
- **Confirmaciones**: Diálogos de confirmación para eliminaciones
- **Responsive**: Interfaz adaptada para móviles y desktop
- **Navegación intuitiva**: Botones de volver y navegación clara

## Instalación

### 1. Crear tabla orden_estados
Ejecutar el script SQL ubicado en:
```
database/crear_tabla_orden_estados.sql
```

### 2. Verificar permisos
Asegurarse de que el usuario administrador tenga `user_type = 1` en la tabla `usuarios`.

## Uso

1. **Acceder como administrador** al sistema
2. **Navegar al menú lateral** y buscar la sección "Parámetros"
3. **Seleccionar el módulo** deseado
4. **Gestionar los registros** según sea necesario

## Notas Importantes

- Los cambios en los parámetros afectan inmediatamente a toda la aplicación
- Se recomienda hacer respaldo antes de realizar cambios importantes
- Los registros eliminados se marcan como inactivos (estado = 0) pero no se borran físicamente
- La información de la empresa se usa en los tickets y comprobantes generados por el sistema 