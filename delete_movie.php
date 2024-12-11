
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

// Verificar si el ID de la película está presente en la URL
if (isset($_GET['id'])) {
    $movieId = $_GET['id'];

    // Eliminar la película de la base de datos
    $stmt = $conn->prepare("DELETE FROM movies WHERE id = :id");
    $stmt->bindParam(':id', $movieId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Película eliminada exitosamente.";
    } else {
        $_SESSION['error'] = "Hubo un error al eliminar la película. Inténtelo de nuevo.";
    }
} else {
    $_SESSION['error'] = "ID de la película no proporcionado.";
}

// Redirigir a la página de gestión de películas
header('Location: manage_movies.php');
exit;
?>
