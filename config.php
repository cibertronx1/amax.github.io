<?php
session_start();

// Configuración de conexión a la base de datos
$servername = "localhost:3306/";
$username = "root";
$password = "";
$dbname = "amax";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Error de conexión: " . $e->getMessage());
    header("Location: error.php?error=conexion");
    exit;
}

// Configuración global de paginación
$limit = 20;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Función genérica para obtener registros
function obtenerRegistros($conn, $tabla, $limit, $offset, $condiciones = []) {
    $permitidos = ['web_series', 'movies'];
    if (!in_array($tabla, $permitidos)) {
        throw new Exception("Tabla no permitida: $tabla");
    }

    $sql = "SELECT * FROM $tabla";
    $params = [];

    // Agregar condiciones si existen
    if (!empty($condiciones)) {
        $sql .= ' WHERE ' . implode(' AND ', array_keys($condiciones));
        $params = array_values($condiciones);
    }

    $sql .= " ORDER BY release_date DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value);
    }

    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para búsqueda combinada corregida
function buscarMedia($conn, $search, $limit, $offset) {
    $stmt = $conn->prepare("
        SELECT * FROM (
            SELECT 'web_series' AS media_type, tmdb_id, poster, titulo, release_date
            FROM web_series
            WHERE LOWER(titulo) LIKE LOWER(:search)
            UNION
            SELECT 'movie' AS media_type, tmdb_id, poster, titulo, release_date
            FROM movies
            WHERE LOWER(titulo) LIKE LOWER(:search)
        ) AS combined
        ORDER BY release_date DESC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Inicialización de variables
$web_series = [];
$movies = [];
$media_results = [];
$total_search_results = 0;
$total_pages_search = 0;
$total_series = 0;
$total_pages_series = 0;
$total_movies = 0;
$total_pages_movies = 0;

// Obtener el tipo de contenido si está definido
$tipo_contenido = isset($_GET['type']) ? $_GET['type'] : null;

// Manejo de búsqueda
if (isset($_GET['titulo']) && !empty($_GET['titulo'])) {
    try {
        $search_term = $_GET['titulo'];
        $media_results = buscarMedia($conn, $search_term, $limit, $offset);

        // Contar el total de resultados para la búsqueda
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM (
                SELECT tmdb_id FROM web_series WHERE LOWER(titulo) LIKE LOWER(:search)
                UNION ALL
                SELECT tmdb_id FROM movies WHERE LOWER(titulo) LIKE LOWER(:search)
            ) AS combined
        ");
        $stmt->bindValue(':search', "%$search_term%", PDO::PARAM_STR);
        $stmt->execute();
        $total_search_results = $stmt->fetchColumn();
        $total_pages_search = ceil($total_search_results / $limit);
    } catch (Exception $e) {
        error_log("Error en la búsqueda: " . $e->getMessage());
    }
} else {
    // Obtener series y películas por defecto
    try {
        $web_series = obtenerRegistros($conn, 'web_series', $limit, $offset);
        $movies = obtenerRegistros($conn, 'movies', $limit, $offset);

        // Cálculo de paginación para series
        $total_series = $conn->query("SELECT COUNT(*) FROM web_series")->fetchColumn();
        $total_pages_series = ceil($total_series / $limit);

        // Cálculo de paginación para películas
        $total_movies = $conn->query("SELECT COUNT(*) FROM movies")->fetchColumn();
        $total_pages_movies = ceil($total_movies / $limit);
    } catch (Exception $e) {
        error_log("Error al obtener registros: " . $e->getMessage());
        $web_series = $movies = [];
    }
}

// Manejo de detalles de series o películas
if ($tipo_contenido === 'series' || $tipo_contenido === 'movie') {
    if (!isset($_GET['tmdb_id'])) {
        header("Location: 404.php?type=$tipo_contenido");
        exit;
    }

    $tmdb_id_param = $_GET['tmdb_id'];
    $tmdb_parts = explode('-', $tmdb_id_param);

    $tmdb_id = (int)$tmdb_parts[0];
    $season_order = count($tmdb_parts) === 2 ? (int)$tmdb_parts[1] : 1;

    if ($tmdb_id <= 0) {
        header("Location: 404.php?type=$tipo_contenido");
        exit;
    }

    if ($tipo_contenido === 'series') {
        // Obtener el ID de la serie en la base de datos
        $stmt_get_series = $conn->prepare("SELECT tmdb_id, titulo FROM web_series WHERE tmdb_id = :tmdb_id");
        $stmt_get_series->bindParam(':tmdb_id', $tmdb_id, PDO::PARAM_INT);
        $stmt_get_series->execute();
        $series = $stmt_get_series->fetch(PDO::FETCH_ASSOC);

        if (!$series) {
            header("Location: error.php?type=series");
            exit;
        }

        $web_series_id = $series['tmdb_id'];
        $series_name = $series['titulo'];

        // Obtener todas las temporadas de la serie
        $stmt_seasons = $conn->prepare("SELECT * FROM temporada WHERE web_series_id = :web_series_id ORDER BY season_order ASC");
        $stmt_seasons->bindParam(':web_series_id', $web_series_id, PDO::PARAM_INT);
        $stmt_seasons->execute();
        $seasons = $stmt_seasons->fetchAll(PDO::FETCH_ASSOC);

        // Obtener detalles de la temporada seleccionada
        $season_order = isset($_GET['season_order']) ? (int)$_GET['season_order'] : 1;
        $stmt_season = $conn->prepare("SELECT * FROM temporada WHERE web_series_id = :web_series_id AND season_order = :season_order");
        $stmt_season->bindParam(':web_series_id', $web_series_id, PDO::PARAM_INT);
        $stmt_season->bindParam(':season_order', $season_order, PDO::PARAM_INT);
        $stmt_season->execute();
        $season = $stmt_season->fetch(PDO::FETCH_ASSOC);

        if (!$season) {
            header("Location: 404.php?type=series");
            exit;
        }

        $season_id = $season['id'];
        $season_name = $season['season_name'];
        $season_description = $season['description'] ?? "Sin descripción disponible.";

        // Obtener los episodios de la temporada seleccionada
        $stmt_episodes = $conn->prepare("SELECT * FROM web_series_episodes WHERE season_id = :season_id ORDER BY episode_order ASC");
        $stmt_episodes->bindParam(':season_id', $season_id, PDO::PARAM_INT);
        $stmt_episodes->execute();
        $episodes = $stmt_episodes->fetchAll(PDO::FETCH_ASSOC);

        // Crear un array para almacenar episodios y sus enlaces de reproducción
        $episodes_with_links = [];

        foreach ($episodes as $episode) {
            $episode_id = $episode['id'];
            $stmt_links = $conn->prepare("SELECT * FROM episode_play_links WHERE episode_id = :episode_id ORDER BY link_order ASC");
            $stmt_links->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);
            $stmt_links->execute();
            $play_links = $stmt_links->fetchAll(PDO::FETCH_ASSOC);

            // Añadir el enlace por defecto
            $default_link = [
                'url' => "https://vidsrc.xyz/embed/tv/" . htmlspecialchars($tmdb_id, ENT_QUOTES, 'UTF-8') . "?s=" . htmlspecialchars($season_order, ENT_QUOTES, 'UTF-8') . "&e=" . htmlspecialchars($episode['episode_order'], ENT_QUOTES, 'UTF-8'),
                'name' => 'Vidsrc.xyz',
                'quality' => 'Ingles'
            ];

            $default_links = [
                'url' => "https://www.2embed.cc/embedtv/" . htmlspecialchars($tmdb_id, ENT_QUOTES, 'UTF-8') . "?s=" . htmlspecialchars($season_order, ENT_QUOTES, 'UTF-8') . "&e=" . htmlspecialchars($episode['episode_order'], ENT_QUOTES, 'UTF-8'),
                'name' => 'www.2embed.cc',
                'quality' => 'Ingles'
            ];

            array_unshift($play_links, $default_link, $default_links);
            $episode['play_links'] = $play_links;
            $episodes_with_links[] = $episode;
        }
    } elseif ($tipo_contenido === 'movie') {
        // Obtener detalles de la película desde la base de datos usando el TMDB_ID
        $stmt_movie = $conn->prepare("SELECT * FROM movies WHERE tmdb_id = :tmdb_id");
        $stmt_movie->bindParam(':tmdb_id', $tmdb_id, PDO::PARAM_INT);
        $stmt_movie->execute();
        $movie = $stmt_movie->fetch(PDO::FETCH_ASSOC);

        if (!$movie) {
            header('Location: 404.php?error=movie');
            exit;
        }

        $movie_id = $movie['tmdb_id'];

        // Obtener enlaces de reproducción para la película
        $stmt_links = $conn->prepare("SELECT * FROM movie_play_links WHERE movie_id = :movie_id ORDER BY link_order ASC");
        $stmt_links->bindParam(':movie_id', $movie_id, PDO::PARAM_INT);
        $stmt_links->execute();
        $play_links = $stmt_links->fetchAll(PDO::FETCH_ASSOC);

        // Añadir enlaces por defecto
        $default_link = [
            "name" => "Opcion-1",
            "quality" => "1080P",
            "url" => "https://vidsrc.xyz/embed/" . $tmdb_id
        ];

        $default_links = [
            'url' => "https://www.2embed.cc/embed/" . $tmdb_id,
            'name' => 'Opcion-2',
            'quality' => '1080P'
        ];
        array_unshift($play_links, $default_link, $default_links);

        $movie['play_links'] = $play_links;
    }
}

// Manejo adicional de búsqueda por título (si es necesario)
$search_query = trim($_GET['titulo'] ?? '');
if ($search_query !== '') {
    try {
        // Buscar en películas
        $stmt_movies = $conn->prepare("SELECT * FROM movies WHERE LOWER(titulo) LIKE LOWER(:search)");
        $stmt_movies->bindValue(':search', "%$search_query%", PDO::PARAM_STR);
        $stmt_movies->execute();
        $movies = $stmt_movies->fetchAll();

        // Buscar en series
        $stmt_series = $conn->prepare("SELECT * FROM web_series WHERE LOWER(titulo) LIKE LOWER(:search)");
        $stmt_series->bindValue(':search', "%$search_query%", PDO::PARAM_STR);
        $stmt_series->execute();
        $web_series = $stmt_series->fetchAll();
    } catch (Exception $e) {
        error_log("Error en la búsqueda por título: " . $e->getMessage());
    }
}

// Obtener los datos del slider desde la base de datos
$stmt_slider = $conn->prepare("SELECT * FROM slider ORDER BY sort_order ASC LIMIT 8");
$stmt_slider->execute();
$slider_items = $stmt_slider->fetchAll(PDO::FETCH_ASSOC);


////////////

// Obtener contenidos sugeridos (por ejemplo, los 3 lanzamientos más recientes de películas y las 3 series más recientes)
$sugerencias_peliculas = $conn->query("
    SELECT 'movie' as type, id, tmdb_id, titulo, poster, release_date 
    FROM movies
    ORDER BY release_date DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

$sugerencias_series = $conn->query("
    SELECT 'web_series' as type, id, tmdb_id, titulo, poster, release_date 
    FROM web_series
    ORDER BY release_date DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

// Combinar sugerencias en un solo array
$sugerencias = array_merge($sugerencias_peliculas, $sugerencias_series);

/////////
?>
