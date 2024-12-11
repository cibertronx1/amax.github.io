<?php
// get_seasons.php 
session_start();

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "amax";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}

// Variables de búsqueda global
$search_query = isset($_GET['titulo']) ? trim($_GET['titulo']) : '';
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$media_results = [];
$total_pages_search = 0;

// Si hay búsqueda, ejecutar la consulta global
if (!empty($search_query)) {
    // Consulta UNION para películas y series
    $stmt = $conn->prepare("
        (SELECT 'web_series' AS media_type, tmdb_id, titulo, poster, release_date
         FROM web_series
         WHERE LOWER(titulo) LIKE LOWER(:search))
        UNION
        (SELECT 'movie' AS media_type, tmdb_id, titulo, poster, release_date
         FROM movies
         WHERE LOWER(titulo) LIKE LOWER(:search))
        ORDER BY release_date DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':search', "%$search_query%", PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $media_results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contar resultados totales
    $stmt_count = $conn->prepare("
        SELECT COUNT(*) FROM (
            (SELECT tmdb_id FROM web_series WHERE LOWER(titulo) LIKE LOWER(:search))
            UNION ALL
            (SELECT tmdb_id FROM movies WHERE LOWER(titulo) LIKE LOWER(:search))
        ) AS combined
    ");
    $stmt_count->bindValue(':search', "%$search_query%", PDO::PARAM_STR);
    $stmt_count->execute();
    $total_search_results = $stmt_count->fetchColumn();
    $total_pages_search = ceil($total_search_results / $limit);
}

// Si no hay búsqueda, proceder con la lógica de temporada/episodios
if (empty($search_query)) {
    // Verificar que se haya pasado tmdb_id
    if (!isset($_GET['tmdb_id'])) {
        header("Location: series.php?type=series");
        exit;
    }

    // Obtener y validar el ID de la serie
    $tmdb_id_param = $_GET['tmdb_id'];
    $tmdb_parts = explode('-', $tmdb_id_param);

    $tmdb_id = (int)$tmdb_parts[0];
    $season_order = (count($tmdb_parts) === 2) ? (int)$tmdb_parts[1] : 1;

    if ($tmdb_id <= 0) {
        header("Location: series.php?type=series");
        exit;
    }

    // Obtener el ID de la serie en la base de datos
    $stmt_get_series = $conn->prepare("SELECT id, titulo FROM web_series WHERE tmdb_id = :tmdb_id");
    $stmt_get_series->bindParam(':tmdb_id', $tmdb_id, PDO::PARAM_INT);
    $stmt_get_series->execute();
    $series = $stmt_get_series->fetch(PDO::FETCH_ASSOC);

    if (!$series) {
        header("Location: series.php?type=series");
        exit;
    }

    $web_series_id = $series['id'];
    $series_name = $series['titulo'];

    // Obtener todas las temporadas
    $stmt_seasons = $conn->prepare("SELECT * FROM temporada WHERE web_series_id = :web_series_id ORDER BY season_order ASC");
    $stmt_seasons->bindParam(':web_series_id', $web_series_id, PDO::PARAM_INT);
    $stmt_seasons->execute();
    $seasons = $stmt_seasons->fetchAll(PDO::FETCH_ASSOC);

    // Sobre escribir season_order si se envía por GET
    if (isset($_GET['season_order'])) {
        $season_order = (int)$_GET['season_order'];
    }

    $stmt_season = $conn->prepare("SELECT * FROM temporada WHERE web_series_id = :web_series_id AND season_order = :season_order");
    $stmt_season->bindParam(':web_series_id', $web_series_id, PDO::PARAM_INT);
    $stmt_season->bindParam(':season_order', $season_order, PDO::PARAM_INT);
    $stmt_season->execute();
    $season = $stmt_season->fetch(PDO::FETCH_ASSOC);

    if (!$season) {
        header("Location: series.php?type=series");
        exit;
    }

    $season_id = $season['id'];
    $season_name = $season['season_name'];
    $season_description = $season['description'] ?? "Sin descripción disponible.";

    // Obtener episodios
    $stmt_episodes = $conn->prepare("SELECT * FROM web_series_episodes WHERE season_id = :season_id ORDER BY episode_order ASC");
    $stmt_episodes->bindParam(':season_id', $season_id, PDO::PARAM_INT);
    $stmt_episodes->execute();
    $episodes = $stmt_episodes->fetchAll(PDO::FETCH_ASSOC);

    $episodes_with_links = [];

    foreach ($episodes as $episode) {
        $episode_id = $episode['id'];
        $stmt_links = $conn->prepare("SELECT * FROM episode_play_links WHERE episode_id = :episode_id ORDER BY link_order ASC");
        $stmt_links->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);
        $stmt_links->execute();
        $play_links = $stmt_links->fetchAll(PDO::FETCH_ASSOC);

        // Enlace por defecto
        // Añadir el enlace por defecto
        $default_link = [
            'url' => "https://vidsrc.xyz/embed/tv/" . htmlspecialchars($tmdb_id, ENT_QUOTES, 'UTF-8') . "?s=" . htmlspecialchars($season_order, ENT_QUOTES, 'UTF-8') . "&e=" . htmlspecialchars($episode['episode_order'], ENT_QUOTES, 'UTF-8'),
            'name' => 'Opcion-1',
            'quality' => 'Ingles'
        ];

        $default_links = [
            'url' => "https://www.2embed.cc/embedtv/" . htmlspecialchars($tmdb_id, ENT_QUOTES, 'UTF-8') . "?s=" . htmlspecialchars($season_order, ENT_QUOTES, 'UTF-8') . "&e=" . htmlspecialchars($episode['episode_order'], ENT_QUOTES, 'UTF-8'),
            'name' => 'Opcion-2',
            'quality' => 'Ingles'
        ];

        array_unshift($play_links, $default_link, $default_links);
        $episode['play_links'] = $play_links;
        $episodes_with_links[] = $episode;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <title>
        <?php if (!empty($search_query)): ?>
            Resultados de Búsqueda - Amax Streaming
        <?php else: ?>
            Detalle de Temporada - <?php echo htmlspecialchars($series_name, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars($season_name, ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
    </title>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
        }
        .modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 50;
            background-color: #1e293b;
            border-radius: 1rem;
            overflow: hidden;
            max-width: 800px;
            width: 90%;
            max-height: 90%;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
        }
        .backdrop-blur {
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .modal.active {
            display: block;
        }
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.75);
        }
        body.modal-open {
            overflow: hidden;
        }
        .gradient-text {
            background: linear-gradient(to right, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        @media (max-width: 768px) {
            .modal-content {
                padding: 0.5rem;
            }
        }
        footer {
            background-color: #0f172a;
            color: #9ca3af;
            padding: 1rem;
            text-align: center;
            margin-top: 2rem;
            position: fixed;
        }
    </style>
</head>
<body class="antialiased">
    <header class="w-full">
        <nav class="bg-white bg-opacity-75 backdrop-filter backdrop-blur-lg shadow-md fixed w-full z-50 bg-gray-900 border-b border-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <!-- Logo/Title -->
                    <div class="flex-shrink-0">
                        <h1 class="text-3xl md:text-4xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                            Amax
                        </h1>
                    </div>
                    <!-- Navigation Links - Desktop -->
                    <div class="hidden md:block">
                        <div class="flex items-center space-x-4">
                            <a href="movies.php" class="text-white hover:bg-gray-700 px-3 py-2 rounded-md text-sm font-medium">
                                Peliculas
                            </a>
                            <a href="series.php" class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-sm font-medium">
                                Series
                            </a>
                        </div>
                    </div>
                    <!-- Search Bar -->
                    <div class="flex-1 flex justify-center px-2 lg:ml-6 lg:justify-end">
                        <div class="max-w-lg w-full lg:max-w-xs">
                            <form class="flex items-center" method="GET" action="">
                                <input type="text" 
                                       name="titulo"
                                       class="block w-full px-4 py-2 text-gray-300 bg-gray-700 border border-gray-600 rounded-l-md focus:ring-blue-500 focus:border-blue-500" 
                                       placeholder="Buscar..."
                                       aria-label="Search"
                                       value="<?php echo htmlspecialchars($search_query ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if (!empty($_GET['tmdb_id'])): ?>
                                    <input type="hidden" name="tmdb_id" value="<?php echo htmlspecialchars($_GET['tmdb_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <?php if (!empty($_GET['season_order'])): ?>
                                    <input type="hidden" name="season_order" value="<?php echo htmlspecialchars($_GET['season_order'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php endif; ?>
                                <button type="submit" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-r-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Buscar
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- Mobile menu button -->
                    <div class="md:hidden flex items-center">
                        <button type="button" 
                                id="mobile-menu-button">
                                <div class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                                    aria-controls="mobile-menu"
                                    aria-expanded="false">
                            <span class="sr-only">Abrir menú principal</span>
                            <!-- Icono cuando el menú está cerrado -->
                            <svg class="block h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <!-- Icono cuando el menú está abierto -->
                            <svg class="hidden h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Mobile menu, oculto por defecto -->
            <div class="md:hidden hidden" id="mobile-menu">
                <div class="px-2 pt-2 pb-3 space-y-1">
                    <a href="movies.php" class="text-white block px-3 py-2 rounded-md text-base font-medium hover:bg-gray-700">
                        Peliculas
                    </a>
                    <a href="series.php" class="text-gray-300 block px-3 py-2 rounded-md text-base font-medium hover:bg-gray-700 hover:text-white">
                        Series
                    </a>
                </div>
            </div>
        </nav>
    </header>

    <!-- Espaciado para el nav -->
    <div class="pt-20"></div>

    <?php if (!empty($search_query)): ?>
        <!-- Resultados de la Búsqueda -->
        <div class="container mx-auto px-6 py-8">
            <h2 class="text-3xl font-bold mb-4">Resultados de Búsqueda para: "<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>"</h2>
            <?php if (!empty($media_results)): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($media_results as $item): ?>
                        <?php
                            $poster = $item['poster'] ?? "https://via.placeholder.com/300x450?text=" . urlencode($item['titulo']);
                            if ($item['media_type'] === 'movie') {
                                $url = "get_movie.php?type=movie&tmdb_id=" . urlencode($item['tmdb_id']);
                            } elseif ($item['media_type'] === 'web_series') {
                                $url = "get_seasons.php?type=series&tmdb_id=" . urlencode($item['tmdb_id']);
                            } else {
                                $url = "#";
                            }
                        ?>
                        <div class="card relative group rounded-lg overflow-hidden shadow-lg">
                            <img src="<?php echo htmlspecialchars($poster, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-80 object-cover">
                            <div class="absolute inset-0 bg-gray-900 bg-opacity-75 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 p-4 text-center">
                                <h3 class="text-lg font-bold mb-2"><?php echo htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="text-sm mb-4"><?php echo htmlspecialchars($item['release_date'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <a href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" class="block mt-4 text-white hover:text-gray-300">
                                    <button 
                                        class="play-button w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors flex items-center justify-center gap-2"
                                    >
                                        <i class="fas fa-play"></i>
                                        <span>Ver Ahora</span>
                                    </button>
                                </a>
                            </div>
                            <p class="text-gray-400 text-center mt-2"><?php echo htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Paginación para la búsqueda -->
                <div class="mt-6 flex justify-center space-x-4">
                    <?php if ($page > 1): ?>
                        <a href="?titulo=<?php echo urlencode($search_query); ?>&page=<?php echo $page - 1; ?>" class="px-4 py-2 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600">Anterior</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages_search): ?>
                        <a href="?titulo=<?php echo urlencode($search_query); ?>&page=<?php echo $page + 1; ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Siguiente</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-300">No se encontraron resultados.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <!-- Mostrar las temporadas y episodios como antes -->
        <main class="max-w-6xl mx-auto p-6">
            <div class="mb-6">
                <label for="seasonSelector" class="block text-lg font-medium mb-2">Seleccionar Temporada:</label>
                <select id="seasonSelector" class="bg-gray-700 text-white px-4 py-2 rounded-lg" onchange="changeSeason()">
                    <?php foreach ($seasons as $season_option): ?>
                        <option value="<?php echo $season_option['season_order']; ?>" <?php echo $season_option['season_order'] == $season_order ? 'selected' : ''; ?>>
                            Temporada <?php echo htmlspecialchars($season_option['season_order'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </main>

        <div class="max-w-6xl mx-auto bg-gray-800/60 rounded-3xl overflow-hidden shadow-2xl backdrop-blur-lg">
            <div class="bg-gray-900/60 p-8">
                <h2 class="text-3xl font-bold mb-6 text-center">Episodios de <?php echo htmlspecialchars($season_name, ENT_QUOTES, 'UTF-8'); ?></h2>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($episodes_with_links as $episode): ?>
                    <div class="bg-gray-800 rounded-2xl overflow-hidden shadow-lg transform transition-all duration-300 hover:scale-105 hover:shadow-2xl">
                        <div class="relative">
                            <img src="<?php echo htmlspecialchars($episode['episode_image'] ?? 'placeholder-episode.jpg', ENT_QUOTES, 'UTF-8'); ?>" 
                                 alt="Episodio <?php echo htmlspecialchars($episode['episode_order'], ENT_QUOTES, 'UTF-8'); ?>" 
                                 class="w-full h-48 object-cover">
                            <div class="absolute top-2 right-2 bg-black/60 px-3 py-1 rounded-full text-sm">
                                Episodio <?php echo htmlspecialchars($episode['episode_order'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </div>
                        <div class="p-5">
                            <h3 class="text-xl font-semibold mb-2"><?php echo htmlspecialchars($episode['episode_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p class="text-gray-400 mb-4 text-sm">
                                <?php echo htmlspecialchars($episode['episode_description'] ?? 'Sinopsis no disponible.', ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <?php if (!empty($episode['play_links'])): ?>
                                <select id="linkSelector_<?php echo $episode['id']; ?>" class="w-full mb-3 bg-gray-700 text-white rounded-lg">
                                    <?php foreach ($episode['play_links'] as $link): ?>
                                        <option value="<?php echo htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($link['name'] . ' (' . $link['quality'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button onclick="openVideo('linkSelector_<?php echo $episode['id']; ?>')" class="block w-full text-center bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-full transition-colors">
                                    Ver Ahora
                                </button>
                            <?php else: ?>
                                <button disabled class="block w-full text-center bg-gray-600 text-white py-2 rounded-full transition-colors">
                                    No Disponible
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal del reproductor de video -->
    <div id="videoModal" class="modal">
        <div class="p-4">
            <div class="flex justify-between items-center border-b border-gray-700 pb-4">
                <h3 id="modalTitle" class="text-xl font-bold">Espere hasta que se cargue el buffer...</h3>
                <button onclick="closeVideo()" class="text-gray-400 hover:text-white transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div id="videoPlayerContainer" class="mt-4">
                <!-- El iframe se inyectará aquí -->
            </div>
        </div>
    </div>
    <div id="modalBackdrop" class="modal-backdrop hidden" onclick="closeVideo()"></div>

    <footer>
        <p>&copy; 2024 Amax Streaming. Todos los derechos reservados.</p>
    </footer>

    <script>
        function changeSeason() {
            const seasonOrder = document.getElementById("seasonSelector").value;
            const tmdbId = "<?php echo htmlspecialchars($tmdb_id, ENT_QUOTES, 'UTF-8'); ?>";
            window.location.href = `?tmdb_id=${tmdbId}&season_order=${seasonOrder}`;
        }

        function openVideo(selectorId) {
            const selector = document.getElementById(selectorId);
            const url = selector.value;

            if (!url) {
                console.error('URL de video no válida');
                return;
            }

            const container = document.getElementById('videoPlayerContainer');
            const modal = document.getElementById('videoModal');
            const backdrop = document.getElementById('modalBackdrop');

            container.innerHTML = ''; // Limpiar el contenedor por seguridad
            const iframe = document.createElement('iframe');
            iframe.setAttribute('src', url);
            iframe.setAttribute('class', 'w-full h-80');
            iframe.setAttribute('frameborder', '0');
            iframe.setAttribute('allowfullscreen', 'true');
            iframe.setAttribute('loading', 'lazy');
            container.appendChild(iframe);

            modal.classList.add('active');
            backdrop.classList.remove('hidden');
            document.body.classList.add('modal-open');
        }

        function closeVideo() {
            const container = document.getElementById('videoPlayerContainer');
            const modal = document.getElementById('videoModal');
            const backdrop = document.getElementById('modalBackdrop');

            container.innerHTML = ''; // Limpiar el contenedor del video
            modal.classList.remove('active');
            backdrop.classList.add('hidden');
            document.body.classList.remove('modal-open');
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && document.body.classList.contains('modal-open')) {
                closeVideo();
            }
        });

        // Funcionalidad del menú móvil
        document.addEventListener('DOMContentLoaded', () => {
            const menuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');

            menuButton.addEventListener('click', () => {
                const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
                menuButton.setAttribute('aria-expanded', !isExpanded);
                mobileMenu.classList.toggle('hidden');
            });
        });
    </script>
</body>
</html>
