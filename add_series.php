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

// Procesar el formulario para agregar una serie
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tmdb_id = $_POST['tmdb_id'];

    // Verificar si el TMDB_ID ya existe en la base de datos
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM web_series WHERE TMDB_ID = :tmdb_id");
    $stmt_check->bindParam(':tmdb_id', $tmdb_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $count = $stmt_check->fetchColumn();

    if ($count > 0) {
        $_SESSION['error'] = "La serie con el TMDB ID proporcionado ya está registrada.";
        header('Location: add_series.php');
        exit;
    }

    // Llamar a la API de TMDB para obtener los datos de la serie
    $apiKey = "f0d20520594c7f22a84bd84472f00297";
    $tmdbUrl = "https://api.themoviedb.org/3/tv/$tmdb_id?api_key=$apiKey&language=es";

    try {
        // Obtener datos de la API
        $response = @file_get_contents($tmdbUrl);
        if ($response === false) {
            throw new Exception("No se pudo encontrar una serie con el ID proporcionado.");
        }

        $seriesData = json_decode($response, true);

        // Verificar si la respuesta de la API contiene un error
        if (isset($seriesData['status_code'])) {
            throw new Exception("No se pudo encontrar una serie con el ID proporcionado.");
        }

        // Insertar datos en la base de datos
        $name = $seriesData['name'];
        $description = $seriesData['overview'];
        $release_date = $seriesData['first_air_date'];
        $poster = "https://image.tmdb.org/t/p/w500" . $seriesData['poster_path'];
        $banner = "https://image.tmdb.org/t/p/w500" . $seriesData['backdrop_path'];
        $status_type = isset($_POST['status_type']) ? 1 : 0;

        $stmt = $conn->prepare("INSERT INTO web_series (TMDB_ID, titulo, description, release_date, poster, banner, status_type) VALUES (:tmdb_id, :titulo, :description, :release_date, :poster, :banner, :status_type)");
        $stmt->bindParam(':tmdb_id', $tmdb_id);
        $stmt->bindParam(':titulo', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':release_date', $release_date);
        $stmt->bindParam(':poster', $poster);
        $stmt->bindParam(':banner', $banner);
        $stmt->bindParam(':status_type', $status_type);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Serie agregada exitosamente.";
            header('Location: manage_series.php');
            exit;
        } else {
            $_SESSION['error'] = "Hubo un error al agregar la serie. Inténtelo de nuevo.";
        }

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: add_series.php');
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Nueva Serie</title>
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
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                        Bienvenido al Panel de Administración
                    </h1>
                    <div class="flex space-x-4">
                        <a href="add_movie.php" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Nueva Película
                        </a>
                      
                    </div>
                </div>

    <div class="flex flex-col justify-center items-center min-h-screen p-4">
        <!-- Card Container -->
        <div class="w-full max-w-lg bg-gray-800 rounded-2xl shadow-2xl p-8 space-y-8 transform hover:scale-[1.01] transition-transform duration-300">
            <!-- Header -->
            <div class="text-center space-y-4">
                <i class="fas fa-tv text-4xl text-purple-500"></i>
                <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-purple-600">
                    Agregar Nueva Serie
                </h1>
                <p class="text-gray-400 text-sm">
                    Ingresa el ID de TMDB para agregar una nueva serie a tu colección
                </p>
            </div>

            <!-- Error Message -->
            <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-900/50 border border-red-500 text-red-200 px-4 py-3 rounded-lg relative" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['error']; ?></span>
                <?php unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form action="" method="POST" class="space-y-6">
                <div class="space-y-2">
                    <label for="tmdb_id" class="block text-sm font-medium text-gray-300">
                        ID de TMDB
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-hashtag text-gray-400"></i>
                        </div>
                        <input 
                            type="text" 
                            name="tmdb_id" 
                            id="tmdb_id" 
                            class="block bg-gray-700 w-full pl-10 pr-3 py-3 border border-gray-600 rounded-lg bg-gray-700/50 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200"
                            placeholder="Ej: 1396"
                            required
                        >
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        Puedes encontrar el ID en la URL de TMDB: themoviedb.org/tv/<span class="text-purple-400">1396</span>
                    </p>
                </div>

                <!-- Info Box -->
                <div class="bg-gray-700/30 rounded-lg p-4 border border-gray-600">
                    <h3 class="flex items-center text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-info-circle text-purple-400 mr-2"></i>
                        Información Importante
                    </h3>
                    <ul class="text-xs text-gray-400 space-y-1 list-disc list-inside">
                        <li>Se importarán todos los episodios disponibles</li>
                        <li>Las temporadas se actualizarán automáticamente</li>
                        <li>Asegúrate de tener el ID correcto de la serie</li>
                    </ul>
                </div>

                <!-- Buttons -->
                <div class="space-y-4">
                    <button 
                        type="submit" 
                        class="w-full bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white font-medium py-3 px-4 rounded-lg transition-all duration-200 transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 focus:ring-offset-gray-800"
                    >
                        <i class="fas fa-plus mr-2"></i>
                        Agregar Serie
                    </button>
                    
                    <a 
                        href="manage_series.php" 
                        class="block w-full text-center py-3 px-4 rounded-lg border border-gray-600 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors duration-200"
                    >
                        <i class="fas fa-arrow-left mr-2"></i>
                        Volver a Gestionar Series
                    </a>
                </div>
            </form>

            <!-- Help Text -->
            <div class="text-center text-xs text-gray-400">
                <p>¿Necesitas ayuda? <a href="#" class="text-purple-400 hover:underline">Consulta nuestra guía</a></p>
            </div>
        </div>

        <!-- Recently Added Series (Optional) -->
        <div class="mt-4 text-center text-sm text-gray-500">
            <p>Última serie agregada: <span class="text-purple-400">Breaking Bad</span></p>
        </div>
    </div>

    <!-- Loading Overlay (Hidden by default) -->
    <div id="loading-overlay" class="fixed inset-0 bg-gray-900/80 flex items-center justify-center hidden">
        <div class="text-center space-y-4">
            <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-purple-500"></div>
            <p class="text-purple-500">Procesando serie...</p>
            <p class="text-xs text-gray-400">Esto puede tomar unos momentos</p>
        </div>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function() {
            document.getElementById('loading-overlay').classList.remove('hidden');
        });
    </script>
</body>
</html>