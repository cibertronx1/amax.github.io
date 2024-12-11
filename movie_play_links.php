<?php
session_start();
// todo funciona bien, haga copia antes de hacer cambiossssssssssssss
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

// Verificar si se ha proporcionado el ID de la película (TMDB_ID)
if (!isset($_GET['tmdb_id'])) {
    $_SESSION['error'] = "ID de película no proporcionado.";
    header('Location: manage_movies.php');
    exit;
}

$tmdb_id = (int)$_GET['tmdb_id'];

// Obtener el ID real de la película en la tabla `movies`
$stmt_movie = $conn->prepare("SELECT tmdb_id, titulo FROM movies WHERE tmdb_id = :tmdb_id");
$stmt_movie->bindParam(':tmdb_id', $tmdb_id, PDO::PARAM_INT);
$stmt_movie->execute();
$movie = $stmt_movie->fetch(PDO::FETCH_ASSOC);

// Validar si se obtuvo la película
if (!$movie) {
    $_SESSION['error'] = "Película no encontrada.";
    header('Location: manage_movies.php');
    exit;
}

$movie_id = $movie['tmdb_id'];
$movie_name = $movie['titulo'];

// Obtener información de los enlaces de reproducción de la película
$stmt_links = $conn->prepare("SELECT * FROM movie_play_links WHERE movie_id = :movie_id ORDER BY link_order ASC");
$stmt_links->bindParam(':movie_id', $movie_id, PDO::PARAM_INT);
$stmt_links->execute();
$play_links = $stmt_links->fetchAll(PDO::FETCH_ASSOC);

// Procesar el formulario para agregar un nuevo enlace de reproducción
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $quality = $_POST['quality'];
    $url = $_POST['url'];
    $link_order = $_POST['link_order'];

    $stmt = $conn->prepare("INSERT INTO movie_play_links (movie_id, name, quality, url, link_order) VALUES (:movie_id, :name, :quality, :url, :link_order)");
    $stmt->bindParam(':movie_id', $movie_id, PDO::PARAM_INT);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':quality', $quality);
    $stmt->bindParam(':url', $url);
    $stmt->bindParam(':link_order', $link_order, PDO::PARAM_INT);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Enlace de reproducción agregado exitosamente.";
        header("Location: movie_play_links.php?tmdb_id=" . $tmdb_id);
        exit;
    } else {
        $_SESSION['error'] = "Hubo un error al agregar el enlace de reproducción. Inténtelo de nuevo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Play Links Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #1e293b;
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body>
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
                       <h1 class="text-3xl text-center font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                       Add New Play Link
                       </h1>
           </div>
        <!-- Main Content -->
        <div class="flex-1 flex flex-col">
            <main class="p-8 space-y-8 overflow-y-auto scrollbar-hide">
                <!-- Add New Play Link Section -->
                <section class="bg-slate-800 rounded-2xl shadow-2xl p-8">    
                    
                    <?php
                    if (isset($_SESSION['error'])) {
                        echo "<div class='bg-red-600 bg-opacity-10 border border-red-500 text-red-400 p-4 rounded-lg mb-6'>{$_SESSION['error']}</div>";
                        unset($_SESSION['error']);
                    }
                    if (isset($_SESSION['message'])) {
                        echo "<div class='bg-green-600 bg-opacity-10 border border-green-500 text-green-400 p-4 rounded-lg mb-6'>{$_SESSION['message']}</div>";
                        unset($_SESSION['message']);
                    }
                    ?>

                    <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block mb-2 text-gray-300">Link Languaje</label>
                            <input type="text" name="name" id="name" class="w-full p-3 bg-slate-700 rounded-lg border border-slate-600 focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label for="quality" class="block mb-2 text-gray-300">Link Quality</label>
                            <input type="text" name="quality" id="quality" class="w-full p-3 bg-slate-700 rounded-lg border border-slate-600 focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label for="link_order" class="block mb-2 text-gray-300">Link Order</label>
                            <input type="number" name="link_order" id="link_order" class="w-full p-3 bg-slate-700 rounded-lg border border-slate-600 focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="md:col-span-2">
                            <label for="url" class="block mb-2 text-gray-300">Link URL</label>
                            <input type="url" name="url" id="url" class="w-full p-3 bg-slate-700 rounded-lg border border-slate-600 focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" class="w-full bg-blue-600 text-white p-4 rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-plus mr-2"></i>Add Play Link
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Play Links Section -->
                <section class="bg-slate-800 rounded-2xl shadow-2xl p-8">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-3xl font-bold text-white">Play Links for "<?php echo htmlspecialchars($movie_name); ?>"</h1>
                        <span class="bg-blue-500 text-white px-4 py-2 rounded-lg">TMDB ID: <?php echo htmlspecialchars($tmdb_id); ?></span>
                    </div>

                    <?php if (count($play_links) > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse">
                                <thead>
                                    <tr class="bg-slate-900 text-gray-400">
                                        <th class="px-6 py-4 text-left">Order</th>
                                        <th class="px-6 py-4 text-left">Name</th>
                                        <th class="px-6 py-4 text-left">Quality</th>
                                        <th class="px-6 py-4 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($play_links as $link): ?>
                                        <tr class="border-b border-slate-700 hover:bg-slate-700 transition-colors">
                                            <td class="px-6 py-4 text-sm text-gray-300"><?php echo htmlspecialchars($link['link_order']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-300"><?php echo htmlspecialchars($link['name']); ?></td>
                                            <td class="px-6 py-4 text-sm text-blue-400"><?php echo htmlspecialchars($link['quality']); ?></td>
                                            <td class="px-6 py-4">
                                                <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" class="text-blue-500 hover:text-blue-400 mr-4">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                                <a href="#" class="text-yellow-500 hover:text-yellow-400">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="bg-slate-700 rounded-lg p-6 text-center">
                            <i class="fas fa-video text-4xl text-gray-500 mb-4"></i>
                            <p class="text-gray-400">No play links found for this movie.</p>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>
</body>
</html>
