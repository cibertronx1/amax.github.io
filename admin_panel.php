
<?php
session_start();
// todo funciona bien, haga copia antes de hacer cambiossssssssssssss
// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "amax"; // Corrección en el nombre de la base de datos

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - User Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
        }
        .dashboard-card {
            background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
            transition: transform 0.2s;
        }
        .dashboard-card:hover {
            transform: translateY(-2px);
        }
        .nav-item {
            transition: all 0.2s;
        }
        .nav-item:hover {
            background: rgba(59, 130, 246, 0.1);
            transform: translateX(4px);
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
                        <li><a href=" episode_play_links.php" class="nav-item flex items-center space-x-3 text-gray-300 p-3 rounded-lg"><i class="fas fa-film w-5"></i><span>Gestionar links</span></a></li>
                        <li><a href="manage_series.php" class="nav-item flex items-center space-x-3 text-gray-300 p-3 rounded-lg"><i class="fas fa-video w-5"></i><span>Gestionar Series Web</span></a></li>
                        <li><a href="manage_users.php" class="nav-item flex items-center space-x-3 text-gray-300 p-3 rounded-lg"><i class="fas fa-users w-5"></i><span>Gestionar Usuarios</span></a></li>
                        <li class="mt-8"><a href="logout.php" class="nav-item flex items-center space-x-3 text-red-400 p-3 rounded-lg"><i class="fas fa-sign-out-alt w-5"></i><span>Cerrar Sesión</span></a></li>
                    </ul>
                </nav>
            </div>
        </div>


        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                        Bienvenido al Panel de Administración
                    </h1>
                    <div class="flex space-x-4">
                        <a href="add_movie.php" class="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Nueva Película
                        </a>
                        <a href="add_series.php" class="flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            <i class="fas fa-plus mr-2"></i>Nueva Serie
                        </a>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <?php
                    $stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM user_db");
                    $stmt->execute();
                    $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
                    ?>
                    <div class="dashboard-card p-6 rounded-xl shadow-lg">
                        <div class="flex items-center">
                            <div class="rounded-full p-3 bg-blue-500/10 mr-4">
                                <i class="fas fa-users text-blue-500 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-3xl font-bold text-gray-100"><?php echo $totalUsers; ?></h3>
                                <p class="text-gray-400">Usuarios Registrados</p>
                            </div>
                        </div>
                    </div>

                    <?php
                    $stmt = $conn->prepare("SELECT COUNT(*) as total_movies FROM movies");
                    $stmt->execute();
                    $totalMovies = $stmt->fetch(PDO::FETCH_ASSOC)['total_movies'];
                    ?>
                    <div class="dashboard-card p-6 rounded-xl shadow-lg">
                        <div class="flex items-center">
                            <div class="rounded-full p-3 bg-purple-500/10 mr-4">
                                <i class="fas fa-film text-purple-500 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-3xl font-bold text-gray-100"><?php echo $totalMovies; ?></h3>
                                <p class="text-gray-400">Total Películas</p>
                            </div>
                        </div>
                    </div>

                    <?php
                    $stmt = $conn->prepare("SELECT COUNT(*) as total_web_series FROM web_series");
                    $stmt->execute();
                    $totalWebSeries = $stmt->fetch(PDO::FETCH_ASSOC)['total_web_series'];
                    ?>
                    <div class="dashboard-card p-6 rounded-xl shadow-lg">
                        <div class="flex items-center">
                            <div class="rounded-full p-3 bg-green-500/10 mr-4">
                                <i class="fas fa-video text-green-500 text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-3xl font-bold text-gray-100"><?php echo $totalWebSeries; ?></h3>
                                <p class="text-gray-400">Total Series Web</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Series Table -->
                <div class="bg-slate-900 rounded-xl shadow-lg mb-8 overflow-hidden">
                    <div class="p-6 border-b border-gray-800">
                        <h4 class="text-xl font-semibold flex items-center">
                            <i class="fas fa-video text-purple-500 mr-2"></i>
                            Series Recientes
                        </h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">TMDB_ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Fecha de Lanzamiento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM web_series ORDER BY release_date DESC LIMIT 5");
                                $stmt->execute();
                                $series = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($series as $serie) {
                                    echo "<tr class='hover:bg-slate-800/50 transition-colors'>";
                                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-300'>{$serie['tmdb_id']}</td>";
                                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-300'>{$serie['titulo']}</td>";
                                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-300'>{$serie['release_date']}</td>";
                                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm'>
                                            <a href='edit_series.php?id={$serie['id']}' class='text-blue-500 hover:text-blue-400 mr-3'><i class='fas fa-edit mr-1'></i>Editar</a>
                                            <a href='delete_series.php?id={$serie['id']}' class='text-red-500 hover:text-red-400'><i class='fas fa-trash-alt mr-1'></i>Eliminar</a>
                                          </td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Movies Table -->
                <div class="bg-slate-900 rounded-xl shadow-lg overflow-hidden">
                    <div class="p-6 border-b border-gray-800">
                        <h4 class="text-xl font-semibold flex items-center">
                            <i class="fas fa-film text-blue-500 mr-2"></i>
                            Películas Recientes
                        </h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-slate-800">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">TMDB_ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Fecha de Lanzamiento</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                <?php
                                $stmt = $conn->prepare("SELECT * FROM movies ORDER BY release_date DESC LIMIT 5");
                                $stmt->execute();
                                $movies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($movies as $movie) {
                                    echo "<tr class='hover:bg-slate-800/50 transition-colors'>";
                                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-300'>{$movie['tmdb_id']}</td>";
                                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-300'>{$movie['titulo']}</td>";
                                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm text-gray-300'>{$movie['release_date']}</td>";
                                    echo "<td class='px-6 py-4 whitespace-nowrap text-sm'>
                                            <a href='edit_movie.php?id={$movie['id']}' class='text-blue-500 hover:text-blue-400 mr-3'><i class='fas fa-edit mr-1'></i>Editar</a>
                                            <a href='delete_movie.php?id={$movie['id']}' class='text-red-500 hover:text-red-400'><i class='fas fa-trash-alt mr-1'></i>Eliminar</a>
                                          </td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>