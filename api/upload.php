<?php
/**
 * ============================================================================
 * ATLANTIS v2 - API Upload d'images
 * POST multipart/form-data, auth + CSRF requis
 * ============================================================================
 */

require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Methode non autorisee.']);
    exit;
}

if (!hasPermission(['csm', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acces refuse.']);
    exit;
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide.']);
    exit;
}

// Suppression d'une image (logo, banniere ou section)
if (($_GET['action'] ?? '') === 'remove') {
    $type = $_POST['type'] ?? '';
    $sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;
    $pdo = getDB();
    $userId = $_SESSION['admin_id'] ?? null;

    if ($type === 'logo') {
        $stmt = $pdo->prepare('UPDATE site_settings SET valeur = "", updated_by = :uid, updated_at = NOW() WHERE cle = "logo_url"');
        $stmt->execute([':uid' => $userId]);
    } elseif ($type === 'banniere') {
        $stmt = $pdo->prepare('UPDATE site_settings SET valeur = "", updated_by = :uid, updated_at = NOW() WHERE cle = "banniere_url"');
        $stmt->execute([':uid' => $userId]);
    } elseif ($type === 'section' && $sectionId > 0) {
        $stmt = $pdo->prepare('UPDATE site_sections SET image_url = NULL, updated_by = :uid, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':uid' => $userId, ':id' => $sectionId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cible de suppression invalide.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Image supprimee.']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Aucun fichier envoye ou erreur d\'upload.']);
    exit;
}

$type = $_POST['type'] ?? '';
$sectionId = isset($_POST['section_id']) ? (int) $_POST['section_id'] : 0;

$allowedTypes = [
    'image/jpeg'     => 'jpg',
    'image/png'      => 'png',
    'image/gif'      => 'gif',
    'image/webp'     => 'webp',
    'image/svg+xml'  => 'svg',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
finfo_close($finfo);

if (!isset($allowedTypes[$mimeType])) {
    echo json_encode(['success' => false, 'message' => 'Type de fichier non autorise. Formats acceptes: JPG, PNG, GIF, WebP, SVG.']);
    exit;
}

$maxSizes = [
    'logo'     => 2 * 1024 * 1024,
    'banniere' => 4 * 1024 * 1024,
    'section'  => 3 * 1024 * 1024,
];

$maxSize = $maxSizes[$type] ?? 3 * 1024 * 1024;
if ($_FILES['image']['size'] > $maxSize) {
    $maxMb = round($maxSize / (1024 * 1024));
    echo json_encode(['success' => false, 'message' => "Fichier trop volumineux. Taille maximale: {$maxMb}Mo."]);
    exit;
}

$subdirs = [
    'logo'     => 'logos',
    'banniere' => 'hero',
    'section'  => 'sections',
];

$subdir = $subdirs[$type] ?? 'sections';
$uploadDir = __DIR__ . '/../uploads/' . $subdir;

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = $allowedTypes[$mimeType];
$filename = $type . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
$filepath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde du fichier.']);
    exit;
}

$relativeUrl = BASE_URL . '/uploads/' . $subdir . '/' . $filename;

$pdo = getDB();
$userId = $_SESSION['admin_id'] ?? null;

if ($type === 'logo') {
    $stmt = $pdo->prepare('INSERT INTO site_settings (cle, valeur, updated_by, updated_at) VALUES (:cle, :val, :uid, NOW()) ON DUPLICATE KEY UPDATE valeur = :val2, updated_by = :uid2, updated_at = NOW()');
    $stmt->execute([':cle' => 'logo_url', ':val' => $relativeUrl, ':val2' => $relativeUrl, ':uid' => $userId, ':uid2' => $userId]);
} elseif ($type === 'banniere') {
    $stmt = $pdo->prepare('INSERT INTO site_settings (cle, valeur, updated_by, updated_at) VALUES (:cle, :val, :uid, NOW()) ON DUPLICATE KEY UPDATE valeur = :val2, updated_by = :uid2, updated_at = NOW()');
    $stmt->execute([':cle' => 'banniere_url', ':val' => $relativeUrl, ':val2' => $relativeUrl, ':uid' => $userId, ':uid2' => $userId]);
} elseif ($type === 'section' && $sectionId > 0) {
    $stmt = $pdo->prepare('UPDATE site_sections SET image_url = :url, updated_by = :uid, updated_at = NOW() WHERE id = :id');
    $stmt->execute([':url' => $relativeUrl, ':uid' => $userId, ':id' => $sectionId]);
}

logAudit($type . '_upload', 'settings', null, "Upload image type={$type} => {$relativeUrl}");

echo json_encode(['success' => true, 'url' => $relativeUrl, 'message' => 'Image upload avec succes.']);