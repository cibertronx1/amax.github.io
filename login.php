<?php
session_start();
//login.php

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "amax"; // Corregido el nombre de la base de datos

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}

// Procesar el formulario de inicio de sesión
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Buscar al usuario en la base de datos
    $stmt = $conn->prepare("SELECT * FROM user_db WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Verificar la contraseña
        if (password_verify($password, $user['password'])) {
            // Establecer variables de sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role_type'];
            $_SESSION['user_name'] = $user['name'];
            header('Location: admin_panel.php');
            exit;
        } else {
            $_SESSION['error'] = "Correo electrónico o contraseña incorrectos.";
        }
    } else {
        $_SESSION['error'] = "Usuario no encontrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
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
            <h1 class="text-3xl text-center font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-purple-600">Iniciar Sesión</h1>

            <?php
            if (isset($_SESSION['error'])) {
                echo "<p class='text-red-500 mb-4'>{$_SESSION['error']}</p>";
                unset($_SESSION['error']);
            }
            ?>

            <form action="" method="POST">
                <div class="mb-4">
                    
                    <input type="email" name="email" id="email" class="w-full p-3 rounded bg-gray-700"placeholder="Enter email" required>

                </div>
                <div class="mb-4">
                  
                    <input type="password" name="password" id="password" class="w-full p-3 rounded bg-gray-700"  placeholder="Enter password" required>
                </div>
                <div class="mb-6">
                    <button type="submit" class="w-full bg-blue-500 p-3 rounded text-white hover:bg-blue-700">Iniciar Sesión</button>
                </div>
            </form>
            <p class="text-center">Olvidaste tu contraseña? <a href="forgot_pass.php" class="text-blue-400 hover:underline">Recupérala aquí</a></p>
            <p class="text-center">No tienes una cuenta? <a href="register.php" class="text-blue-400 hover:underline">Regístrate aquí</a></p>
        </div>
    </div>
</body>
</html>
