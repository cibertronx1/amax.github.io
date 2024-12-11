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
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != '1') {
    header('Location: login.php');
    exit;
}

// Verificar si se ha proporcionado el ID de la temporada
if (!isset($_GET['season_name'])) {
    echo "ID de la temporada no proporcionado.";
    exit;
}

$season_name = (int)$_GET['season_name'];

// Obtener detalles de la temporada
$stmt_season = $conn->prepare("SELECT * FROM web_series_seasons WHERE id = :season_name");
$stmt_season->bindParam(':id', $season_name, PDO::PARAM_INT);
$stmt_season->execute();
$season = $stmt_season->fetch(PDO::FETCH_ASSOC);

if (!$season) {
    echo "Temporada no encontrada.";
    exit;
}

// Obtener episodios para la temporada
$stmt_episodes = $conn->prepare("SELECT * FROM web_series_episodes WHERE season_name = :season_name ORDER BY episode_order ASC");
$stmt_episodes->bindParam(':season_name', $season_id, PDO::PARAM_INT);
$stmt_episodes->execute();
$episodes = $stmt_episodes->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Episodios - <?php echo htmlspecialchars($season['season_name']); ?></title>
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
            <div class="max-w-6xl mx-auto">
                <h1 class="text-3xl font-bold mb-8 bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                    Gestionar Episodios - <?php echo htmlspecialchars($season['season_name']); ?>
                </h1>
                
                <div class="flex justify-between items-center mb-8">
                    <h2 class="text-2xl font-bold text-gray-200">Lista de Episodios</h2>
                    <a href="add_episode.php?season_id=<?php echo $season_id; ?>" class="flex items-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Agregar Nuevo Episodio
                    </a>
                </div>

                <!-- Episodes Table -->
                <div class="bg-gray-800 rounded-lg overflow-hidden shadow-xl">
                    <table class="w-full">
                        <thead class="bg-gray-700 text-gray-300">
                            <tr>
                                <th class="p-4 text-left">Número de Episodio</th>
                                <th class="p-4 text-left">Nombre del Episodio</th>
                                <th class="p-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($episodes) > 0): ?>
                                <?php foreach ($episodes as $episode): ?>
                                    <tr class="border-b border-gray-700 table-hover">
                                        <td class="p-4"><?php echo htmlspecialchars($episode['episode_order']); ?></td>
                                        <td class="p-4"><?php echo htmlspecialchars($episode['episode_name']); ?></td>
                                        <td class="p-4 flex justify-center space-x-3">
                                            <a href="edit_episode.php?id=<?php echo $episode['id']; ?>" class="text-green-400 hover:text-green-300">
                                                <i class="fas fa-edit"></i> Editar
                                            </a>
                                            <a href="delete_episode.php?id=<?php echo $episode['id']; ?>" class="text-red-400 hover:text-red-300" onclick="return confirm('¿Está seguro de que desea eliminar este episodio?');">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </a>
                                            <a href="detail_episode.php?id=<?php echo $episode['id']; ?>" class="text-blue-400 hover:text-blue-300">
                                                <i class="fas fa-play"></i> Ver Ahora
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="p-4 text-center text-gray-500">No hay episodios para esta temporada.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
