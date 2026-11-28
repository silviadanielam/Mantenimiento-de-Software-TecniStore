<?php
// Panel principal del sistema de gestión de inventario TecniStore.
// Cargamos las funciones y comprobamos que el administrador haya iniciado sesión.
require_once __DIR__ . '/funciones.php';
requiere_login();

// Conectamos con MySQL para consultar los productos.
require_once __DIR__ . '/conexion.php';

// Recuperamos los mensajes enviados desde acciones.php.
// Se muestran una sola vez y después se eliminan de la sesión.
$error = $_SESSION['error_inventario'] ?? '';
$errorDuplicado = (bool)($_SESSION['error_duplicado'] ?? false);
$ok = $_SESSION['ok_inventario'] ?? '';
$formularioAnterior = $_SESSION['formulario_anterior'] ?? null;
unset($_SESSION['error_inventario'], $_SESSION['error_duplicado'], $_SESSION['ok_inventario'], $_SESSION['formulario_anterior']);

// HU-04: El formulario lateral se utiliza únicamente para crear productos.
// Si falla una edición, recuperamos los datos para volver a abrir el modal.
$productoVacio = [
    'id' => 0, 'codigo' => '', 'nombre' => '', 'marca' => '',
    'categoria' => 'Accesorios', 'precio' => '', 'stock' => 0
];
$productoNuevo = $productoVacio;
$productoEditar = $productoVacio;
$idAnterior = filter_var($formularioAnterior['id'] ?? 0, FILTER_VALIDATE_INT);
$edicionFallida = is_array($formularioAnterior) && $idAnterior !== false && $idAnterior > 0;

if (is_array($formularioAnterior)) {
    if ($edicionFallida) {
        // El servidor conserva los datos de la edición que no se pudo guardar.
        $productoEditar = array_merge($productoVacio, array_intersect_key($formularioAnterior, $productoVacio));
    } else {
        // Si falló un registro nuevo, recuperamos su formulario lateral.
        $productoNuevo = array_merge($productoVacio, array_intersect_key($formularioAnterior, $productoVacio));
    }
}

// HU-01: Consultamos los productos según la búsqueda y el estado del inventario.
$busqueda = trim((string)($_GET['q'] ?? ''));
$estado = (string)($_GET['estado'] ?? 'todos');

// Aceptamos únicamente los estados definidos para el inventario.
$estadosPermitidos = ['todos', 'disponible', 'bajo', 'agotado'];
if (!in_array($estado, $estadosPermitidos, true)) {
    $estado = 'todos';
}

// Armamos las condiciones de la consulta sin perder la búsqueda existente.
$condiciones = [];
$parametros = [];

if ($busqueda !== '') {
    $condiciones[] = '(codigo LIKE ? OR nombre LIKE ? OR categoria LIKE ?)';
    $termino = '%' . $busqueda . '%';
    $parametros = [$termino, $termino, $termino];
}

// Clasificamos los productos con las mismas reglas de stock del sistema.
if ($estado === 'disponible') {
    $condiciones[] = 'stock > 3';
} elseif ($estado === 'bajo') {
    $condiciones[] = 'stock BETWEEN 1 AND 3';
} elseif ($estado === 'agotado') {
    $condiciones[] = 'stock = 0';
}

// HU-02: Definimos opciones de ordenamiento permitidas.
// No insertamos directamente valores recibidos por GET en ORDER BY.
$orden = (string)($_GET['orden'] ?? 'recientes');
$ordenamientos = [
    'recientes' => 'id DESC',
    'nombre_asc' => 'nombre ASC, id DESC',
    'nombre_desc' => 'nombre DESC, id DESC',
    'precio_asc' => 'precio ASC, id DESC',
    'precio_desc' => 'precio DESC, id DESC',
    'stock_asc' => 'stock ASC, id DESC',
    'stock_desc' => 'stock DESC, id DESC'
];
if (!array_key_exists($orden, $ordenamientos)) {
    $orden = 'recientes';
}

