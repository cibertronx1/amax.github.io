<?php
require_once 'config.php';

// Variables de búsqueda global
$search_query = isset($_GET['titulo']) ? trim($_GET['titulo']) : '';
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$media_results = [];
$total_pages_search = 0;

if (!empty($search_query)) {
    // Consulta para obtener resultados de búsqueda
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

// Si no hay búsqueda, mostrar el detalle de la película como antes
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Detalles de la película - <?php echo htmlspecialchars($movie['titulo']); ?>">
    <title>
        <?php if (!empty($search_query)): ?>
            Resultados de Búsqueda - Amax Streaming
        <?php else: ?>
            Detalle de la Película - <?php echo htmlspecialchars($movie['titulo']); ?>
        <?php endif; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="get_movies.css" rel="stylesheet">
    <style>
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
                            <a href="index.php" class="text-white hover:bg-gray-700 px-3 py-2 rounded-md text-sm font-medium">
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

    <!-- Sección principal de la película -->
    <div class="max-w-7xl mx-auto bg-gray-900/50 rounded-2xl overflow-hidden shadow-xl backdrop-blur">
        <div class="banner-container" 
             style="background-image: url('<?php echo htmlspecialchars($movie['banner'], ENT_QUOTES, 'UTF-8'); ?>'); background-size: cover; background-position: center; background-repeat: no-repeat; min-height: 100vh;">
            <div class="banner-overlay">
                <div class="content-wrapper">
                    <div class="container mx-auto px-6 py-12">
                        <div class="grid md:grid-cols-12 gap-8 p-4 md:p-8">
                            <!-- Columna del póster -->
                            <div class="md:col-span-4 lg:col-span-3">
                                <div class="movie-poster rounded-xl overflow-hidden shadow-2xl">
                                    <img 
                                        src="<?php echo htmlspecialchars($movie['poster']); ?>" 
                                        alt="poster <?php echo htmlspecialchars($movie['titulo']); ?>" 
                                        class="w-full h-auto object-cover"
                                        loading="lazy"
                                    >
                                </div>
                                <p class="text-gray-400 text-center"><?php echo htmlspecialchars($movie['release_date']); ?></p>
                                <div class="flex justify-center space-x-1 text-yellow-400 mt-2">
                                    <?php
                                    $user_score = $movie['user_score'] ?? 0;
                                    for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $user_score ? '' : '-0'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <div class="prose prose-invert max-w-none">
                                    <p class="text-gray-300 text-center leading-relaxed text-base md:text-lg">
                                        <?php echo htmlspecialchars($movie['genre']); ?>
                                    </p>
                                </div>
                            </div>
                            <!-- Columna de contenido -->
                            <div class="md:col-span-8 lg:col-span-9 space-y-6">
                                <!-- Título y descripción -->
                                <div>
                                    <h1 class="text-3xl md:text-4xl font-bold mb-6 gradient-text">
                                        <?php echo htmlspecialchars($movie['titulo']); ?>
                                    </h1>

                                    <?php if (isset($movie['genre'])): ?>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-genre text-purple-400"></i>
                                            <span><?php echo htmlspecialchars($movie['genre']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="prose prose-invert max-w-none">
                                        <p class="text-gray-300 leading-relaxed text-base md:text-lg">
                                            <?php echo htmlspecialchars($movie['description']); ?>
                                        </p>
                                    </div>
                                </div>
                                <!-- Métricas -->
                                <div class="flex items-center space-x-8">
                                    <?php if (isset($movie['user_score'])): ?>
                                    <div class="relative user-score flex items-center justify-center rounded-full w-16 h-16">
                                        <span class="relative z-10 text-2xl font-bold">
                                            <?= number_format($movie['user_score'] * 10, 0) ?>%
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="space-y-1">
                                        <?php if (isset($movie['runtime'])): ?>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-clock text-blue-400"></i>
                                            <span><?= $movie['runtime'] ?> Minutos</span>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (isset($movie['release_date'])): ?>
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-calendar text-purple-400"></i>
                                            <span><?= $movie['release_date'] ?> Fecha</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <!-- Enlaces de reproducción -->
                                <h3 class="text-xl font-semibold mb-4">Opciones de Reproducción</h3>
                                <?php if (!empty($play_links)): ?>
                                <div class="space-y-2">
                                    <select id="linkSelector" class="w-full px-4 py-3 rounded-xl bg-slate-700  border-slate-600 focus:border-blue-500 focus:ring focus:ring-blue-500/20 transition-all">
                                        <?php foreach ($play_links as $link): ?>
                                            <option value="<?php echo htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($link['name'] . ' (' . $link['quality'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button 
                                        onclick="openVideoFromSelector()"
                                        class="play-button w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors flex items-center justify-center gap-2"
                                        aria-label="Reproducir"
                                    >
                                        <i class="fas fa-play"></i>
                                        <span>Ver Ahora</span>
                                    </button>
                                </div>
                                <?php else: ?>
                                <div class="bg-yellow-600/20 border border-yellow-600/30 rounded-lg p-4">
                                    <p class="text-yellow-200 flex items-center gap-2">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        No hay enlaces de reproducción disponibles para esta película.
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal del reproductor de video mejorado -->
    <div id="videoModal" class="modal" role="dialog" aria-labelledby="modalTitle">
        <div class="modal-backdrop"></div>
        <div class="modal-content h-screen py-4 md:py-8">
            <div class="bg-gray-800 rounded-xl overflow-hidden shadow-2xl h-full flex flex-col">
                <div class="flex justify-between items-center p-4 border-b border-gray-700">
                    <h3 id="modalTitle" class="text-xl text-center font-bold">Espere hasta que se cargue el buffer...</h3>
                    <button 
                        onclick="closeVideo()" 
                        class="text-gray-400 hover:text-white transition-colors p-2"
                        aria-label="Cerrar reproductor"
                    >
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                <div class="flex-1 w-full overflow-hidden">
                    <div id="videoPlayerContainer" class="w-full h-full"></div>
                </div>
            </div>
        </div>
    </div>
    <footer>
        <p>&copy; 2024 Amax Streaming. Todos los derechos reservados.</p>
    </footer>
    <!-- Scripts -->
    <script>
    function openVideoFromSelector() {
        const selector = document.getElementById('linkSelector');
        const url = selector.value;
        openVideo(url);
    }

    function openVideo(url) {
        if (!url) {
            console.error('URL de video no válida');
            return;
        }

        const container = document.getElementById('videoPlayerContainer');
        const modal = document.getElementById('videoModal');
        
        // Limpiar contenedor por seguridad
        container.innerHTML = '';
        
        // Crear el iframe del reproductor con atributos de seguridad
        const iframe = document.createElement('iframe');
        iframe.setAttribute('src', url);
        iframe.setAttribute('class', 'w-full h-full');
        iframe.setAttribute('frameborder', '0');
        iframe.setAttribute('allowfullscreen', 'true');
        iframe.setAttribute('loading', 'lazy');
        
        // Agregar el iframe al contenedor
        container.appendChild(iframe);

        // Mostrar el modal
        modal.classList.add('active');
        document.body.classList.add('modal-open');

        // Manejar el cierre
        const handleBackdropClick = (event) => {
            if (event.target.classList.contains('modal-backdrop')) {
                closeVideo();
            }
        };
        
        document.querySelector('.modal-backdrop').addEventListener('click', handleBackdropClick);
    }

    function closeVideo() {
        const container = document.getElementById('videoPlayerContainer');
        const modal = document.getElementById('videoModal');
        
        // Limpiar el contenedor del video
        container.innerHTML = '';
        
        // Ocultar el modal
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }

    // Cerrar el modal con la tecla Escape
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.getElementById('videoModal').classList.contains('active')) {
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
