

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

// Verificar si se ha proporcionado el ID del episodio
if (!isset($_GET['id'])) {
    echo "ID del episodio no proporcionado.";
    exit;
}

$episode_id = (int)$_GET['id'];

// Obtener detalles del episodio
$stmt_episode = $conn->prepare("SELECT * FROM web_series_episodes WHERE id = :id");
$stmt_episode->bindParam(':id', $episode_id, PDO::PARAM_INT);
$stmt_episode->execute();
$episode = $stmt_episode->fetch(PDO::FETCH_ASSOC);

if (!$episode) {
    echo "Episodio no encontrado.";
    exit;
}

// Obtener enlaces de reproducción para el episodio
$stmt_links = $conn->prepare("SELECT * FROM episode_play_links WHERE episode_id = :episode_id ORDER BY link_order ASC");
$stmt_links->bindParam(':episode_id', $episode_id, PDO::PARAM_INT);
$stmt_links->execute();
$play_links = $stmt_links->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Episodio - <?php echo htmlspecialchars($episode['episode_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
        }
    </style>
    <!-- Incluir el script de PlayerJS -->
    <script src="https://playerjs.com/playerjs.js"></script>
</head>
<body class="antialiased">
    <div class="container mx-auto px-6 py-12">
        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <!-- Imagen del episodio -->
            <div class="flex-shrink-0 mb-8 lg:mb-0">
                <img 
                    src="<?php echo htmlspecialchars($episode['episode_image']); ?>" 
                    alt="<?php echo htmlspecialchars($episode['episode_name']); ?>" 
                    class="rounded-lg shadow-lg w-full lg:w-80"
                >
            </div>
            
            <!-- Detalles del episodio -->
            <div class="flex-1">
                <h1 class="text-4xl font-bold mb-4 bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">
                    <?php echo htmlspecialchars($episode['episode_name']); ?>
                </h1>
                
                <p class="text-gray-400 mb-4">
                    Orden del episodio: <?php echo htmlspecialchars($episode['episode_order']); ?>
                </p>
                
                <p class="text-gray-200 mb-6">
                    <?php echo htmlspecialchars($episode['episode_description']); ?>
                </p>

                <!-- Enlaces de reproducción -->
                <h2 class="text-2xl font-semibold mb-4">Ver Ahora</h2>
                
                <?php if (count($play_links) > 0): ?>
                    <div class="space-y-4">
                        <?php foreach ($play_links as $link): ?>
                            <div class="bg-gray-800 p-4 rounded-lg flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold">
                                        <?php echo htmlspecialchars($link['name']); ?>
                                    </h3>
                                    <p class="text-sm text-gray-400">
                                        Calidad: <?php echo htmlspecialchars($link['quality']); ?>
                                    </p>
                                </div>
                                <button 
                                    onclick="openVideo('<?php echo htmlspecialchars($link['url']); ?>')" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors"
                                >
                                    Ver Ahora
                                </button>
                                
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-gray-400">
                        No hay enlaces de reproducción disponibles para este episodio.
                    </p>
                <?php endif; ?>

                <!-- Contenedor para el reproductor de video -->
                <div id="videoPlayerContainer" class="mt-8"></div>

                <!-- Controles del reproductor -->
                <div id="playerControls" class="flex space-x-4 mt-4 hidden">
                    <button onclick="playVideo()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">Reproducir</button>
                    <button onclick="pauseVideo()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg">Pausar</button>
                    <button onclick="getCurrentTime()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg">Tiempo Actual</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Script para manejar el reproductor de video -->
    <script>
    var player;

    // Función para abrir el video
    function openVideo(url) {
        var container = document.getElementById('videoPlayerContainer');
        container.innerHTML = `
            <div class="bg-gray-800 p-4 rounded-lg">
                <iframe id="playerIframe" src="https://playerjs.com/iframe.html#${encodeURIComponent(url)}" class="w-full h-64 md:h-96" frameborder="0" allowfullscreen></iframe>
            </div>
        `;

        // Mostrar los controles del reproductor
        document.getElementById('playerControls').classList.remove('hidden');

        // Crear instancia de PlayerJS después de que el iframe haya cargado
        var iframe = document.getElementById('playerIframe');
        iframe.onload = function() {
            player = new Playerjs({ id: "playerIframe" });
        };
    }

    // Función para pausar el video
    function pauseVideo() {
        if (player) {
            player.api("pause");
        }
    }

    // Función para reproducir el video
    function playVideo() {
        if (player) {
            player.api("play");
        }
    }

    // Función para obtener el tiempo actual del video
    function getCurrentTime() {
        if (player) {
            player.api("time", function(seconds) {
                console.log("Tiempo actual:", seconds);
                alert("Tiempo actual del video: " + seconds + " segundos");
            });
        }
    }
    </script>
</body>
</html>
