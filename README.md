# TecniStore – Sistema Web de Gestión de Inventario

TecniStore es un sistema web de gestión de inventario desarrollado con PHP, MySQL, HTML5, CSS3 y Bootstrap 5 para una tienda ficticia de productos tecnológicos.

El sistema fue desarrollado inicialmente como proyecto de la asignatura Electiva: Desarrollo de Aplicaciones Web y posteriormente utilizado en Mantenimiento de Software para implementar cinco mejoras funcionales mediante Git y GitHub.

## Funcionalidades

- Inicio y cierre de sesión del administrador.
- Registro, consulta, edición y eliminación de productos.
- Administración de código SKU, nombre, marca, categoría, precio y stock.
- Búsqueda de productos por código, nombre o categoría.
- Incremento y disminución de existencias mediante botones +1 y -1.
- Indicadores generales del inventario y alertas visuales de disponibilidad.
- Validaciones de datos y mensajes de confirmación o error.
- Interfaz adaptable a computadores y dispositivos móviles.

### Mejoras implementadas en Mantenimiento de Software

- **HU-01:** Filtrado de productos por estado del inventario: disponibles, inventario bajo y agotados.
- **HU-02:** Ordenamiento de productos por nombre, precio y stock, de forma ascendente o descendente.
- **HU-03:** Exportación del inventario completo a un archivo CSV compatible con Microsoft Excel.
- **HU-04:** Edición de productos mediante una ventana emergente (modal) de Bootstrap.
- **HU-05:** Consulta del perfil del administrador y cambio de contraseña con validaciones de seguridad.

## Tecnologías utilizadas

- **Frontend:** HTML5, CSS3 y Bootstrap 5.
- **Backend:** PHP.
- **Base de datos:** MySQL mediante PDO.
- **Entorno local:** Laragon y HeidiSQL.
- **Control de versiones:** Git y GitHub.

## Control de versiones y mantenimiento

Para desarrollar las cinco historias de usuario se utilizó una organización de ramas en Git:

- `main`: rama principal del proyecto.
- `develop`: rama de integración de las mejoras.
- `hu-01-filtro-estado`: implementación del filtro por estado.
- `hu-02-ordenamiento`: implementación del ordenamiento.
- `hu-03-exportacion-csv`: implementación de la exportación.
- `hu-04-edicion-modal`: implementación de la edición mediante modal.
- `hu-05-perfil-administrador`: implementación del perfil y cambio de contraseña.

Cada historia de usuario se registró en GitHub Issues y se desarrolló en una rama independiente. Los cambios se documentaron mediante commits, se verificaron mediante pruebas funcionales y se integraron a `develop` a través de pull requests.

Finalmente, las cinco mejoras se integraron en `main`, después de realizar pruebas de regresión para comprobar el funcionamiento conjunto del sistema.

## Archivos principales

- `index.php`: panel de inventario, registro de productos, búsqueda, filtros, ordenamiento e interfaz de edición.
- `acciones.php`: procesamiento de operaciones sobre productos y existencias.
- `exportar_csv.php`: generación y descarga del inventario en formato CSV.
- `perfil.php`: consulta del perfil del administrador y cambio de contraseña.
- `login.php` y `logout.php`: inicio y cierre de sesión.
- `funciones.php`: sesiones, validaciones y funciones compartidas.
- `conexion.php` y `config.local.php`: configuración de conexión a MySQL.
- `configurar_admin.php`: creación inicial de la cuenta de administrador.
- `crear_admin_cli.php`: alternativa para crear la cuenta desde la terminal.
- `database.sql`: estructura de las tablas de productos y administradores.
- `assets/css/estilos.css`: estilos personalizados.

## Instalación y ejecución local

1. Instalar Laragon e iniciar Apache y MySQL.
2. Clonar o descargar el repositorio dentro de `C:\laragon\www`.
3. Importar el archivo `database.sql` mediante HeidiSQL o phpMyAdmin.
4. Verificar los datos de conexión en `config.local.php`, según la configuración local de MySQL.
5. Acceder desde localhost a `configurar_admin.php` para crear la cuenta inicial.
6. Registrar un correo electrónico y una contraseña de mínimo diez caracteres.
7. Retirar el acceso al script de configuración después de crear la cuenta.
8. Ingresar al sistema mediante `login.php` con las credenciales registradas.

La base de datos proporcionada contiene la estructura necesaria para ejecutar el sistema. Cada usuario puede crear sus propios registros para realizar pruebas.

## Seguridad

TecniStore utiliza sesiones PHP para restringir el acceso al administrador, consultas preparadas mediante PDO y validaciones de los datos ingresados.

Las contraseñas se almacenan mediante hash y su modificación requiere verificar la contraseña actual. El sistema también incorpora protección CSRF y restricciones para evitar códigos SKU duplicados o cantidades negativas.

## Aplicación publicada

**https://tecnistore.infinityfree.io/login.php**

Este enlace corresponde al despliegue web realizado durante el desarrollo inicial de TecniStore. Las cinco mejoras de la actividad de Mantenimiento de Software fueron implementadas y verificadas en el entorno local y están disponibles en este repositorio de GitHub.

## Resultados del mantenimiento

Se implementaron cinco historias de usuario y se realizaron pruebas funcionales y de regresión para verificar las mejoras incorporadas.

El repositorio conserva el historial de commits, las ramas y los pull requests utilizados durante el proceso, permitiendo consultar los cambios realizados desde la versión inicial hasta la versión final del sistema.
