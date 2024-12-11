<?php
session_start();

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "amax";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // Habilitar excepciones para errores de PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    $_SESSION['error'] = "Error de conexión a la base de datos.";
    header('Location: manage_series.php');
    exit;
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != '1') {
    header('Location: login.php');
    exit;
}

// Verificar si se han proporcionado TMDB_ID y NOMBRE
if (!isset($_GET['tmdb_id']) || !isset($_GET['titulo'])) {
    $_SESSION['error'] = "Datos de serie no proporcionados.";
    header('Location: manage_series.php');
    exit;
}

// Extraer el tmdb_id y separar el número de temporada si está presente
$tmdb_id_param = trim($_GET['tmdb_id']);
$tmdb_parts = explode('-', $tmdb_id_param);

$tmdb_id = (int)$tmdb_parts[0];
$name = trim($_GET['titulo']);

// Si el tmdb_id está vacío o no es válido, redirigir con un mensaje de error
if (empty($tmdb_id) || $tmdb_id <= 0) {
    $_SESSION['error'] = "El TMDB ID proporcionado no es válido.";
    header('Location: manage_series.php');
    exit;
}

// Obtener el ID correspondiente al TMDB_ID desde la tabla web_series
$stmt_get_series = $conn->prepare("SELECT id FROM web_series WHERE TMDB_ID = :tmdb_id");
$stmt_get_series->bindParam(':tmdb_id', $tmdb_id, PDO::PARAM_INT);
$stmt_get_series->execute();
$series = $stmt_get_series->fetch(PDO::FETCH_ASSOC);

if (!$series) {
    $_SESSION['error'] = "No se encontró la serie con el TMDB ID proporcionado.";
    header('Location: manage_series.php');
    exit;
} 

$web_series_id = $series['id'];

// Generar token CSRF si es una petición GET
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Variables para almacenar temporadas obtenidas
$available_seasons = [];
$selected_seasons = [];

