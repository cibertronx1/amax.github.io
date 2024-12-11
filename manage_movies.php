


<?php
session_start();

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "amax";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // Habilitar excepciones para errores de PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != '1') {
   header('Location: login.php');
   exit;
}

// Configuración de paginación
$limit = 50; // Limite de registros por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Búsqueda
$search_query = isset($_GET['titulo']) ? trim($_GET['titulo']) : '';

// Consulta base
$sql_base = "FROM movies";
$where = "";
$params = [];

// Si hay búsqueda
if (!empty($search_query)) {
    if (is_numeric($search_query)) {
        // Buscar por TMDB_ID exacto o por titulo similar
        $where = "WHERE tmdb_id = :search OR titulo LIKE :search_like";
        $params[':search'] = $search_query;
        $params[':search_like'] = "%$search_query%";
    } else {
        // Buscar por el título de la película
        $where = "WHERE titulo LIKE :search";
        $params[':search'] = "%$search_query%";
    }
}

// Contar total de resultados (filtrados o no)
$stmt_total = $conn->prepare("SELECT COUNT(*) $sql_base $where");
$stmt_total->execute($params);
$total_movies = $stmt_total->fetchColumn();

$total_pages = ($total_movies > 0) ? ceil($total_movies / $limit) : 1;

// Obtener las películas según la búsqueda y paginación
if (!empty($search_query)) {
    if (is_numeric($search_query)) {
        $sql = "SELECT * $sql_base $where ORDER BY release_date DESC LIMIT :limit OFFSET :offset";
    } else {
        $sql = "SELECT * $sql_base $where ORDER BY release_date DESC LIMIT :limit OFFSET :offset";
    }
} else {
    // Sin búsqueda, todas las películas
    $sql = "SELECT * $sql_base ORDER BY titulo DESC LIMIT :limit OFFSET :offset";
}

$stmt = $conn->prepare($sql);

// Vincular parámetros de búsqueda si hay
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}

// Vincular parámetros de paginación
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Películas - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
        }
        .sidebar {
            background: linear-gradient(to bottom right, #1e40af, #7e22ce);
        }
        .table-hover:hover {
            background-color: rgba(45, 55, 72, 0.6);
            transition: background-color 0.3s ease;
        }
    </style>
</head>
<body class="antialiased">
    <div class="flex min-h-screen">
        <div class="w-64 bg-slate-900 shadow-xl">
            <div class="p-6">
                <div class="flex items-center justify-center mb-8">
                    <div class="relative">
                        <div class="w-20 h-20 bg-gradient-to-tr from-blue-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                            <i class="fas fa-tv text-3xl text-white"></i>
                        </div>
                    </div>
                </div>
                <h2 class="text-xl font-bold text-center mb-8 text-gray-200">Admin Panel</h2>
                <nav>
                    <ul class="space-y-2">
                        <li><a href="admin_panel.php" class="flex items-center space-x-3 text-gray-300 p-3 rounded-lg"><i class="fas fa-home w-5"></i><span>Dashboard</span></a></li>
                        <li><a href="manage_movies.php" class="flex items-center space-x-3 text-gray-300 p-3 rounded-lg bg-gray-700"><i class="fas fa-film w-5"></i><span>Gestionar Películas</span></a></li>
                        <li><a href="manage_series.php" class="flex items-center space-x-3 text-gray-300 p-3 rounded-lg"><i class="fas fa-video w-5"></i><span>Gestionar Series Web</span></a></li>
                        <li><a href="manage_users.php" class="flex items-center space-x-3 text-gray-300 p-3 rounded-lg"><i class="fas fa-users w-5"></i><span>Gestionar Usuarios</span></a></li>
                        <li class="mt-8"><a href="logout.php" class="flex items-center space-x-3 text-red-400 p-3 rounded-lg"><i class="fas fa-sign-out-alt w-5"></i><span>Cerrar Sesión</span></a></li>
                    </ul>
                </nav>
            </div>
        </div>

        <div class="flex-1 p-8">
            <div class="max-w-6xl mx-auto">
                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent ">Gestionar Películas</h1>
                    
                    <!-- Buscador -->
                    <div class="flex items-center space-x-4">
                        <form method="GET" action="manage_movies.php" class="flex items-center">
                            <input type="search" name="titulo" 
                                   placeholder="Buscar película..." 
                                   value="<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>"
                                   class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit" class="ml-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">Buscar</button>
                        </form>
                        <?php if (!empty($search_query)): ?>
                            <!-- Botón para limpiar la búsqueda -->
                            <a href="manage_movies.php" class="ml-2 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">Limpiar</a>
                        <?php endif; ?>
                    </div>

                    <a href="add_movie.php" class="flex items-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i data-feather="plus" class="mr-2"></i>Agregar Nueva Película
                    </a>
                </div>

                <div class="bg-gray-800 rounded-lg overflow-hidden shadow-xl">
                    <table class="w-full">
                        <thead class="bg-gray-700 text-gray-300">
                            <tr>
                                <th class="p-4 text-left">TMDB_ID</th>
                                <th class="p-4 text-left">Nombre</th>
                                <th class="p-4 text-left">Fecha de Lanzamiento</th>
                                <th class="p-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($movies)): ?>
                                <?php foreach ($movies as $movie): ?>
                                    <?php if (isset($movie['id']) && !empty($movie['id'])): ?>
                                        <tr class="border-b border-gray-700 table-hover">
                                            <td class="p-4"><?php echo htmlspecialchars($movie['tmdb_id']); ?></td>
                                            <td class="p-4"><?php echo htmlspecialchars($movie['titulo']); ?></td>
                                            <td class="p-4"><?php echo htmlspecialchars($movie['release_date']); ?></td>
                                            <td class="p-4 flex justify-center space-x-3">
                                                <a href="movie_play_links.php?tmdb_id=<?php echo htmlspecialchars($movie['tmdb_id']); ?>" class="text-blue-400 hover:text-blue-300"><i data-feather="link"></i></a>
                                                <a href="edit_movie.php?tmdb_id=<?php echo htmlspecialchars($movie['tmdb_id']); ?>" class="text-green-400 hover:text-green-300"><i data-feather="edit"></i></a>
                                                <a href="delete_movie.php?id=<?php echo htmlspecialchars($movie['id']); ?>" class="text-red-400 hover:text-red-300"><i data-feather="trash-2"></i></a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center text-gray-400 p-4">No se encontraron películas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div class="mt-6 flex justify-between items-center text-gray-400">
                    <div>
                        Página <?php echo $page; ?> de <?php echo ($total_pages > 0) ? $total_pages : 1; ?>
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="px-4 py-2 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600 transition-colors">Anterior</a>
                        <?php endif; ?>
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Siguiente</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Feather Icons
        feather.replace();
    </script>
</body>
</html>
