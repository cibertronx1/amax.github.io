
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

// Verificar si se ha proporcionado el ID de la serie
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "ID de serie no proporcionado.";
    header('Location: manage_series.php');
    exit;
}

$series_id = (int)$_GET['id'];

// Eliminar la serie y todas sus relaciones en la base de datos
try {
    // Iniciar una transacción
    $conn->beginTransaction();

    // Eliminar los episodios asociados con las temporadas de la serie
    $stmt_delete_episodes = $conn->prepare("DELETE web_series_episodes FROM web_series_episodes INNER JOIN web_series_seasons ON web_series_episodes.season_id = web_series_seasons.id WHERE web_series_seasons.web_series_id = :series_id");
    $stmt_delete_episodes->bindParam(':series_id', $series_id, PDO::PARAM_INT);
    $stmt_delete_episodes->execute();

    // Eliminar las temporadas de la serie
    $stmt_delete_seasons = $conn->prepare("DELETE FROM web_series_seasons WHERE web_series_id = :series_id");
    $stmt_delete_seasons->bindParam(':series_id', $series_id, PDO::PARAM_INT);
    $stmt_delete_seasons->execute();

    // Eliminar los géneros asociados a la serie
    $stmt_delete_genres = $conn->prepare("DELETE FROM series_genres WHERE series_id = :series_id");
    $stmt_delete_genres->bindParam(':series_id', $series_id, PDO::PARAM_INT);
    $stmt_delete_genres->execute();

    // Eliminar la serie en sí
    $stmt_delete_series = $conn->prepare("DELETE FROM web_series WHERE id = :series_id");
    $stmt_delete_series->bindParam(':series_id', $series_id, PDO::PARAM_INT);
    $stmt_delete_series->execute();

    // Confirmar la transacción
    $conn->commit();

    $_SESSION['message'] = "Serie eliminada exitosamente.";
    header('Location: manage_series.php');
    exit;
} catch (PDOException $e) {
    // Revertir la transacción en caso de error
    $conn->rollBack();
    $_SESSION['error'] = "Error al eliminar la serie: " . $e->getMessage();
    header('Location: manage_series.php');
    exit;
}
?>