// Procesar el formulario según la acción
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validar el token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Token CSRF inválido.";
        header('Location: add_seasons.php?tmdb_id=' . urlencode($tmdb_id_param) . '&titulo=' . urlencode($name));
        exit;
    }

    // Identificar la acción
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'fetch_seasons') {
            // Acción: Obtener Temporadas desde TMDB
            $apiKey = "f0d20520594c7f22a84bd84472f00297";
            $tmdbUrl = "https://api.themoviedb.org/3/tv/$tmdb_id?api_key=$apiKey&language=es";

            $response = @file_get_contents($tmdbUrl);

            if ($response === FALSE) {
                $_SESSION['error'] = "No se pudo obtener la información de la serie desde TMDB. Verifique el TMDB ID.";
            } else {
                $seriesData = json_decode($response, true);

                if ($seriesData && !isset($seriesData['status_code'])) {
                    // Obtener todas las temporadas
                    if (isset($seriesData['seasons']) && is_array($seriesData['seasons'])) {
                        foreach ($seriesData['seasons'] as $season) {
                            // Evitar la temporada especial (e.g., temporada 0)
                            if ($season['season_number'] > 0) {
                                $available_seasons[] = [
                                    'season_number' => $season['season_number'],
                                    'season_name' => $season['name']
                                ];
                            }
                        }
                    } else {
                        $_SESSION['error'] = "No se encontraron temporadas para esta serie.";
                    }
                } else {
                    $_SESSION['error'] = "No se pudo obtener la información de la serie desde TMDB. Posiblemente no exista.";
                }
            }
        } elseif ($action === 'send_to_db') {
            // Acción: Enviar Temporadas Seleccionadas a la DB
            if (isset($_POST['selected_seasons']) && is_array($_POST['selected_seasons'])) {
                $selected_seasons = $_POST['selected_seasons'];
                $apiKey = "f0d20520594c7f22a84bd84472f00297";

                foreach ($selected_seasons as $season_number) {
                    $season_number = (int)$season_number;

                    // Verificar si la temporada ya existe en la DB
                    $stmt_check_season = $conn->prepare("SELECT id FROM temporada WHERE web_series_id = :web_series_id AND season_order = :season_order");
                    $stmt_check_season->bindParam(':web_series_id', $web_series_id, PDO::PARAM_INT);
                    $stmt_check_season->bindParam(':season_order', $season_number, PDO::PARAM_INT);
                    $stmt_check_season->execute();

                    if ($stmt_check_season->fetch()) {
                        $_SESSION['error'] = "La temporada $season_number ya existe para esta serie.";
                        continue; // Saltar a la siguiente temporada
                    }

                    // Llamar a la API de TMDB para obtener los datos de la temporada
                    $tmdbUrl = "https://api.themoviedb.org/3/tv/$tmdb_id/season/$season_number?api_key=$apiKey&language=es";
                    $response = @file_get_contents($tmdbUrl);

                    if ($response === FALSE) {
                        $_SESSION['error'] = "No se pudo obtener la información de la temporada $season_number desde TMDB.";
                        continue;
                    }

                    $seasonData = json_decode($response, true);

                    if ($seasonData && !isset($seasonData['status_code'])) {
                        $season_name = $seasonData['name'];

                        try {
                            // Iniciar transacción
                            $conn->beginTransaction();

                            // Insertar nueva temporada en la base de datos
                            $stmt_insert_season = $conn->prepare("INSERT INTO temporada (web_series_id, season_name, season_order) VALUES (:web_series_id, :season_name, :season_order)");
                            $stmt_insert_season->bindParam(':web_series_id', $web_series_id, PDO::PARAM_INT);
                            $stmt_insert_season->bindParam(':season_name', $season_name, PDO::PARAM_STR);
                            $stmt_insert_season->bindParam(':season_order', $season_number, PDO::PARAM_INT);
                            $stmt_insert_season->execute();
                            
                            // Obtener el ID de la temporada insertada
                            $season_id = $conn->lastInsertId();

                            // Insertar episodios en la base de datos
                            if (isset($seasonData['episodes']) && is_array($seasonData['episodes'])) {
                                foreach ($seasonData['episodes'] as $episode) {
                                    $episode_name = $episode['name'];
                                    $episode_description = $episode['overview'] ?? '';
                                    $episode_image = isset($episode['still_path']) ? "https://image.tmdb.org/t/p/w500" . $episode['still_path'] : '';
                                    $episode_order = $episode['episode_number'];

                                    $stmt_insert_episode = $conn->prepare("INSERT INTO web_series_episodes (season_id, episode_name, episode_description, episode_image, episode_order) VALUES (:season_id, :episode_name, :episode_description, :episode_image, :episode_order)");
                                    $stmt_insert_episode->bindParam(':season_id', $season_id, PDO::PARAM_INT);
                                    $stmt_insert_episode->bindParam(':episode_name', $episode_name, PDO::PARAM_STR);
                                    $stmt_insert_episode->bindParam(':episode_description', $episode_description, PDO::PARAM_STR);
                                    $stmt_insert_episode->bindParam(':episode_image', $episode_image, PDO::PARAM_STR);
                                    $stmt_insert_episode->bindParam(':episode_order', $episode_order, PDO::PARAM_INT);
                                    $stmt_insert_episode->execute();
                                }
                            }

                            // Confirmar transacción
                            $conn->commit();
                            $_SESSION['message'] = "Temporada $season_number y sus episodios agregados exitosamente.";
                        } catch (Exception $e) {
                            // Revertir transacción en caso de error
                            $conn->rollBack();
                            $_SESSION['error'] = "Hubo un error al agregar la temporada $season_number y sus episodios: " . $e->getMessage();
                        }
                    } else {
                        $_SESSION['error'] = "No se pudo obtener la información de la temporada $season_number desde TMDB. Posiblemente no exista.";
                    }
                }

                header('Location: manage_series.php');
                exit;
            } else {
                $_SESSION['error'] = "No se seleccionaron temporadas para agregar.";
                header('Location: add_seasons.php?tmdb_id=' . urlencode($tmdb_id_param) . '&titulo=' . urlencode($name));
                exit;
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Temporada - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
        }
    </style>
</head>
<body class="antialiased">
    <div class="flex min-h-screen">
        <!-- Sidebar Redesigned -->
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

        <!-- Main Content -->
        <div class="flex-1 p-8">
            <div class="max-w-3xl mx-auto">
                <h1 class="text-3xl text-center font-bold mb-8 bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">Agregar Temporada</h1>
                
                <!-- Mensajes de Error -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500 text-white p-4 rounded mb-6">
                        <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Mensajes de Éxito -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="bg-green-500 text-white p-4 rounded mb-6">
                        <?php 
                        echo $_SESSION['message']; 
                        unset($_SESSION['message']);
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Formulario para Obtener Temporadas -->
                <form action="add_seasons.php?tmdb_id=<?php echo urlencode($tmdb_id_param); ?>&titulo=<?php echo urlencode($name); ?>" method="POST" class="bg-gray-800 p-8 rounded-lg shadow-lg space-y-6">
                    <!-- Campo oculto para CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <!-- Campo oculto para identificar la acción -->
                    <input type="hidden" name="action" value="fetch_seasons">

                    <div class="flex space-x-4">
                        <div class="flex-1">
                            <label for="tmdb_id" class="block text-sm font-semibold mb-2">TMDB ID de la Serie</label>
                            <input type="text" name="tmdb_id" value="<?php echo htmlspecialchars($tmdb_id_param); ?>" readonly class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg">
                        </div>

                        <div class="flex-1">
                            <label for="name" class="block text-sm font-semibold mb-2">Nombre de la Serie</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" readonly class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg">
                        </div>
                    </div>

                    <!-- Botón Obtener Temporadas -->
                    <div>
                        <button type="submit" name="fetch_seasons_btn" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors w-full">Obtener Temporadas</button>
                    </div>
                </form>

                <!-- Si se han obtenido temporadas, mostrarlas en otro formulario -->
                <?php if (!empty($available_seasons)): ?>
                    <form action="add_seasons.php?tmdb_id=<?php echo urlencode($tmdb_id_param); ?>&titulo=<?php echo urlencode($name); ?>" method="POST" class="bg-gray-800 p-8 rounded-lg shadow-lg space-y-6 mt-8">
                        <!-- Campo oculto para CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <!-- Campo oculto para identificar la acción -->
                        <input type="hidden" name="action" value="send_to_db">

                        <h2 class="text-2xl font-bold mb-4">Selecciona las Temporadas a Agregar</h2>

                        <div class="space-y-4">
                            <?php foreach ($available_seasons as $season): ?>
                                <div class="flex items-center">
                                    <input type="checkbox" name="selected_seasons[]" value="<?php echo htmlspecialchars($season['season_number']); ?>" id="season_<?php echo htmlspecialchars($season['season_number']); ?>" class="form-checkbox h-5 w-5 text-blue-600">
                                    <label for="season_<?php echo htmlspecialchars($season['season_number']); ?>" class="ml-2 text-lg"><?php echo "Temporada " . htmlspecialchars($season['season_number']) . ": " . htmlspecialchars($season['season_name']); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div>
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors w-full">Enviar a DB</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
