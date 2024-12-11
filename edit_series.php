<?php
session_start();
//edit_series.php

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

// Obtener información de la serie a editar
if (isset($_GET['id'])) {
    $seriesId = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM web_series WHERE id = :id");
    $stmt->bindParam(':id', $seriesId, PDO::PARAM_INT);
    $stmt->execute();
    $series = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$series) {
        $_SESSION['error'] = "Serie no encontrada.";
        header('Location: manage_series.php');
        exit;
    }
} else {
    header('Location: manage_series.php');
    exit;
}

// Procesar el formulario de edición de la serie
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tmdb_id = $_POST['tmdb_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $release_date = $_POST['release_date'];
    $poster = $_POST['poster'];
    $banner = $_POST['banner'];
    $status_type = isset($_POST['status_type']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE web_series SET tmdb_id = :tmdb_id, titulo = :name, description = :description, release_date = :release_date, poster = :poster, banner = :banner, status_type = :status_type WHERE id = :id");
    $stmt->bindParam(':tmdb_id', $tmdb_id);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':release_date', $release_date);
    $stmt->bindParam(':poster', $poster);
    $stmt->bindParam(':banner', $banner);
    $stmt->bindParam(':status_type', $status_type);
    $stmt->bindParam(':id', $seriesId, PDO::PARAM_INT);

    $updateSuccess = $stmt->execute();

    if ($updateSuccess) {
        // Manejo del slider
        if (isset($_POST['add_to_slider']) && $_POST['add_to_slider'] == '1') {
            $slider_description = $_POST['slider_description'] ?? '';
            $slider_link_url = $_POST['slider_link_url'] ?? '';
            $slider_sort_order = $_POST['slider_sort_order'] ?? 0;
            $slider_sort_order = (int)$slider_sort_order;

            // Verificar si existe ya una entrada en slider para esta serie
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM slider WHERE tmdb_id = :tmdb_id AND media_type = 'web_series'");
            $checkStmt->execute([':tmdb_id' => $tmdb_id]);
            $exists = $checkStmt->fetchColumn() > 0;

            if ($exists) {
                // Actualizar registro existente en slider
                $updateSliderStmt = $conn->prepare("
                    UPDATE slider
                    SET titulo = :titulo,
                        description = :description,
                        banner = :banner,
                        link_url = :link_url,
                        sort_order = :sort_order
                    WHERE tmdb_id = :tmdb_id AND media_type = 'web_series'
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
                // Insertar nuevo registro en slider
                $insertSliderStmt = $conn->prepare("
                    INSERT INTO slider (media_type, tmdb_id, titulo, description, banner, link_url, sort_order, release_date)
                    VALUES ('web_series', :tmdb_id, :titulo, :description, :banner, :link_url, :sort_order, NOW())
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
            // Si no se marcó la casilla, no se modifica el slider
            // Opcionalmente, podría implementarse lógica para eliminar registro del slider si existía.
        }

        $_SESSION['message'] = "Serie actualizada exitosamente.";
        header('Location: manage_series.php');
        exit;
    } else {
        $_SESSION['error'] = "Hubo un error al actualizar la serie. Inténtelo de nuevo.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Serie</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f172a;
            color: #f3f4f6;
            min-height: 100vh;
        }
        
        .form-input {
            transition: all 0.3s ease;
            border: 1px solid #374151;
        }
        
        .form-input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }
        
        .checkbox-custom {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .checkbox-custom:checked {
            animation: pulse 0.2s;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(0.9); }
            100% { transform: scale(1); }
        }
        
        .submit-button {
            transition: all 0.3s ease;
        }
        
        .submit-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .poster-preview {
            transition: transform 0.3s ease;
        }

        .poster-preview:hover {
            transform: scale(1.02);
        }
    </style>
    <script>
        function previewPoster() {
            const posterUrl = document.getElementById('poster').value;
            const previewImage = document.getElementById('posterPreview');
            const previewContainer = document.getElementById('posterPreviewContainer');
            
            if (posterUrl) {
                previewImage.src = posterUrl;
                previewImage.onerror = function() {
                    previewImage.src = '/api/placeholder/300/450';
                    previewContainer.classList.add('border-red-500');
                };
                previewImage.onload = function() {
                    previewContainer.classList.remove('border-red-500');
                };
                previewContainer.style.display = 'block';
            } else {
                previewImage.src = '/api/placeholder/300/450';
                previewContainer.style.display = 'block';
            }
        }

        // Ejecutar al cargar la página para mostrar la imagen existente
        window.onload = function() {
            previewPoster();
        }
    </script>
</head>
<body class="bg-gradient-to-br from-gray-900 to-gray-800">
    <div class="flex justify-center items-center min-h-screen p-4">
        <div class="w-full max-w-6xl bg-gray-800 p-8 rounded-lg shadow-2xl border border-gray-700">
            <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent text-center">Editar Serie</h1>

            <?php
            if (isset($_SESSION['error'])) {
                echo "<div class='bg-red-500 bg-opacity-20 border border-red-500 text-red-300 px-4 py-3 rounded mb-4'>
                    <p class='text-sm'>{$_SESSION['error']}</p>
                </div>";
                unset($_SESSION['error']);
            }
            ?>

            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Columna del formulario -->
                <div class="flex-1">
                    <form action="" method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="tmdb_id" class="block mb-2 text-sm font-medium text-gray-300">TMDB ID</label>
                                <input type="text" name="tmdb_id" id="tmdb_id" value="<?php echo htmlspecialchars($series['tmdb_id']); ?>" 
                                       class="form-input w-full p-3 rounded bg-gray-700 text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            
                            <div>
                                <label for="name" class="block mb-2 text-sm font-medium text-gray-300">Nombre</label>
                                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($series['titulo']); ?>" 
                                       class="form-input w-full p-3 rounded bg-gray-700 text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                        </div>

                        <div>
                            <label for="description" class="block mb-2 text-sm font-medium text-gray-300">Descripción</label>
                            <textarea name="description" id="description" 
                                      class="form-input w-full p-3 rounded bg-gray-700 text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 min-h-[100px]" 
                                      required><?php echo htmlspecialchars($series['description']); ?></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="release_date" class="block mb-2 text-sm font-medium text-gray-300">Fecha de Lanzamiento</label>
                                <input type="date" name="release_date" id="release_date" value="<?php echo htmlspecialchars($series['release_date']); ?>" 
                                       class="form-input w-full p-3 rounded bg-gray-700 text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                            
                            <div>
                                <label for="poster" class="block mb-2 text-sm font-medium text-gray-300">URL del Póster</label>
                                <input type="text" name="poster" id="poster" value="<?php echo htmlspecialchars($series['poster']); ?>" 
                                       class="form-input w-full p-3 rounded bg-gray-700 text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" 
                                       onchange="previewPoster()" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="banner" class="block mb-2 text-sm font-medium text-gray-300">URL del Banner</label>
                                <input type="text" name="banner" id="banner" value="<?php echo htmlspecialchars($series['banner']); ?>" 
                                       class="form-input w-full p-3 rounded bg-gray-700 text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                        </div>

                        <!-- Opciones para el slider -->
                        <h3 class="text-xl font-semibold mb-4 text-gray-200">Opciones de Slider (Opcional)</h3>
                        <div class="space-y-4">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="add_to_slider" value="1" class="checkbox-custom form-checkbox h-5 w-5 text-blue-500 rounded border-gray-500 bg-gray-700">
                                <span class="text-sm font-medium text-gray-300">Agregar/Actualizar esta serie en el slider</span>
                            </label>

                            <div>
                                <label for="slider_description" class="block mb-2 text-sm font-medium text-gray-300">Descripción Slider (Opcional)</label>
                                <textarea name="slider_description" id="slider_description"
                                          class="form-input w-full p-3 rounded bg-gray-700 text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" rows="2"></textarea>
                            </div>

                            <div>
                                <label for="slider_link_url" class="block mb-2 text-sm font-medium text-gray-300">Link URL Slider (Opcional)</label>
                                <input type="text" name="slider_link_url" id="slider_link_url"
                                       class="form-input w-full p-3 rounded bg-gray-700 text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>

                            <div>
                                <label for="slider_sort_order" class="block mb-2 text-sm font-medium text-gray-300">Orden (Opcional)</label>
                                <input type="number" name="slider_sort_order" id="slider_sort_order"
                                       class="form-input w-full p-3 rounded bg-gray-700 text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Ej: 1 para mostrar primero">
                            </div>
                        </div>

                        <div class="flex space-x-6 mt-4">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="status_type" id="status_type" 
                                       class="checkbox-custom form-checkbox h-5 w-5 text-blue-500 rounded border-gray-500 bg-gray-700" 
                                       <?php echo $series['status_type'] ? 'checked' : ''; ?>>
                                <span class="text-sm font-medium text-gray-300">Publicar</span>
                            </label>
                        </div>

                        <div class="pt-4">
                            <button type="submit" 
                                    class="submit-button w-full bg-blue-600 p-3 rounded-lg text-white font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800">
                                Actualizar Serie
                            </button>
                        </div>
                    </form>

                    <div class="mt-6 text-center">
                        <a href="manage_series.php" 
                           class="text-blue-400 hover:text-blue-300 transition-colors duration-200 text-sm">
                            Volver a Gestionar Series
                        </a>
                    </div>
                </div>

                <!-- Columna de previsualización -->
                <div class="lg:w-72">
                    <div class="sticky top-8">
                        <h3 class="text-lg font-medium text-gray-300 mb-4">Previsualización del Póster</h3>
                        <div id="posterPreviewContainer" class="border-2 border-gray-600 rounded-lg overflow-hidden poster-preview">
                            <img id="posterPreview" src="/api/placeholder/300/450" alt="Preview del póster" class="w-full h-auto object-cover">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
