
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

// Procesar el formulario de restablecimiento de contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];

    // Verificar si el correo electrónico está registrado
    $stmt = $conn->prepare("SELECT * FROM user_db WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Generar un token para restablecer la contraseña (en este ejemplo simplificado, solo usamos el ID del usuario)
        $resetToken = md5($user['id'] . time());

        // Guardar el token en la sesión (en un proyecto real, sería mejor enviar un correo con un enlace de restablecimiento)
        $_SESSION['reset_token'] = $resetToken;
        $_SESSION['user_to_reset'] = $user['id'];
        $_SESSION['message'] = "Se ha enviado un enlace para restablecer la contraseña a su correo.";
        header('Location: reset_password.php');
        exit;
    } else {
        $_SESSION['error'] = "El correo electrónico no está registrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña</title>
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
            <h1 class="text-3xl text-center font-bold bg-clip-text text-transparent bg-gradient-to-r from-purple-400 to-purple-600">Recuperar Contraseña</h1>

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
             
                    <input type="email" name="email" id="email" class="w-full p-3 rounded bg-gray-700" placeholder="Enter email"required>
                </div>
                <div class="mb-6">
                    <button type="submit" class="w-full bg-blue-500 p-3 rounded text-white hover:bg-blue-700">Enviar Enlace de Recuperación</button>
                </div>
            </form>
            <p class="text-center">Ya tienes una cuenta? <a href="login.php" class="text-blue-400 hover:underline">Inicia Sesión</a></p>
        </div>
    </div>
</body>
</html>
