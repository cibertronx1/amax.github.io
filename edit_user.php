
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

// Obtener información del usuario a editar
if (isset($_GET['id'])) {
    $userId = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM user_db WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['error'] = "Usuario no encontrado.";
        header('Location: manage_users.php');
        exit;
    }
} else {
    header('Location: manage_users.php');
    exit;
}

// Procesar el formulario de edición de usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role_type = $_POST['role_type'];

    $stmt = $conn->prepare("UPDATE user_db SET name = :name, email = :email, password = :password, role_type = :role_type WHERE id = :id");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email); 
    $stmt->bindParam(':password', $password);
    $stmt->bindParam(':role_type', $role_type);
    $stmt->bindParam(':id', $userId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Usuario actualizado exitosamente.";
        header('Location: manage_users.php');
        exit;
    } else {
        $_SESSION['error'] = "Hubo un error al actualizar el usuario. Inténtelo de nuevo.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
        
            background-color: #0f172a;
            color: #f3f4f6;
        }
    </style>
</head>
<body>
    <div class="flex justify-center items-center h-screen">
        <div class="w-full max-w-md bg-gray-800 p-8 rounded">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent text-center">Editar Usuario</h1>

            <?php
            if (isset($_SESSION['error'])) {
                echo "<p class='text-red-500 mb-4'>{$_SESSION['error']}</p>";
                unset($_SESSION['error']);
            }
            ?>

            <form action="" method="POST">
                <div class="mb-4">
                    <label for="name" class="block mb-2">Nombre</label>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="w-full p-3 rounded bg-gray-700" required>
                </div>
                <div class="mb-4">
                    <label for="email" class="block mb-2">Correo Electrónico</label>
                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" class="w-full p-3 rounded bg-gray-700" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="block mb-2">Password</label>
                    <input type="password" name="password" id="password" value="<?php echo htmlspecialchars($user['password']); ?>" class="w-full p-3 rounded bg-gray-700" required>
                </div>
                <div class="mb-4">
                    <label for="role_type" class="block mb-2">Rol</label>
                    <select name="role_type" id="role_type" class="w-full p-3 rounded bg-gray-700">
                        <option value="0" <?php echo $user['role_type'] == '0' ? 'selected' : ''; ?>>Usuario</option>
                        <option value="1" <?php echo $user['role_type'] == '1' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                <div class="mb-6">
                    <button type="submit" class="w-full bg-blue-500 p-3 rounded text-white hover:bg-blue-700">Actualizar Usuario</button>
                </div>
            </form>
            <p class="text-center"><a href="manage_users.php" class="text-blue-400 hover:underline">Volver a Gestionar Usuarios</a></p>
        </div>
    </div>
</body>
</html>