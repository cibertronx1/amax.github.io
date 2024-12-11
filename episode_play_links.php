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
    echo "Conexión fallida: " . $e->getMessage();
    die();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != '1') {
    header('Location: login.php');
    exit;
}

// Obtener el `tmdb_id` de la URL
$tmdb_id = isset($_GET['tmdb_id']) ? trim($_GET['tmdb_id']) : '';

// Validar que se haya proporcionado un `tmdb_id`
if (empty($tmdb_id)) {
    $_SESSION['error'] = "No se proporcionó un identificador de serie válido.";
    header("Location: manage_series.php");
    exit;
}

// Verificar que el `tmdb_id` existe en `web_series`
$stmt = $conn->prepare("SELECT id FROM web_series WHERE tmdb_id = :tmdb_id");
$stmt->bindParam(':tmdb_id', $tmdb_id, PDO::PARAM_INT);
$stmt->execute();
$series = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$series) {
    $_SESSION['error'] = "La serie con el identificador proporcionado no existe.";
    header("Location: manage_series.php");
    exit;
}

$web_series_id = $series['id'];

// Obtener todas las temporadas disponibles para la serie específica
$stmt = $conn->prepare("
    SELECT 
        temporada.id, 
        temporada.season_name, 
        temporada.season_order, 
        temporada.web_series_id,
        web_series.titulo AS series_title
    FROM temporada
    JOIN web_series ON temporada.web_series_id = web_series.id
    WHERE web_series.tmdb_id = :tmdb_id
    ORDER BY temporada.season_order ASC
");
$stmt->bindParam(':tmdb_id', $tmdb_id, PDO::PARAM_INT);
$stmt->execute();
$seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar si se ha seleccionado una temporada
$selected_season_id = isset($_GET['season_id']) ? (int)$_GET['season_id'] : 0;

$episodes = [];
$play_links_assoc = []; // Inicializar el array para asociar enlaces
if ($selected_season_id > 0) {
    // Obtener los episodios de la temporada seleccionada
    $stmt = $conn->prepare("
        SELECT 
            id, 
            episode_name, 
            episode_image, 
            episode_description, 
            episode_order 
        FROM web_series_episodes 
        WHERE season_id = :season_id 
        ORDER BY episode_order ASC
    ");
    $stmt->bindParam(':season_id', $selected_season_id, PDO::PARAM_INT);
    $stmt->execute();
    $episodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener los enlaces de reproducción existentes para los episodios
    $episode_ids = array_column($episodes, 'id');
    if (!empty($episode_ids)) {
        // Crear marcadores de posición para la consulta
        $placeholders = implode(',', array_fill(0, count($episode_ids), '?'));
        $stmt = $conn->prepare("
            SELECT * 
            FROM episode_play_links 
            WHERE episode_id IN ($placeholders) 
            ORDER BY episode_id, link_order ASC
        ");
        $stmt->execute($episode_ids);
        $play_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Asociar los enlaces a sus respectivos episodios
        foreach ($play_links as $link) {
            $play_links_assoc[$link['episode_id']][] = $link; // Permitir múltiples enlaces
        }
    }
}

// Procesar el formulario para agregar nuevos enlaces
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_links'])) {
    if (isset($_POST['episodes']) && is_array($_POST['episodes'])) {
        foreach ($_POST['episodes'] as $episode_id => $links) {
            if (isset($links['new']) && is_array($links['new'])) {
                $new_link = $links['new']; // Obtener los datos del nuevo enlace
                $name = trim($new_link['name'] ?? '');
                $quality = trim($new_link['quality'] ?? '');
                $url = trim($new_link['url'] ?? '');
                $is_downloadable = isset($new_link['is_downloadable']) ? 1 : 0;

                // Validar campos obligatorios
                if (empty($name) || empty($url)) {
                    $_SESSION['error'] = "Nombre y URL son obligatorios para los nuevos enlaces.";
                    continue;
                }

                // Validar URL
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $_SESSION['error'] = "URL inválida para el enlace: $name.";
                    continue;
                }

                // Determinar el orden del enlace
                $stmt = $conn->prepare("
                    SELECT MAX(link_order) as max_order 
                    FROM episode_play_links 
                    WHERE episode_id = :episode_id
                ");
                $stmt->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $link_order = ($result['max_order'] ?? 0) + 1;

                // Insertar el nuevo enlace
                $stmt = $conn->prepare("
                    INSERT INTO episode_play_links 
                    (name, quality, link_order, episode_id, url, is_downloadable) 
                    VALUES 
                    (:name, :quality, :link_order, :episode_id, :url, :is_downloadable)
                ");
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':quality', $quality);
                $stmt->bindParam(':link_order', $link_order, PDO::PARAM_INT);
                $stmt->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);
                $stmt->bindParam(':url', $url);
                $stmt->bindParam(':is_downloadable', $is_downloadable, PDO::PARAM_INT);
                $stmt->execute();
            }
        }

        if (!isset($_SESSION['error'])) {
            $_SESSION['message'] = "Enlaces de reproducción agregados exitosamente.";
        }

        header("Location: " . $_SERVER['PHP_SELF'] . "?tmdb_id=$tmdb_id&season_id=$selected_season_id");
        exit;
    } else {
        $_SESSION['error'] = "No se proporcionaron enlaces de reproducción para agregar.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Enlaces de Reproducción</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
        }
        .table-container {
            overflow-x: auto;
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
<div class="flex h-screen">
        <!-- Enhanced Sidebar -->
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
                    <h1 class="text-3xl center font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                    Gestionar Enlaces de Reproducción
                    </h1>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-500 text-white p-4 rounded-lg mb-4">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-500 text-white p-4 rounded-lg mb-4">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario de Selección de Temporada -->
        <form method="GET" action="episode_play_links.php" class="mb-8">
            <input type="hidden" name="tmdb_id" value="<?php echo htmlspecialchars($tmdb_id); ?>">
            <div class="flex items-center">
                <label for="season_id" class="mr-4 text-lg">Selecciona una Temporada:</label>
                <select name="season_id" id="season_id" class="p-2 rounded-lg bg-gray-700 text-gray-200" required>
                    <option value="">-- Selecciona --</option>
                    <?php foreach ($seasons as $season): ?>
                        <option value="<?php echo htmlspecialchars($season['id']); ?>" <?php echo ($season['id'] == $selected_season_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($season['series_title'] . ' - ' . $season['season_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="ml-4 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                    Seleccionar
                </button>
            </div>
        </form>

        <?php if ($selected_season_id > 0): ?>
            <h2 class="text-2xl text-center font-semibold mb-4">
                Temporada Seleccionada: 
                <?php 
                    // Obtener el nombre de la temporada seleccionada
                    foreach ($seasons as $season) {
                        if ($season['id'] == $selected_season_id) {
                            echo htmlspecialchars($season['series_title'] . ' - ' . $season['season_name']);
                            break;
                        }
                    }
                ?>
            </h2>

            <?php if (!empty($episodes)): ?>
                <form action="episode_play_links.php?tmdb_id=<?php echo htmlspecialchars($tmdb_id); ?>&season_id=<?php echo htmlspecialchars($selected_season_id); ?>" method="POST">
                    <!-- Campos ocultos para mantener tmdb_id y season_id -->
                    <input type="hidden" name="tmdb_id" value="<?php echo htmlspecialchars($tmdb_id); ?>">
                    <input type="hidden" name="season_id" value="<?php echo htmlspecialchars($selected_season_id); ?>">

                    <div class="table-container">
                        <table class="min-w-full bg-gray-800 rounded-lg">
                            <thead>
                                <tr>
                                    <th class="py-3 px-6 bg-gray-700 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">#</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($episodes as $episode): ?>
                                    <tr class="border-t border-gray-700">
                                        <td class="py-4 px-6"><?php echo htmlspecialchars($episode['episode_order']); ?></td>
                                       
                                        <td class="py-4 px-6">
                                            <!-- Mostrar Enlaces Existentes -->
                                            <?php if (isset($play_links_assoc[$episode['id']])): ?>
                                                <ul class="mb-4">
                                                    <?php foreach ($play_links_assoc[$episode['id']] as $link): ?>
                                                        <li class="mb-2">
                                                            <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" class="text-blue-400 underline">
                                                                <?php echo htmlspecialchars($link['name'] . ' (' . $link['quality'] . ')'); ?>
                                                            </a>
                                                            <?php if ($link['is_downloadable']): ?>
                                                                <span class="text-green-500">(Descargable)</span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p class="text-gray-400 mb-4">No hay enlaces de reproducción.</p>
                                            <?php endif; ?>

                                            <!-- Formulario para Agregar Nuevo Enlace -->
                                            <div class="bg-gray-700 p-4 rounded-lg">
                                                <h3 class="text-sm font-semibold mb-2">Agregar Nuevo Enlace:</h3>
                                                <div class="space-y-2">
                                                    <div>
                                                        <label class="block text-sm">Nombre:</label>
                                                        <input type="text" name="episodes[<?php echo htmlspecialchars($episode['id']); ?>][new][name]" class="w-full px-3 py-2 rounded-lg bg-gray-600 text-gray-200" placeholder="Nombre del Enlace" required>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm">Calidad:</label>
                                                        <input type="text" name="episodes[<?php echo htmlspecialchars($episode['id']); ?>][new][quality]" class="w-full px-3 py-2 rounded-lg bg-gray-600 text-gray-200" placeholder="Ej: 720p, 1080p" required>
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm">URL:</label>
                                                        <input type="url" name="episodes[<?php echo htmlspecialchars($episode['id']); ?>][new][url]" class="w-full px-3 py-2 rounded-lg bg-gray-600 text-gray-200" placeholder="https://ejemplo.com/enlace" required>
                                                    </div>
                                                    <div class="flex items-center">
                                                        <input type="checkbox" name="episodes[<?php echo htmlspecialchars($episode['id']); ?>][new][is_downloadable]" class="mr-2" value="1">
                                                        <span class="text-sm">Descargable</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6">
                        <button type="submit" name="add_links" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg transition-colors">
                            Agregar Enlaces de Reproducción
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <p class="text-gray-400">No se encontraron episodios para esta temporada.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
