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
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != '1') {
   header('Location: login.php');
    exit;
}

// Definir la paginación
$items_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $items_per_page;

// Contar el total de usuarios
$stmt = $conn->prepare("SELECT COUNT(*) FROM user_db");
$stmt->execute();
$total_users = $stmt->fetchColumn();
$total_pages = ceil($total_users / $items_per_page);

// Obtener usuarios para la página actual
$stmt = $conn->prepare("SELECT * FROM user_db ORDER BY id DESC LIMIT :offset, :items_per_page");
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':items_per_page', $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - Admin Panel</title>
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
            <div class="max-w-6xl mx-auto">

                <div class="flex justify-between items-center mb-8">
                
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent ">Gestionar Usuarios</h1>
                   
                    <!-- Buscador de usuarios -->
                    <div class="flex items-center space-x-4">
                        <form action="" method="GET" class="flex items-center">
                            <input type="text" name="search" placeholder="Buscar usuarios..." value="<?php echo htmlspecialchars($search ?? ''); ?>"

                                class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <button type="submit" class="ml-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">Buscar</button>
                        </form>
                    </div>

                    <!-- Te envia para agregar una peliculas -->
                    <a href="register.php" class="flex items-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i data-feather="plus" class="mr-2"></i>Agregar Nuevos Usuarios
                    </a>
                </div>
                </div>
            <div class="bg-gray-800 rounded-lg overflow-hidden shadow-xl">
                <table class="w-full">
                    <thead class="bg-gray-700 text-gray-300">
                        <tr>
                            <th class="border-b p-4">ID</th>
                            <th class="border-b p-4">Nombre</th>
                            <th class="border-b p-4">Correo Electrónico</th>
                            <th class="border-b p-4">Rol</th>
                            <th class="border-b p-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($users as $user) {
                            $role = $user['role_type'] == '1' ? 'Admin' : 'Usuario';

                            echo "<tr class='border-b border-gray-700 table-hover'>";
                            echo "<td class='p-4'>" . htmlspecialchars($user['id']) . "</td>";
                            echo "<td class='p-4'>" . htmlspecialchars($user['name']) . "</td>";
                            echo "<td class='p-4'>" . htmlspecialchars($user['email']) . "</td>";
                            echo "<td class='p-4'>" . htmlspecialchars($role) . "</td>";
                            echo "<td class='p-4 flex justify-center space-x-3'>";

                            echo "<a href='edit_user.php?id=" . htmlspecialchars($user['id']) . "' class='text-blue-400 hover:text-blue-300'><i data-feather='edit'>Editar</i></a>";
                            echo "<a href='delete_user.php?id=" . htmlspecialchars($user['id']) . "' class='text-red-400 hover:text-red-300'><i data-feather='trash-2'></i></a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="flex justify-between items-center mt-6">
                <div class="text-gray-400">
                    Página <?php echo $page; ?> de <?php echo $total_pages; ?>
                </div>
                <div class="flex space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Anterior</a>
                    <?php endif; ?> 
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Siguiente</a>
                    <?php endif; ?>
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
