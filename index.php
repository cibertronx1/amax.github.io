

<?php
require_once 'config.php';
//index.php
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página Principal - Amax Streaming</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="home.css">
    <link  href="https://cdnjs.cloudflare.com/ajax/libs/Swiper/8.4.5/swiper-bundle.min.css"rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Swiper/8.4.5/swiper-bundle.min.js"></script>
    <style> </style>
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
                                <input 
                                    type="text"
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
                                id="mobile-menu-button"
                                class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
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

    <!-- Contenido Principal -->
    <div class="pt-20"></div> <!-- Espaciado para evitar que el contenido quede debajo del nav -->

    <?php if (!empty($search_query)): ?>
        <!-- Resultados de la Búsqueda -->
        <div class="container mx-auto px-6 py-8">
            <h2 class="text-3xl font-bold mb-4">Resultados de Búsqueda para: "<?php echo htmlspecialchars($search_query, ENT_QUOTES, 'UTF-8'); ?>"</h2>
            <?php if (!empty($media_results)): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($media_results as $item): ?>
                        <div class="card relative group rounded-lg overflow-hidden shadow-lg">
                            <?php
                                $poster = $item['poster'] ?? "https://via.placeholder.com/300x450?text=" . urlencode($item['titulo']);
                                
                                // Determinar la URL según el tipo de media
                                if (isset($item['media_type'])) {
                                    if ($item['media_type'] === 'movie') {
                                        $url = "get_movie.php?type=movie&tmdb_id=" . urlencode($item['tmdb_id']);
                                    } elseif ($item['media_type'] === 'web_series') {
                                        $url = "get_seasons.php?type=series&tmdb_id=" . urlencode($item['tmdb_id']);
                                    } else {
                                        $url = "#"; // Valor por defecto si no coincide
                                    }
                                } else {
                                    $url = "#"; // Por si media_type no está definido
                                }
                            ?>
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
        
<!-- Slider de Contenido Reciente desde la tabla slider -->
        <div class="movie-slider overflow-x-hidden w-full mb-8">
            <div class="swiper-container relative w-full">
                <div class="swiper-wrapper">
                    <?php foreach ($slider_items as $slide): ?>
                        <?php
                            $slide_title = $slide['titulo'] ?? 'Sin título';
                            $slide_banner = $slide['banner'] ?? '';
                            $slide_media_type = $slide['media_type'] ?? '';
                            $slide_tmdb_id = $slide['tmdb_id'] ?? '';

                            // Generar enlace según el tipo de media
                            if ($slide_media_type === 'movie') {
                                $slide_url = "get_movie.php?type=movie&tmdb_id=" . urlencode($slide_tmdb_id);
                            } elseif ($slide_media_type === 'web_series') {
                                $slide_url = "get_seasons.php?type=series&tmdb_id=" . urlencode($slide_tmdb_id);
                            } else {
                                $slide_url = "get_movie.php?type=movie&tmdb_id=". urlencode($slide_tmdb_id);
                            }
                        ?>
                        <div class="swiper-slide">
                            <div class="movie-card flex items-center">
                                <img src="<?php echo htmlspecialchars($slide_banner, ENT_QUOTES, 'UTF-8'); ?>" 
                                    alt="<?php echo htmlspecialchars($slide_title, ENT_QUOTES, 'UTF-8'); ?>" 
                                    class="w-full h-[500px] object-cover">
                                <div class="movie-info p-4">
                                    <h5 class="movie-title text-white text-xl font-bold"><?php echo htmlspecialchars($slide_title, ENT_QUOTES, 'UTF-8'); ?></h5>
                                    <?php if (!empty($slide['description'])): ?>
                                        <p class="movie-date text-gray-200"><?php echo htmlspecialchars($slide['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                    <a href="<?php echo htmlspecialchars($slide_url, ENT_QUOTES, 'UTF-8'); ?>" class="block mt-4 text-white-1900 hover:text-white-1300 ">
                                        <button 
                                            class="play-button w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors items-center justify-center gap-2"
                                        >
                                            <i class="fas fa-play"></i>
                                            <span>Ver Ahora</span>
                                        </button>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
            </div>
        </div>
        <!-- Fin del Slider  -->

        <div class="container mx-auto px-6 py-8">
            <!-- Sección de Películas -->
            <section class="mb-12">
                <h2 class="text-3xl font-bold mb-4">Películas Populares</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($movies as $movie): ?>
                        <div class="card relative group rounded-lg overflow-hidden shadow-lg">
                            <img src="<?php echo htmlspecialchars($movie['poster'], ENT_QUOTES, 'UTF-8'); ?>"
                                 alt="<?php echo htmlspecialchars($movie['titulo'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-80 object-cover">
                            <div class="absolute inset-0 bg-gray-900 bg-opacity-75 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <a href="get_movie.php?type=movie&tmdb_id=<?php echo htmlspecialchars($movie['tmdb_id'], ENT_QUOTES, 'UTF-8'); ?>" class="block mt-4 text-white-1900 hover:text-white-1300 ">
                                    <button 
                                        class="play-button w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors flex items-center justify-center gap-2"
                                    >
                                        <i class="fas fa-play"></i>
                                        <span>Ver Ahora</span>
                                    </button>
                                </a> 
                            </div>  
                            <p class="text-gray-400 text-center mt-2"><?php echo htmlspecialchars($movie['titulo'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="flex justify-center space-x-1 text-yellow-400 mt-1">
                                <?php
                                $user_score = $movie['user_score'] ?? 0;
                                for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $user_score ? '' : '-0'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="prose prose-invert max-w-none">
                                <p class="text-gray-300 text-center leading-relaxed text-base md:text-lg">
                                    <?php echo htmlspecialchars($movie['genre'], ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Paginación para Películas -->
                <div class="mt-6 flex justify-center space-x-4">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" class="px-4 py-2 bg-gray-700 text-gray-300 rounded-lg hover:bg-gray-600">Anterior</a>
                    <?php endif; ?>
                    <?php if ($page < $total_pages_movies): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Siguiente</a>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    <?php endif; ?>

    <footer>
        <p>&copy; 2024 Amax Streaming. Todos los derechos reservados.</p>
    </footer>
    <script>
        new Swiper('.swiper-container', {
            slidesPerView: 'auto',
            centeredSlides: true,
            spaceBetween: 30,
            loop: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            effect: 'coverflow',
            coverflowEffect: {
                rotate: 0,
                stretch: 0,
                depth: 100,
                modifier: 2,
                slideShadows: false,
            },
            breakpoints: {
                640: {
                    slidesPerView: 1,
                },
                768: {
                    slidesPerView: 2,
                },
                1024: {
                    slidesPerView: 3,
                },
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
