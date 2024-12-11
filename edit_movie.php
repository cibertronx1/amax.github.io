<?php
session_start();

//edit_movies.php

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "amax";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    die();
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != '1') {
    header('Location: login.php');
    exit;
}

// Obtener información de la película a editar
if (isset($_GET['tmdb_id'])) {
    $movieId = $_GET['tmdb_id'];
    $stmt = $conn->prepare("SELECT * FROM movies WHERE tmdb_id = :tmdb_id");
    $stmt->bindParam(':tmdb_id', $movieId, PDO::PARAM_INT);
    $stmt->execute();
    $movie = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$movie) {
        $_SESSION['error'] = "Película no encontrada.";
        header('Location: manage_movies.php');
        exit;
    }
} else {
    header('Location: manage_movies.php');
    exit;
}

// Procesar el formulario de edición de la película
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tmdb_id = $_POST['tmdb_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $release_date = $_POST['release_date'];
    $runtime = $_POST['runtime'];
    $poster = $_POST['poster'];
    $banner = $_POST['banner'];
    $status_type = isset($_POST['status_type']) ? 1 : 0;

    // Actualizar la película
    $stmt = $conn->prepare("UPDATE movies SET tmdb_id = :tmdb_id, titulo = :titulo, description = :description, release_date = :release_date, runtime = :runtime, poster = :poster, banner = :banner, status_type = :status_type WHERE id = :id");
    $stmt->bindParam(':tmdb_id', $tmdb_id);
    $stmt->bindParam(':titulo', $name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':release_date', $release_date);
    $stmt->bindParam(':runtime', $runtime);
    $stmt->bindParam(':poster', $poster);
    $stmt->bindParam(':banner', $banner);
    $stmt->bindParam(':status_type', $status_type);
    $stmt->bindParam(':id', $movie['id'], PDO::PARAM_INT);

    $updateSuccess = $stmt->execute();

    if ($updateSuccess) {
        // Manejo de la inserción/actualización en slider (opcional)
        // Si el usuario marcó la casilla para agregar al slider
        if (isset($_POST['add_to_slider']) && $_POST['add_to_slider'] == '1') {
            $slider_description = $_POST['slider_description'] ?? '';
            $slider_link_url = $_POST['slider_link_url'] ?? '';
            $slider_sort_order = $_POST['slider_sort_order'] ?? 0;
            $slider_sort_order = (int)$slider_sort_order;

            // Verificar si existe ya una entrada en slider para esta película
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM slider WHERE tmdb_id = :tmdb_id AND media_type = 'movies'");
            $checkStmt->execute([':tmdb_id' => $tmdb_id]);
            $exists = $checkStmt->fetchColumn() > 0;

            if ($exists) {
                // Actualizar registro existente
                $updateSliderStmt = $conn->prepare("
                    UPDATE slider
                    SET titulo = :titulo,
                        description = :description,
                        banner = :banner,
                        link_url = :link_url,
                        sort_order = :sort_order
                    WHERE tmdb_id = :tmdb_id AND media_type = 'movie'
                ");
                $updateSliderStmt->execute([
                    ':titulo' => $name,
                    ':description' => $slider_description,
                    ':banner' => $banner,
                    ':link_url' => $slider_link_url,
                    ':sort_order' => $slider_sort_order,
                    ':tmdb_id' => $tmdb_id
                ]);
            } else {
                // Insertar nuevo registro
                $insertSliderStmt = $conn->prepare("
                    INSERT INTO slider (media_type, tmdb_id, titulo, description, banner, link_url, sort_order, release_date)
                    VALUES ('movies', :tmdb_id, :titulo, :description, :banner, :link_url, :sort_order, NOW())
                ");
                $insertSliderStmt->execute([
                    ':tmdb_id' => $tmdb_id,
                    ':titulo' => $name,
                    ':description' => $slider_description,
                    ':banner' => $banner,
                    ':link_url' => $slider_link_url,
                    ':sort_order' => $slider_sort_order
                ]);
            }
        } else {
            // Si no se marcó la casilla, no se hace nada en slider
            // (Opcionalmente, podría implementarse lógica para eliminar el registro del slider si existía)
        }

        $_SESSION['message'] = "Película actualizada exitosamente.";
        header('Location: manage_movies.php');
        exit;
    } else {
        $_SESSION['error'] = "Hubo un error al actualizar la película. Inténtelo de nuevo.";
    }
}

?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Película</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
        }
        .preview-image {
            transition: transform 0.3s ease;
        }
        .preview-image:hover {
            transform: scale(1.05);
        }
        .input-group input, .input-group textarea {
            background-color: rgba(30, 41, 59, 0.5);
            border: 1px solid #334155;
            transition: all 0.3s ease;
        }
        .input-group input:focus, .input-group textarea:focus {
            background-color: rgba(30, 41, 59, 0.8);
            border-color: #60a5fa;
            box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2);
        }
        .custom-checkbox {
            position: relative;
            padding-left: 35px;
            cursor: pointer;
            user-select: none;
        }
        .custom-checkbox input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
        .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            height: 25px;
            width: 25px;
            background-color: rgba(30, 41, 59, 0.5);
            border: 1px solid #334155;
            border-radius: 4px;
        }
        .custom-checkbox:hover input ~ .checkmark {
            background-color: rgba(30, 41, 59, 0.8);
        }
        .custom-checkbox input:checked ~ .checkmark {
            background-color: #60a5fa;
        }
    </style>
