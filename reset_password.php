
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

// Verificar si el token de restablecimiento es válido
if (!isset($_SESSION['reset_token']) || !isset($_SESSION['user_to_reset'])) {
    $_SESSION['error'] = "Token de restablecimiento no válido.";
    header('Location: forgot_pass.php');
    exit;
}

// Procesar el formulario de restablecimiento de contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Las contraseñas no coinciden.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $userId = $_SESSION['user_to_reset'];

        // Actualizar la contraseña en la base de datos
        $stmt = $conn->prepare("UPDATE user_db SET password = :password WHERE id = :id");
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $userId);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Contraseña actualizada exitosamente.";
            // Limpiar token de sesión
            unset($_SESSION['reset_token']);
            unset($_SESSION['user_to_reset']);
            header('Location: login.php');
            exit;
        } else {
            $_SESSION['error'] = "Hubo un error al actualizar la contraseña. Inténtelo de nuevo.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
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
            <h1 class="text-3xl text-center font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-purple-600">Restablecer Contraseña</h1>

            <?php
            if (isset($_SESSION['error'])) {
                echo "<p class='text-red-500 mb-4'>{$_SESSION['error']}</p>";
                unset($_SESSION['error']);
            }

            if (isset($_SESSION['message'])) {
                echo "<p class='text-green-500 mb-4'>{$_SESSION['message']}</p>";
                unset($_SESSION['message']);
            }
            ?>

            <form action="" method="POST">
                <div class="mb-4">
                    <label for="new_password" class="block mb-2">Nueva Contraseña</label>
                    <input type="password" name="new_password" id="new_password" class="w-full p-3 rounded bg-gray-700" required>
                </div>
                <div class="mb-4">
                    <label for="confirm_password" class="block mb-2">Confirmar Nueva Contraseña</label>
                    <input type="password" name="confirm_password" id="confirm_password" class="w-full p-3 rounded bg-gray-700" required>
                </div>
                <div class="mb-6">
                    <button type="submit" class="w-full bg-blue-500 p-3 rounded text-white hover:bg-blue-700">Restablecer Contraseña</button>
                </div>
            </form>
            <p class="text-center"><a href="login.php" class="text-blue-400 hover:underline">Volver al Inicio de Sesión</a></p>
        </div>
    </div>
</body>
</html>