$sql = 'SELECT * FROM productos';
if ($condiciones !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $condiciones);
}
$sql .= ' ORDER BY ' . $ordenamientos[$orden];

// Ejecutamos la consulta parametrizada y guardamos los resultados para el panel.
$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);
$productos = $stmt->fetchAll();

// Calculamos las unidades y el valor del inventario de los productos mostrados.
$totalUnidades = 0;
$valorInventario = 0;
foreach ($productos as $p) {
    $totalUnidades += (int)$p['stock'];
    $valorInventario += (float)$p['precio'] * (int)$p['stock'];
}
// Generamos el token de seguridad que se incluirá en los formularios.
$token = token_csrf();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestión de inventario | TecniStore</title>
  <!-- Cargamos Bootstrap y nuestros estilos personalizados. -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<main class="container-xl py-3 py-md-4">
  <!-- Encabezado del panel y opción para cerrar sesión. -->
  <header class="encabezado p-3 p-md-4 mb-4 shadow-sm d-flex justify-content-between align-items-center gap-3 flex-wrap">
    <div>
      <h1 class="h3 fw-bold mb-1">TecniStore - Gestión de inventario</h1>
      <div class="small text-white-50">Panel administrativo</div>
    </div>
    <!-- HU-05: Acceso al perfil sin alterar el cierre de sesión existente. -->
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <a href="perfil.php" class="btn btn-outline-light btn-sm">Mi perfil</a>
      <form method="POST" action="logout.php" class="mb-0">
        <input type="hidden" name="csrf_token" value="<?= escapar($token) ?>">
        <button class="btn btn-light btn-sm" type="submit">Cerrar sesión</button>
      </form>
    </div>
  </header>

  <!-- Mostramos mensajes de confirmación o error después de una operación. -->
  <?php if ($ok !== ''): ?>
    <div class="alert alert-success alert-dismissible fade show" role="status">
      <?= escapar($ok) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
  <?php endif; ?>
  <?php if (!$edicionFallida && $errorDuplicado): ?>
    <div class="alert alert-danger d-flex align-items-start gap-3" role="alert">
      <span class="icono-alerta-duplicado" aria-hidden="true">!</span>
      <div><strong class="d-block mb-1">Producto ya registrado</strong><?= escapar($error) ?></div>
    </div>
  <?php elseif (!$edicionFallida && $error !== ''): ?>
    <div class="alert alert-danger" role="alert"><?= escapar($error) ?></div>
  <?php endif; ?>

  <!-- Indicadores calculados con los productos visibles, incluso al buscar. -->
  <section class="row row-cols-1 row-cols-md-3 g-3 mb-4" aria-label="Resumen del inventario">
    <div class="col"><div class="indicador bg-white p-3 h-100 shadow-sm">
      <div class="text-secondary small mb-1">Productos listados</div>
      <div class="valor"><?= count($productos) ?></div>
    </div></div>
    <div class="col"><div class="indicador bg-white p-3 h-100 shadow-sm">
      <div class="text-secondary small mb-1">Unidades en stock</div>
      <div class="valor"><?= (int)$totalUnidades ?> uds.</div>
    </div></div>
    <div class="col"><div class="indicador bg-white p-3 h-100 shadow-sm">
      <div class="text-secondary small mb-1">Valor del inventario</div>
      <div class="valor">$<?= number_format($valorInventario, 0, ',', '.') ?></div>
    </div></div>
  </section>

  <div class="row g-4 align-items-start">
    <!-- HU-04: Formulario exclusivo para registrar productos nuevos. -->
    <section class="col-12 col-xl-4" aria-label="Formulario de productos">
      <div class="card tarjeta shadow-sm"><div class="card-body p-3 p-md-4">
        <h2 class="h5 fw-bold mb-3">Nuevo producto</h2>
        <!-- Enviamos los datos a acciones.php para guardarlos en MySQL. -->
        <form method="POST" action="acciones.php">
          <!-- Enviamos el token y la acción solicitada con el formulario. -->
          <input type="hidden" name="csrf_token" value="<?= escapar($token) ?>">
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="id" value="0">

          <div class="mb-3">
            <label for="codigo" class="form-label fw-semibold">Código del producto (SKU)</label>
            <input type="text" id="codigo" name="codigo" class="form-control" minlength="3" maxlength="30"
                   pattern="[A-Za-z0-9-]{3,30}" placeholder="Ej.: TEC-001" required
                   value="<?= escapar($productoNuevo['codigo']) ?>">
            <div class="form-text">Debe ser único. Utiliza letras, números o guiones.</div>
          </div>
          <div class="mb-3">
            <label for="nombre" class="form-label fw-semibold">Nombre del producto</label>
            <input type="text" id="nombre" name="nombre" class="form-control" minlength="3" maxlength="120" required value="<?= escapar($productoNuevo['nombre']) ?>">
          </div>
          <div class="mb-3">
            <label for="marca" class="form-label fw-semibold">Marca</label>
            <input type="text" id="marca" name="marca" class="form-control" maxlength="60" required value="<?= escapar($productoNuevo['marca']) ?>">
          </div>
          <div class="mb-3">
            <label for="categoria" class="form-label fw-semibold">Categoría</label>
            <select id="categoria" name="categoria" class="form-select" required>
              <?php foreach (categorias() as $cat): ?>
                <option value="<?= escapar($cat) ?>" <?= $productoNuevo['categoria'] === $cat ? 'selected' : '' ?>><?= escapar($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label for="precio" class="form-label fw-semibold">Precio unitario ($ COP)</label>
            <input type="number" id="precio" name="precio" class="form-control" min="1000" max="99999999.99" step="0.01" required value="<?= escapar($productoNuevo['precio']) ?>">
          </div>
          <div class="mb-3">
            <label for="stock" class="form-label fw-semibold">Stock (unidades)</label>
            <input type="number" id="stock" name="stock" class="form-control" min="0" max="2147483647" step="1" required value="<?= escapar($productoNuevo['stock']) ?>">
          </div>
          <button type="submit" class="btn btn-primary w-100">Agregar al inventario</button>
        </form>
      </div></div>
    </section>

    <!-- Consulta del inventario: búsqueda y listado adaptable a cada pantalla. -->
    <section class="col-12 col-xl-8" aria-label="Lista de productos">
      <div class="card tarjeta shadow-sm"><div class="card-body p-3 p-md-4">
        <!-- HU-03: Permite descargar una copia completa del inventario en formato CSV. -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <h2 class="h5 fw-bold mb-0">Productos del inventario</h2>
          <a href="exportar_csv.php" class="btn btn-outline-success btn-sm">Exportar inventario CSV</a>
        </div>
        <!-- HU-01: Búsqueda y filtro por estado sin modificar la base de datos. -->
        <form method="GET" action="index.php" class="row g-2 mb-3">
          <div class="col-12 col-md">
            <label for="busqueda" class="visually-hidden">Buscar productos</label>
            <input type="search" id="busqueda" name="q" class="form-control"
                   placeholder="Buscar por código, nombre o categoría..." value="<?= escapar($busqueda) ?>">
          </div>
          <div class="col-12 col-md-auto">
            <label for="estado" class="visually-hidden">Estado del inventario</label>
            <select id="estado" name="estado" class="form-select" aria-label="Filtrar por estado del inventario"
                    onchange="this.form.requestSubmit()">
              <option value="todos" <?= $estado === 'todos' ? 'selected' : '' ?>>Todos los productos</option>
              <option value="disponible" <?= $estado === 'disponible' ? 'selected' : '' ?>>Disponibles</option>
              <option value="bajo" <?= $estado === 'bajo' ? 'selected' : '' ?>>Inventario bajo</option>
              <option value="agotado" <?= $estado === 'agotado' ? 'selected' : '' ?>>Agotados</option>
            </select>
          </div>
          <!-- HU-02: Ordenar sin perder la búsqueda ni el filtro por estado. -->
          <div class="col-12 col-md-auto">
            <label for="orden" class="visually-hidden">Ordenar productos</label>
            <select id="orden" name="orden" class="form-select" aria-label="Ordenar productos"
                    onchange="this.form.requestSubmit()">
              <option value="recientes" <?= $orden === 'recientes' ? 'selected' : '' ?>>Más recientes</option>
              <option value="nombre_asc" <?= $orden === 'nombre_asc' ? 'selected' : '' ?>>Nombre: A a Z</option>
              <option value="nombre_desc" <?= $orden === 'nombre_desc' ? 'selected' : '' ?>>Nombre: Z a A</option>
              <option value="precio_asc" <?= $orden === 'precio_asc' ? 'selected' : '' ?>>Precio: menor a mayor</option>
              <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Precio: mayor a menor</option>
              <option value="stock_asc" <?= $orden === 'stock_asc' ? 'selected' : '' ?>>Stock: menor a mayor</option>
              <option value="stock_desc" <?= $orden === 'stock_desc' ? 'selected' : '' ?>>Stock: mayor a menor</option>
            </select>
          </div>
          <div class="col-12 col-md-auto d-grid">
            <button type="submit" class="btn btn-primary">Buscar</button>
          </div>
          <?php if ($busqueda !== '' || $estado !== 'todos' || $orden !== 'recientes'): ?>
            <div class="col-12 col-md-auto d-grid">
              <a href="index.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
          <?php endif; ?>
        </form>

        <!-- Si no hay resultados, mostramos un aviso en lugar del listado. -->
        <?php if (count($productos) === 0): ?>
          <div class="alert alert-light border mb-0">No se encontraron productos.</div>
        <?php else: ?>
          <!-- En pantallas grandes, mostramos los productos en una tabla. -->
          <div class="table-responsive d-none d-xl-block">
            <table class="table table-hover table-striped align-middle tabla-productos mb-0">
              <thead class="table-light"><tr>
                <th scope="col">Código</th><th scope="col">Producto</th><th scope="col">Marca</th>
                <th scope="col">Categoría</th><th scope="col">Precio</th><th scope="col">Stock</th><th scope="col">Acciones</th>
              </tr></thead>
              <tbody>
                <?php foreach ($productos as $p): ?>
                  <tr>
                    <td class="small fw-semibold text-nowrap"><?= escapar($p['codigo']) ?></td>
                    <td class="fw-semibold nombre-producto"><?= escapar($p['nombre']) ?></td>
                    <td><?= escapar($p['marca']) ?></td>
                    <td><?= escapar($p['categoria']) ?></td>
                    <td>$<?= number_format((float)$p['precio'], 0, ',', '.') ?></td>
                    <td><span class="badge <?= clase_stock((int)$p['stock']) ?>"><?= escapar(texto_stock((int)$p['stock'])) ?></span></td>
                    <!-- Botones para editar, ajustar existencias o eliminar el producto. -->
                    <td><div class="acciones">
                      <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#modalEditarProducto"
                        data-id="<?= (int)$p['id'] ?>"
                        data-codigo="<?= escapar($p['codigo']) ?>"
                        data-nombre="<?= escapar($p['nombre']) ?>"
                        data-marca="<?= escapar($p['marca']) ?>"
                        data-categoria="<?= escapar($p['categoria']) ?>"
                        data-precio="<?= escapar($p['precio']) ?>"
                        data-stock="<?= (int)$p['stock'] ?>"
                        aria-label="Editar <?= escapar($p['nombre']) ?>">Editar</button>
                      <form method="POST" action="acciones.php">
                        <input type="hidden" name="csrf_token" value="<?= escapar($token) ?>">
                        <input type="hidden" name="accion" value="sumar_stock">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success boton-accion" aria-label="Sumar una unidad a <?= escapar($p['nombre']) ?>">+1</button>
                      </form>
                      <form method="POST" action="acciones.php">
                        <input type="hidden" name="csrf_token" value="<?= escapar($token) ?>">
                        <input type="hidden" name="accion" value="restar_stock">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary boton-accion" aria-label="Restar una unidad a <?= escapar($p['nombre']) ?>" <?= (int)$p['stock'] === 0 ? 'disabled' : '' ?>>-1</button>
                      </form>
                      <form method="POST" action="acciones.php" onsubmit="return confirm('¿Seguro que deseas eliminar este producto?');">
                        <input type="hidden" name="csrf_token" value="<?= escapar($token) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Borrar</button>
                      </form>
                    </div></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- En celulares y pantallas más pequeñas, usamos tarjetas por producto. -->
          <div class="d-xl-none">
            <?php foreach ($productos as $p): ?>
              <article class="border rounded-3 p-3 mb-3 bg-white">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                  <div class="min-w-0">
                    <div class="small text-secondary">Código: <?= escapar($p['codigo']) ?></div>
                    <h3 class="h6 fw-bold mb-1 nombre-producto"><?= escapar($p['nombre']) ?></h3>
                  </div>
                  <span class="badge <?= clase_stock((int)$p['stock']) ?> text-wrap"><?= escapar(texto_stock((int)$p['stock'])) ?></span>
                </div>
                <dl class="row g-1 mb-3 small">
                  <dt class="col-4 text-secondary fw-normal">Marca</dt><dd class="col-8 mb-0 nombre-producto"><?= escapar($p['marca']) ?></dd>
                  <dt class="col-4 text-secondary fw-normal">Categoría</dt><dd class="col-8 mb-0"><?= escapar($p['categoria']) ?></dd>
                  <dt class="col-4 text-secondary fw-normal">Precio</dt><dd class="col-8 mb-0 fw-bold">$<?= number_format((float)$p['precio'], 0, ',', '.') ?></dd>
                </dl>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-sm btn-outline-primary"
                        data-bs-toggle="modal" data-bs-target="#modalEditarProducto"
                        data-id="<?= (int)$p['id'] ?>"
                        data-codigo="<?= escapar($p['codigo']) ?>"
                        data-nombre="<?= escapar($p['nombre']) ?>"
                        data-marca="<?= escapar($p['marca']) ?>"
                        data-categoria="<?= escapar($p['categoria']) ?>"
                        data-precio="<?= escapar($p['precio']) ?>"
                        data-stock="<?= (int)$p['stock'] ?>"
                        aria-label="Editar <?= escapar($p['nombre']) ?>">Editar</button>
                  <form method="POST" action="acciones.php">
                    <input type="hidden" name="csrf_token" value="<?= escapar($token) ?>">
                    <input type="hidden" name="accion" value="sumar_stock">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-success">+1</button>
                  </form>
                  <form method="POST" action="acciones.php">
                    <input type="hidden" name="csrf_token" value="<?= escapar($token) ?>">
                    <input type="hidden" name="accion" value="restar_stock">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" <?= (int)$p['stock'] === 0 ? 'disabled' : '' ?>>-1</button>
                  </form>
                  <form method="POST" action="acciones.php" onsubmit="return confirm('¿Seguro que deseas eliminar este producto?');">
                    <input type="hidden" name="csrf_token" value="<?= escapar($token) ?>">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Borrar</button>
                  </form>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div></div>
    </section>
  </div>
  <footer class="text-center text-secondary small py-4">TecniStore - Proyecto académico de gestión de inventario</footer>
</main>
<!-- HU-04: Una única ventana modal permite editar cualquier producto del listado. -->
<div class="modal fade" id="modalEditarProducto" tabindex="-1"
     aria-labelledby="tituloModalEditar" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title fs-5 fw-bold" id="tituloModalEditar">Editar producto</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form method="POST" action="acciones.php" id="formularioEdicion">
        <div class="modal-body">
          <?php if ($edicionFallida && $error !== ''): ?>
            <div class="alert alert-danger" role="alert" id="errorModalEdicion">
              <?= escapar($error) ?>
            </div>
          <?php endif; ?>
          <input type="hidden" name="csrf_token" value="<?= escapar($token) ?>">
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="id" value="<?= (int)$productoEditar['id'] ?>">

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label for="editar_codigo" class="form-label fw-semibold">Código del producto (SKU)</label>
              <input type="text" id="editar_codigo" name="codigo" class="form-control"
                     minlength="3" maxlength="30" pattern="[A-Za-z0-9-]{3,30}"
                     required value="<?= escapar($productoEditar['codigo']) ?>">
              <div class="form-text">Debe ser único. Letras, números o guiones.</div>
            </div>
            <div class="col-12 col-md-6">
              <label for="editar_nombre" class="form-label fw-semibold">Nombre del producto</label>
              <input type="text" id="editar_nombre" name="nombre" class="form-control"
                     minlength="3" maxlength="120" required value="<?= escapar($productoEditar['nombre']) ?>">
            </div>
            <div class="col-12 col-md-6">
              <label for="editar_marca" class="form-label fw-semibold">Marca</label>
              <input type="text" id="editar_marca" name="marca" class="form-control"
                     maxlength="60" required value="<?= escapar($productoEditar['marca']) ?>">
            </div>
            <div class="col-12 col-md-6">
              <label for="editar_categoria" class="form-label fw-semibold">Categoría</label>
              <select id="editar_categoria" name="categoria" class="form-select" required>
                <?php foreach (categorias() as $cat): ?>
                  <option value="<?= escapar($cat) ?>"
                          <?= $productoEditar['categoria'] === $cat ? 'selected' : '' ?>><?= escapar($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label for="editar_precio" class="form-label fw-semibold">Precio unitario ($ COP)</label>
              <input type="number" id="editar_precio" name="precio" class="form-control"
                     min="1000" max="99999999.99" step="0.01"
                     required value="<?= escapar($productoEditar['precio']) ?>">
            </div>
            <div class="col-12 col-md-6">
              <label for="editar_stock" class="form-label fw-semibold">Stock (unidades)</label>
              <input type="number" id="editar_stock" name="stock" class="form-control"
                     min="0" max="2147483647" step="1"
                     required value="<?= escapar($productoEditar['stock']) ?>">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Cargamos los componentes interactivos de Bootstrap, como el cierre de alertas. -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
// HU-04: Al pulsar Editar, cargamos el producto en el formulario del modal.
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('modalEditarProducto');
  const formulario = document.getElementById('formularioEdicion');

  modal.addEventListener('show.bs.modal', function (evento) {
    const boton = evento.relatedTarget;
    if (!boton) return; // Sin botón: reapertura automática tras un error.

    const datos = boton.dataset;
    const campos = ['id', 'codigo', 'nombre', 'marca', 'categoria', 'precio', 'stock'];
    campos.forEach(function (campo) {
      const input = formulario.elements.namedItem(campo);
      if (input) input.value = datos[campo] ?? '';
    });

    // Si se intenta editar otro producto, ocultamos un error anterior.
    const errorPrevio = document.getElementById('errorModalEdicion');
    if (errorPrevio) errorPrevio.classList.add('d-none');
  });

  <?php if ($edicionFallida): ?>
  // Si una validación del servidor falló, recuperamos el formulario y el mensaje.
  bootstrap.Modal.getOrCreateInstance(modal).show();
  <?php endif; ?>
});
</script>
</body>
</html>