</head>
<body class="min-h-screen py-8">
    <div class="container mx-auto px-4">
        <div class="max-w-6xl mx-auto bg-slate-800 rounded-lg shadow-xl overflow-hidden">
            <!-- Encabezado -->
            <div class="bg-slate-700 p-6 border-b border-slate-600">
                <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent text-center">Editar Película</h1>
            </div>

            <!-- Contenido principal -->
            <div class="p-6">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-500 text-white p-4 rounded-lg mb-6">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Columna de previsualización -->
                    <div class="space-y-6">
                        <div class="bg-slate-700 p-4 rounded-lg">
                            <h3 class="text-xl font-semibold mb-4">Vista Previa</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium mb-2">Póster</label>
                                    <img id="poster-preview" src="<?php echo htmlspecialchars($movie['poster']); ?>" 
                                         alt="Póster de la película" 
                                         class="w-full h-auto rounded-lg preview-image"
                                         onerror="this.src='/api/placeholder/300/450'">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Banner</label>
                                    <img id="banner-preview" src="<?php echo htmlspecialchars($movie['banner']); ?>" 
                                         alt="Banner de la película" 
                                         class="w-full h-32 object-cover rounded-lg preview-image"
                                         onerror="this.src='/api/placeholder/800/200'">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulario -->
                    <div class="lg:col-span-2">
                        <form action="" method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- TMDB ID y Nombre -->
                                <div class="input-group">
                                    <label for="tmdb_id" class="block text-sm font-medium mb-2">TMDB ID</label>
                                    <input type="text" name="tmdb_id" id="tmdb_id" 
                                           value="<?php echo htmlspecialchars($movie['tmdb_id']); ?>" 
                                           class="w-full p-3 rounded-lg focus:outline-none" required>
                                </div>
                                <div class="input-group">
                                    <label for="name" class="block text-sm font-medium mb-2">Nombre</label>
                                    <input type="text" name="name" id="name" 
                                           value="<?php echo htmlspecialchars($movie['titulo']); ?>" 
                                           class="w-full p-3 rounded-lg focus:outline-none" required>
                                </div>
                            </div>

                            <!-- Descripción -->
                            <div class="input-group">
                                <label for="description" class="block text-sm font-medium mb-2">Descripción</label>
                                <textarea name="description" id="description" 
                                          class="w-full p-3 rounded-lg focus:outline-none" 
                                          rows="4" required><?php echo htmlspecialchars($movie['description']); ?></textarea>
                            </div>

                            <!-- Fecha y Duración -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="input-group">
                                    <label for="release_date" class="block text-sm font-medium mb-2">Fecha de Lanzamiento</label>
                                    <input type="date" name="release_date" id="release_date" 
                                           value="<?php echo htmlspecialchars($movie['release_date']); ?>" 
                                           class="w-full p-3 rounded-lg focus:outline-none" required>
                                </div>
                                <div class="input-group">
                                    <label for="runtime" class="block text-sm font-medium mb-2">Duración (minutos)</label>
                                    <input type="number" name="runtime" id="runtime" 
                                           value="<?php echo htmlspecialchars($movie['runtime']); ?>" 
                                           class="w-full p-3 rounded-lg focus:outline-none" required>
                                </div>
                            </div>

                            <!-- URLs -->
                            <div class="input-group">
                                <label for="poster" class="block text-sm font-medium mb-2">URL del Póster</label>
                                <input type="text" name="poster" id="poster" 
                                       value="<?php echo htmlspecialchars($movie['poster']); ?>" 
                                       class="w-full p-3 rounded-lg focus:outline-none" 
                                       onchange="updatePreview('poster')" required>
                            </div>
                            <div class="input-group">
                                <label for="banner" class="block text-sm font-medium mb-2">URL del Banner</label>
                                <input type="text" name="banner" id="banner" 
                                       value="<?php echo htmlspecialchars($movie['banner']); ?>" 
                                       class="w-full p-3 rounded-lg focus:outline-none" 
                                       onchange="updatePreview('banner')" required>
                            </div>

                            <!-- Opciones para el slider -->
                            <h3 class="text-xl font-semibold mb-4">Opciones de Slider (Opcional)</h3>
                            <div class="space-y-4">
                                <label class="custom-checkbox">
                                    <input type="checkbox" name="add_to_slider" value="1">
                                    <span class="checkmark"></span>
                                    <span class="ml-2">Agregar/Actualizar esta película en el slider</span>
                                </label>

                                <div class="input-group">
                                    <label for="slider_description" class="block text-sm font-medium mb-2">Descripción Slider (Opcional)</label>
                                    <textarea name="slider_description" id="slider_description"
                                              class="w-full p-3 rounded-lg focus:outline-none" rows="2"></textarea>
                                </div>

                                <div class="input-group">
                                    <label for="slider_link_url" class="block text-sm font-medium mb-2">Link URL Slider (Opcional)</label>
                                    <input type="text" name="slider_link_url" id="slider_link_url"
                                           class="w-full p-3 rounded-lg focus:outline-none">
                                </div>

                                <div class="input-group">
                                    <label for="slider_sort_order" class="block text-sm font-medium mb-2">Orden (Opcional)</label>
                                    <input type="number" name="slider_sort_order" id="slider_sort_order"
                                           class="w-full p-3 rounded-lg focus:outline-none" placeholder="Ej: 1 para mostrar primero">
                                </div>
                            </div>

                            <!-- Checkboxes -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                <label class="custom-checkbox">
                                    <input type="checkbox" name="status_type" id="status_type" 
                                           <?php echo $movie['status_type'] ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    <span class="ml-2">Marca la casilla para Publicar</span>
                                </label>
                            </div>

                            <!-- Botones -->
                            <div class="flex space-x-4 mt-6">
                                <button type="submit" 
                                        class="flex-1 bg-blue-500 hover:bg-blue-600 text-white py-3 px-6 rounded-lg 
                                               transition-colors duration-200 flex items-center justify-center space-x-2">
                                    <i class="fas fa-save"></i>
                                    <span>Actualizar Película</span>
                                </button>
                                <a href="manage_movies.php" 
                                   class="bg-slate-600 hover:bg-slate-700 text-white py-3 px-6 rounded-lg 
                                          transition-colors duration-200 flex items-center justify-center space-x-2">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Volver</span>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updatePreview(type) {
            const input = document.getElementById(type);
            const preview = document.getElementById(type + '-preview');
            preview.src = input.value;
            
            // Manejar errores de carga de imagen
            preview.onerror = function() {
                this.src = type === 'poster' ? '/api/placeholder/300/450' : '/api/placeholder/800/200';
            };
        }

        // Actualizar previsualizaciones al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            updatePreview('poster');
            updatePreview('banner');
        });
    </script>
</body>
</html>
