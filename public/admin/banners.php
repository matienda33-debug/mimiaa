<?php
require_once '../../config/config.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    header('Location: ../../index.php');
    exit();
}

if (isset($_SESSION['rol']) && $_SESSION['rol'] == 4) {
    header('Location: ../../index.php');
    exit();
}

function getBannerFileName($slot) {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $basePath = __DIR__ . '/../../assets/img/';

    foreach ($allowedExtensions as $extension) {
        $fileName = 'banner' . $slot . '.' . $extension;
        if (file_exists($basePath . $fileName)) {
            return $fileName;
        }
    }

    return null;
}

function removeBannerVariants($slot) {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $basePath = __DIR__ . '/../../assets/img/';

    foreach ($allowedExtensions as $extension) {
        $filePath = $basePath . 'banner' . $slot . '.' . $extension;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bannerSlot = isset($_POST['banner_slot']) ? (int) $_POST['banner_slot'] : 0;
    $inputName = 'banner_image';

    if (!in_array($bannerSlot, [1, 2, 3], true)) {
        $message = 'Banner inválido.';
        $messageType = 'danger';
    } elseif (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        $message = 'Debes seleccionar una imagen válida.';
        $messageType = 'danger';
    } else {
        $file = $_FILES[$inputName];
        $tempPath = $file['tmp_name'];
        $size = (int) $file['size'];

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tempPath);
        finfo_close($finfo);

        if (!isset($allowedMimeTypes[$mimeType])) {
            $message = 'Formato no permitido. Solo JPG, PNG o WEBP.';
            $messageType = 'danger';
        } elseif ($size > 5 * 1024 * 1024) {
            $message = 'La imagen excede 5MB.';
            $messageType = 'danger';
        } else {
            $targetDir = __DIR__ . '/../../assets/img/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            removeBannerVariants($bannerSlot);
            $extension = $allowedMimeTypes[$mimeType];
            $targetName = 'banner' . $bannerSlot . '.' . $extension;
            $targetPath = $targetDir . $targetName;

            if (move_uploaded_file($tempPath, $targetPath)) {
                $message = 'Banner ' . $bannerSlot . ' actualizado correctamente.';
                $messageType = 'success';
            } else {
                $message = 'No se pudo guardar la imagen.';
                $messageType = 'danger';
            }
        }
    }
}

$bannerFiles = [
    1 => getBannerFileName(1),
    2 => getBannerFileName(2),
    3 => getBannerFileName(3)
];

$bannerLabels = [
    1 => 'Banner Oferta',
    2 => 'Nueva Colección',
    3 => 'Ajitos Kids'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Banners</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6B74DB;
            --secondary-color: #5461C8;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }
        .banner-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            padding: 18px;
            height: 100%;
        }
        .banner-preview {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            background: #f3f4f6;
        }
        .placeholder {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Gestión de Banners del Carrusel</h1>
        <a href="/tiendaAA/modules/mod1/dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Volver</a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="alert alert-info">
        Sube una imagen para cada banner del inicio. Formatos permitidos: JPG, PNG, WEBP. Máximo 5MB por imagen.
    </div>

    <div class="row g-4">
        <?php for ($i = 1; $i <= 3; $i++): ?>
            <div class="col-md-4">
                <div class="banner-card">
                    <h5 class="mb-3"><?php echo htmlspecialchars($bannerLabels[$i]); ?></h5>

                    <?php if ($bannerFiles[$i]): ?>
                        <img class="banner-preview mb-3" src="/tiendaAA/assets/img/<?php echo htmlspecialchars($bannerFiles[$i]); ?>?t=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($bannerLabels[$i]); ?>">
                    <?php else: ?>
                        <div class="banner-preview placeholder mb-3">Sin imagen</div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="banner_slot" value="<?php echo $i; ?>">
                        <div class="mb-3">
                            <input type="file" class="form-control" name="banner_image" accept=".jpg,.jpeg,.png,.webp" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-upload me-2"></i>Guardar <?php echo htmlspecialchars($bannerLabels[$i]); ?>
                        </button>
                    </form>
                </div>
            </div>
        <?php endfor; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
