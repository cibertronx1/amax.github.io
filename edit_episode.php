
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

// Verificar si se ha proporcionado el ID del episodio
if (!isset($_GET['episode_id'])) {
    echo "ID del episodio no proporcionado.";
    exit;
}

$episode_id = (int)$_GET['episode_id'];

// Obtener detalles del episodio
$stmt_episode = $conn->prepare("SELECT * FROM web_series_episodes WHERE id = :episode_id");
$stmt_episode->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);
$stmt_episode->execute();
$episode = $stmt_episode->fetch(PDO::FETCH_ASSOC);

if (!$episode) {
    echo "Episodio no encontrado.";
    exit;
}

// Procesar el formulario para editar el episodio
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $episode_name = $_POST['episode_name'];
    $episode_image = $_POST['episode_image'];
    $episode_description = $_POST['episode_description'];
    $episode_order = (int)$_POST['episode_order'];

    $stmt_update = $conn->prepare("UPDATE web_series_episodes SET 
                                    episode_name = :episode_name,
                                    episode_image = :episode_image,
                                    episode_description = :episode_description,
                                    episode_order = :episode_order
                                    WHERE id = :episode_id");
    $stmt_update->bindParam(':episode_name', $episode_name);
    $stmt_update->bindParam(':episode_image', $episode_image);
    $stmt_update->bindParam(':episode_description', $episode_description);
    $stmt_update->bindParam(':episode_order', $episode_order);
    $stmt_update->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);

    if ($stmt_update->execute()) {
        $_SESSION['message'] = "Episodio actualizado exitosamente.";
        header("Location: manage_episodes.php?season_id=" . $episode['season_id']);
        exit;
    } else {
        $_SESSION['error'] = "Hubo un error al actualizar el episodio. Inténtelo de nuevo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Episodio - <?php echo htmlspecialchars($episode['episode_name']); ?></title>
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
    <div class="container mx-auto px-6 py-12">
        <h1 class="text-4xl font-bold mb-8 bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
            Editar Episodio - <?php echo htmlspecialchars($episode['episode_name']); ?>
        </h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-500 text-white p-4 rounded-lg mb-4">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg mb-8">
                <div class="mb-4">
                    <label for="episode_name" class="block text-sm font-medium text-gray-300">Nombre del Episodio</label>
                    <input type="text" name="episode_name" id="episode_name" value="<?php echo htmlspecialchars($episode['episode_name']); ?>" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label for="episode_image" class="block text-sm font-medium text-gray-300">URL de la Imagen del Episodio</label>
                    <input type="text" name="episode_image" id="episode_image" value="<?php echo htmlspecialchars($episode['episode_image']); ?>" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="mb-4">
                    <label for="episode_description" class="block text-sm font-medium text-gray-300">Descripción del Episodio</label>
                    <textarea name="episode_description" id="episode_description" rows="4" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($episode['episode_description']); ?></textarea>
                </div>

                <div class="mb-4">
                    <label for="episode_order" class="block text-sm font-medium text-gray-300">Orden del Episodio</label>
                    <input type="number" name="episode_order" id="episode_order" value="<?php echo htmlspecialchars($episode['episode_order']); ?>" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors">
                Guardar Cambios
            </button>
        </form>
    </div>
</body>
</html>
