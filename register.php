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

// Procesar el formulario de registro
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Verificar si el nombre de usuario ya existe en la base de datos
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM user_db WHERE name = :name");
    $stmt_check->bindParam(':name', $name, PDO::PARAM_STR);
    $stmt_check->execute();
    $count = $stmt_check->fetchColumn();

    if ($count > 0) {
        $_SESSION['error'] = " Este usuario ya está registrado, te sugerimos agregar un sufijo al tuyo.";
        header('Location: register.php');
        exit;
    }

    // Insertar un nuevo usuario en la base de datos
    $stmt = $conn->prepare("INSERT INTO user_db (name, email, password, role_type) VALUES (:name, :email, :password, '0')");
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':password', $password);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Registro exitoso. Por favor, inicie sesión.";
        header('Location: login.php');
        exit;
    } else {
        $_SESSION['error'] = "Hubo un error en el registro. Inténtelo de nuevo.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Usuario</title>
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
            <h1 class="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-purple-600 text-center">Registro de Usuario</h1>

            <?php
            if (isset($_SESSION['error'])) {
                echo "<p class='text-red-500 mb-4'>{$_SESSION['error']}</p>";
                unset($_SESSION['error']);
            }
            ?>

            <form action="" method="POST">
                <div class="mb-4">
                    <label for="name" class="block mb-2">Nombre</label>
                    <input type="text" name="name" id="name" class="w-full p-3 rounded bg-gray-700" required>
                </div>
                <div class="mb-4">
                    <label for="email" class="block mb-2">Correo Electrónico</label>
                    <input type="email" name="email" id="email" class="w-full p-3 rounded bg-gray-700" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="block mb-2">Contraseña</label>
                    <input type="password" name="password" id="password" class="w-full p-3 rounded bg-gray-700" required>
                </div>
                <div class="mb-6">
                    <button type="submit" class="w-full bg-blue-500 p-3 rounded text-white hover:bg-blue-700">Registrarse</button>
                </div>
            </form>
            <p class="text-center">Ya tienes una cuenta? <a href="login.php" class="text-blue-400 hover:underline">Inicia Sesión</a></p>
        </div>
    </div>
</body>
</html>
