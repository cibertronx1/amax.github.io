<?php
session_start();

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "amax";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != '1') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tmdb_id = $_POST['tmdb_id'];
    $apiKey = "f0d20520594c7f22a84bd84472f00297";

    // Función para insertar una película dada su TMDB ID
    function insertarPelicula($movie_id, $conn, $apiKey) {
        // Verificar si el TMDB_ID ya existe en la base de datos
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM movies WHERE tmdb_id = :tmdb_id");
        $stmt_check->bindParam(':tmdb_id', $movie_id, PDO::PARAM_INT);
        $stmt_check->execute();
        $count = $stmt_check->fetchColumn();

        if ($count > 0) {
            // Ya existe la película
            return;
        }

        $movieUrl = "https://api.themoviedb.org/3/movie/$movie_id?api_key=$apiKey&language=es";
        $response = @file_get_contents($movieUrl);
        if ($response === false) {
            // Si no encuentra la película, simplemente salir
            return;
        }

        $movieData = json_decode($response, true);
        if (isset($movieData['status_code'])) {
            return; // No se encontró la película
        }

        $name = $movieData['title'] ?? '';
        $description = $movieData['overview'] ?? '';
        $release_date = $movieData['release_date'] ?? null;

        // Ajuste para fecha vacía
        if (empty($release_date)) {
            $release_date = null;
        }

        $runtime = $movieData['runtime'] ?? null;
        $poster = isset($movieData['poster_path']) ? "https://image.tmdb.org/t/p/w500" . $movieData['poster_path'] : null;
        $banner = isset($movieData['backdrop_path']) ? "https://image.tmdb.org/t/p/w500" . $movieData['backdrop_path'] : null;
        $status_type = 0;
        $user_score = isset($movieData['vote_average']) ? $movieData['vote_average'] * 10 : 0;
        $genre = isset($movieData['genres']) ? implode(', ', array_column($movieData['genres'], 'name')) : '';
        $directors = "";
        $writers = "";
        $streaming_provider = "";

        $stmt = $conn->prepare(
            "INSERT INTO movies (
                tmdb_id, titulo, description, release_date, runtime, poster, banner, status_type, user_score, genre, directors, writers, streaming_provider
            ) VALUES (
                :tmdb_id, :titulo, :description, :release_date, :runtime, :poster, :banner, :status_type, :user_score, :genre, :directors, :writers, :streaming_provider
            )"
        );
        $stmt->bindParam(':tmdb_id', $movie_id);
        $stmt->bindParam(':titulo', $name);
        $stmt->bindParam(':description', $description);
        // Si release_date es null, bindParam normal está bien, MySQL aceptará NULL
        $stmt->bindParam(':release_date', $release_date);
        $stmt->bindParam(':runtime', $runtime);
        $stmt->bindParam(':poster', $poster);
        $stmt->bindParam(':banner', $banner);
        $stmt->bindParam(':status_type', $status_type);
        $stmt->bindParam(':user_score', $user_score);
        $stmt->bindParam(':genre', $genre);
        $stmt->bindParam(':directors', $directors);
        $stmt->bindParam(':writers', $writers);
        $stmt->bindParam(':streaming_provider', $streaming_provider);
        $stmt->execute();
    }

    // Nueva funcionalidad: Si $tmdb_id no es numérico, buscar por nombre (título de la película)
    if (!ctype_digit($tmdb_id)) {
        // Buscar por nombre
        $searchName = urlencode($tmdb_id);
        $searchUrl = "https://api.themoviedb.org/3/search/movie?api_key=$apiKey&language=es&query=$searchName";
        $searchResponse = @file_get_contents($searchUrl);

        if ($searchResponse !== false) {
            $searchData = json_decode($searchResponse, true);
            if (isset($searchData['results']) && count($searchData['results']) > 0) {
                // Insertar todas las películas encontradas con ese nombre
                foreach ($searchData['results'] as $result) {
                    if (isset($result['id'])) {
                        insertarPelicula($result['id'], $conn, $apiKey);
                    }
                }
                $_SESSION['message'] = "Se han agregado las películas encontradas para el término: $tmdb_id.";
                header('Location: manage_movies.php');
                exit;
            } else {
                // No se encontraron películas con ese nombre
                // Continuar con la lógica original por si es una colección o película individual por ID
            }
        } else {
            // Si no hubo respuesta de la búsqueda por nombre, continuar con lógica original
        }
    }

    // Aquí si es numérico, se intenta primero como colección
    $collectionUrl = "https://api.themoviedb.org/3/collection/$tmdb_id?api_key=$apiKey&language=es";
    $collectionResponse = @file_get_contents($collectionUrl);

    if ($collectionResponse !== false) {
        $collectionData = json_decode($collectionResponse, true);
        // Si se encuentran datos de la colección y existe el campo 'parts'
        if (isset($collectionData['id']) && isset($collectionData['parts'])) {
            // Insertar la colección en la tabla colecciones si no existe
            $coll_id = $collectionData['id'];
            $coll_name = $collectionData['name'] ?? '';
            $coll_desc = $collectionData['overview'] ?? '';
            $coll_poster = isset($collectionData['poster_path']) ? "https://image.tmdb.org/t/p/w500" . $collectionData['poster_path'] : null;

            $stmt_check_col = $conn->prepare("SELECT COUNT(*) FROM colecciones WHERE tmdb_id = :tmdb_id");
            $stmt_check_col->bindParam(':tmdb_id', $coll_id, PDO::PARAM_INT);
            $stmt_check_col->execute();
            $collection_count = $stmt_check_col->fetchColumn();

            if ($collection_count == 0) {
                $stmt_collection = $conn->prepare(
                    "INSERT INTO colecciones (tmdb_id, nombre, descripcion, imagen_portada, fecha_agregado)
                     VALUES (:tmdb_id, :nombre, :descripcion, :imagen_portada, NOW())"
                );
                $stmt_collection->bindParam(':tmdb_id', $coll_id);
                $stmt_collection->bindParam(':nombre', $coll_name);
                $stmt_collection->bindParam(':descripcion', $coll_desc);
                $stmt_collection->bindParam(':imagen_portada', $coll_poster);
                $stmt_collection->execute();
            }

            // Insertar todas las películas de la colección
            foreach ($collectionData['parts'] as $part) {
                if (isset($part['id'])) {
                    insertarPelicula($part['id'], $conn, $apiKey);
                }
            }

            $_SESSION['message'] = "Colección y sus películas agregadas exitosamente.";
            header('Location: manage_movies.php');
            exit;
        }
    }

    // Si llega aquí, no es una colección o no se pudo obtener. Intentar como película individual.
    $movieUrl = "https://api.themoviedb.org/3/movie/$tmdb_id?api_key=$apiKey&language=es";
    $response = @file_get_contents($movieUrl);

    if ($response === false) {
        $_SESSION['error'] = "No se pudo encontrar una película o colección con el ID/Título proporcionado.";
        header('Location: add_movie.php');
        exit;
    }

    $movieData = json_decode($response, true);
    if (isset($movieData['status_code'])) {
        $_SESSION['error'] = "No se pudo encontrar una película con el ID/Título proporcionado.";
        header('Location: add_movie.php');
        exit;
    }

    // Insertar la película individual
    insertarPelicula($tmdb_id, $conn, $apiKey);
    $_SESSION['message'] = "Película agregada exitosamente.";
    header('Location: manage_movies.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Nueva Película</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
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
<body>
    <div class="flex h-screen">
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
                        <li><a href="admin_panel.php" class="nav-item flex items-center space-x-3 text-gray-300 p-3 rounded-lg"><i class="fas fa-home w-5"></i><span>Dashboard</span></a></li>
                        <li><a href="manage_movies.php" class="nav-item flex items-center space-x-3 text-gray-300 p-3 rounded-lg"><i class="fas fa-film w-5"></i><span>Gestionar Películas</span></a></li>
                        <li><a href="manage_series.php" class="nav-item flex items-center space-x-3 text-gray-300 p-3 rounded-lg"><i class="fas fa-video w-5"></i><span>Gestionar Series Web</span></a></li>
                        <li><a href="manage_users.php" class="nav-item flex items-center space-x-3 text-gray-300 p-3 rounded-lg"><i class="fas fa-users w-5"></i><span>Gestionar Usuarios</span></a></li>
                        <li class="mt-8"><a href="logout.php" class="nav-item flex items-center space-x-3 text-red-400 p-3 rounded-lg"><i class="fas fa-sign-out-alt w-5"></i><span>Cerrar Sesión</span></a></li>
                    </ul>
                </nav>
            </div>
        </div>
        <div class="flex-1 p-8">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                    Bienvenido al Panel de Administración
                </h1>
                <div class="flex space-x-4">
                    <a href="add_series.php" class="flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nueva Serie
                    </a>
                </div>
            </div>

            <div class="flex flex-col justify-center items-center min-h-screen p-4">
                <div class="w-full max-w-lg bg-gray-800 rounded-2xl shadow-2xl p-8 space-y-8 transform hover:scale-[1.01] transition-transform duration-300">
                    <div class="text-center space-y-4">
                        <i class="fas fa-film text-4xl text-blue-500"></i>
                        <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-400 to-blue-600">
                            Agregar Nueva Película
                        </h1>
                        <p class="text-gray-400 text-sm">
                            Ingresa el ID numérico de TMDB, el ID de una colección, o un título para agregar películas
                        </p>
                    </div>

                    <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded-lg relative" role="alert">
                        <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                    <?php endif; ?>

                    <form action="" method="POST" class="space-y-6">
                        <div class="space-y-2">
                            <label for="tmdb_id" class="block text-sm font-medium text-gray-300">
                                ID de TMDB (Película, Colección o Título)
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-hashtag text-gray-400"></i>
                                </div>
                                <input 
                                    type="text" 
                                    name="tmdb_id" 
                                    id="tmdb_id" 
                                    class="block w-full pl-10 pr-3 py-3 border border-gray-600 rounded-lg bg-gray-700/50 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 bg-gray-700"
                                    placeholder="Ej: 550 (Película), 558216 (Colección) o 'Deadpool' (Título)"
                                    required
                                >
                            </div>
                            <p class="text-xs text-gray-400 mt-1">
                                Puedes usar un ID de película, un ID de colección o el nombre de una película.
                            </p>
                        </div>
                        
                        <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
                            <h3 class="flex items-center text-sm font-medium text-gray-300 mb-2">
                                <i class="fas fa-info-circle text-purple-400 mr-2"></i>
                                Información Importante
                            </h3>
                            <ul class="text-xs text-gray-400 space-y-1 list-disc list-inside">
                                <li>Buscar peliculas por nombre es experimental, No se recomienda usar.</li>
                                <li>Si el valor es numérico y corresponde a una colección, se agregará la colección y todas sus películas.</li>
                                <li>Si el valor es numérico y corresponde a una película, se agregará esa película.</li>
                                <li>Si el valor no es numérico, se interpretará como título y se agregarán todas las películas encontradas con ese nombre.</li>
                            </ul>
                        </div>
                        <div class="space-y-4">
                            <button 
                                type="submit" 
                                class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800"
                            >
                                <i class="fas fa-plus mr-2"></i>
                                Agregar
                            </button>
                            
                            <a 
                                href="manage_movies.php" 
                                class="block w-full text-center py-3 px-4 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors duration-200"
                            >
                                <i class="fas fa-arrow-left mr-2"></i>
                                Volver a Gestionar Películas
                            </a>
                        </div>
                    </form>

                    <div class="text-center text-xs text-gray-400">
                        <p>¿Necesitas ayuda? <a href="#" class="text-blue-400 hover:underline">Consulta nuestra guía</a></p>
                    </div>
                </div>
            </div>

            <div id="loading-overlay" class="fixed inset-0 bg-gray-900/80 flex items-center justify-center hidden">
                <div class="text-center">
                    <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-500"></div>
                    <p class="mt-4 text-blue-500">Procesando...</p>
                </div>
            </div>

            <script>
                document.querySelector('form').addEventListener('submit', function() {
                    document.getElementById('loading-overlay').classList.remove('hidden');
                });
            </script>
        </div>
    </div>
</body>
</html>
