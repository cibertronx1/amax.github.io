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

// Verificar si se ha proporcionado el ID de la serie
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID de serie no proporcionado.";
    header('Location: manage_series.php');
    exit;
}

$series_id = (int)$_GET['id'];

// Obtener temporadas de la serie
$stmt_seasons = $conn->prepare("SELECT * FROM temporada WHERE web_series_id = :series_id");
$stmt_seasons->bindParam(':series_id', $series_id, PDO::PARAM_INT);
$stmt_seasons->execute();
$seasons = $stmt_seasons->fetchAll(PDO::FETCH_ASSOC);

// Procesar la eliminación de la temporada si se envía el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['season_id'])) {
        $_SESSION['error'] = "No se ha seleccionado ninguna temporada para eliminar.";
        header('Location: delete_seasons.php?id=' . $series_id);
        exit;
    }

    $season_id = (int)$_POST['season_id'];

    try {
        // Iniciar una transacción
        $conn->beginTransaction();

        // Eliminar los episodios asociados con la temporada
        $stmt_delete_episodes = $conn->prepare("DELETE FROM temporada WHERE season_id = :season_id");
        $stmt_delete_episodes->bindParam(':season_id', $season_id, PDO::PARAM_INT);
        $stmt_delete_episodes->execute();

        // Eliminar la temporada
        $stmt_delete_season = $conn->prepare("DELETE FROM temporada WHERE id = :season_id");
        $stmt_delete_season->bindParam(':season_id', $season_id, PDO::PARAM_INT);
        $stmt_delete_season->execute();

        // Confirmar la transacción
        $conn->commit();

        $_SESSION['message'] = "Temporada eliminada exitosamente.";
        header('Location: manage_series.php');
        exit;
    } catch (PDOException $e) {
        // Revertir la transacción en caso de error
        $conn->rollBack();
        $_SESSION['error'] = "Error al eliminar la temporada: " . $e->getMessage();
        header('Location: delete_seasons.php?id=' . $series_id);
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Temporada - Admin Panel</title>
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
                <h1 class="text-3xl text-center font-bold mb-8 bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">Eliminar Temporada</h1>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500 text-white p-4 rounded mb-6">
                        <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <form action="delete_seasons.php?id=<?php echo urlencode($series_id); ?>" method="POST" class="bg-gray-800 p-8 rounded-lg shadow-lg space-y-6">
                    <div>
                        <label for="season_id" class="block text-sm font-semibold mb-2 text-center">Seleccionar Temporada a Eliminar</label>
                        <select name="season_id" required class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg">
                            <?php foreach ($seasons as $season): ?>
                                <option value="<?php echo htmlspecialchars($season['id']); ?>">
                                    <?php echo "Temporada " . htmlspecialchars($season['season_order']) . " - " . htmlspecialchars($season['season_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg transition-colors w-full text-center">Eliminar Temporada</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>


