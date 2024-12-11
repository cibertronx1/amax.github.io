
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
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != '1') {
    header('Location: login.php');
    exit;
}

// Verificar si se ha proporcionado el ID del episodio a eliminar
if (!isset($_GET['episode_id'])) {
    echo "ID del episodio no proporcionado.";
    exit;
}

$episode_id = (int)$_GET['episode_id'];

// Intentar eliminar el episodio de la base de datos
try {
    // Preparar la consulta para eliminar el episodio
    $stmt_delete = $conn->prepare("DELETE FROM web_series_episodes WHERE id = :episode_id");
    $stmt_delete->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);
    
    // Ejecutar la consulta
    if ($stmt_delete->execute()) {
        $_SESSION['message'] = "Episodio eliminado exitosamente.";
    } else {
        $_SESSION['error'] = "Hubo un error al eliminar el episodio. Inténtelo de nuevo.";
    }
} catch(PDOException $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

// Redirigir al usuario de vuelta a la pantalla de gestión de episodios
header("Location: manage_episodes.php?season_id=" . $_GET['season_id']);
exit;
?>
