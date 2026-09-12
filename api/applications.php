<?php
/**
 * ============================================================================
 * ATLANTIS v2 - API : Gestion des demandes (applications)
 * ============================================================================
 *
 * Endpoints :
 *   POST   ?action=create     (public - formulaire)
 *   GET    ?action=list       (admin/gestionnaire)
 *   GET    ?action=detail&id= (admin/gestionnaire)
 *   POST   ?action=update_status&id= (admin/gestionnaire)
 *   DELETE ?action=delete&id= (admin/gestionnaire)
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ---------------------------------------------------------------------------
// ROUTAGE
// ---------------------------------------------------------------------------
switch ($action) {
    case 'create':
        handleCreate();
        break;
    case 'list':
        handleList();
        break;
    case 'detail':
        handleDetail();
        break;
    case 'update_status':
        handleUpdateStatus();
        break;
    case 'delete':
        handleDelete();
        break;
    case 'upload_piece':
        handleUploadPiece();
        break;
    case 'download_piece':
        handleDownloadPiece();
        break;
    default:
        jsonError(400, 'Action inconnue.');
}

// ---------------------------------------------------------------------------
// CREATE (formulaire public)
// ---------------------------------------------------------------------------
function handleCreate(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError(405, 'Methode non autorisee.');
    }

    $nom        = clean($_POST['nom'] ?? '');
    $prenom     = clean($_POST['prenom'] ?? '');
    $telephone  = cleanRaw($_POST['telephone'] ?? '');
    $email      = cleanRaw($_POST['email'] ?? '');
    $entreprise = clean($_POST['entreprise'] ?? '');
    $type       = $_POST['type'] ?? '';
    $message    = clean($_POST['message'] ?? '');

    $pieceTokens = $_POST['pieces'] ?? [];
    if (!is_array($pieceTokens)) $pieceTokens = [];
    $pieceTokens = array_values(array_map('strval', $pieceTokens));
    $pieceTokens = array_filter($pieceTokens, fn($t) => $t !== '');
    $pieceTokens = array_slice($pieceTokens, 0, 6);

    $errors = [];

    if ($nom === '') {
        $errors['nom'] = 'Le nom est obligatoire.';
    } elseif (mb_strlen($nom) > 100) {
        $errors['nom'] = 'Le nom est trop long (100 caracteres max).';
    }

    if ($telephone === '') {
        $errors['telephone'] = 'Le numero de telephone est obligatoire.';
    } elseif (!isValidPhone($telephone)) {
        $errors['telephone'] = 'Le numero de telephone semble invalide.';
    }

    if ($email !== '' && !isValidEmail($email)) {
        $errors['email'] = 'L\'adresse email semble invalide.';
    }

    $allowedTypes = ['partenariat', 'recrutement'];
    if (!in_array($type, $allowedTypes, true)) {
        $errors['type'] = 'Veuillez selectionner un type de candidature.';
    }

    if (mb_strlen($message) > 5000) {
        $errors['message'] = 'Le message est trop long (5000 caracteres max).';
    }

    if (count($pieceTokens) < 1) {
        $errors['pieces'] = 'Le dossier de candidature est obligatoire (ajoutez au moins une piece jointe).';
    } elseif (count($pieceTokens) > 6) {
        $errors['pieces'] = 'Maximum 6 pieces jointes autorisees.';
    }

    if (!empty($errors)) {
        jsonError(400, 'Certains champs sont invalides.', ['errors' => $errors]);
    }

    try {
        $pdo = getDB();

        // Verifier que chaque jeton de piece existe, est "pending" et appartient a la session
        ensureSession();
        $sessionHash = hash('sha256', session_id());
        $ph       = [];
        $validateParams = [':session_hash' => $sessionHash];
        foreach ($pieceTokens as $i => $tok) {
            $key = ':t' . $i;
            $ph[] = $key;
            $validateParams[$key] = $tok;
        }
        $stmtP = $pdo->prepare(
            'SELECT id FROM application_pieces
             WHERE token IN (' . implode(', ', $ph) . ')
               AND session_hash = :session_hash
               AND statut = "pending"
               AND application_id IS NULL'
        );
        $stmtP->execute($validateParams);
        if ($stmtP->rowCount() !== count($pieceTokens)) {
            jsonError(400, 'Certaines pieces jointes sont invalides ou expirees.', [
                'errors' => ['pieces' => 'Certaines pieces jointes sont invalides ou expirees. Rechargez la page et reessayez.'],
            ]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO applications (nom, prenom, telephone, email, entreprise, type, message, statut)
             VALUES (:nom, :prenom, :telephone, :email, :entreprise, :type, :message, :statut)'
        );
        $stmt->execute([
            ':nom'        => $nom,
            ':prenom'     => $prenom !== '' ? $prenom : null,
            ':telephone'  => $telephone,
            ':email'      => $email !== '' ? $email : null,
            ':entreprise' => $entreprise !== '' ? $entreprise : null,
            ':type'       => $type,
            ':message'    => $message !== '' ? $message : null,
            ':statut'     => 'en_attente',
        ]);

        $newId = $pdo->lastInsertId();

        // Attacher les pieces a la candidature
        $linkParams = [':aid' => $newId];
        foreach ($pieceTokens as $i => $tok) {
            $linkParams[':t' . $i] = $tok;
        }
        $stmtL = $pdo->prepare(
            'UPDATE application_pieces
             SET application_id = :aid, statut = "recu"
             WHERE token IN (' . implode(', ', $ph) . ')'
        );
        $stmtL->execute($linkParams);

        // Nettoyage opportuniste des pieces en attente expirees ( +24h )
        cleanupExpiredPieces();

        logAudit(
            'create_application',
            'application',
            (int)$newId,
            'Nouvelle demande recue : ' . $nom . ' (' . $type . ') avec ' . count($pieceTokens) . ' piece(s) jointe(s)'
        );

        jsonSuccess('Merci pour votre demande ! Votre demande a bien ete transmise a l\'equipe ATLANTIS. Nous vous contacterons prochainement.');
    } catch (PDOException $e) {
        error_log('Create application error: ' . $e->getMessage());
        jsonError(500, 'Une erreur interne est survenue. Veuillez reessayer plus tard.');
    }
}

// ---------------------------------------------------------------------------
// UPLOAD PIECE (pre-upload des pieces jointes du formulaire public)
// ---------------------------------------------------------------------------
function handleUploadPiece(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError(405, 'Methode non autorisee.');
    }

    if (!isset($_FILES['piece']) || $_FILES['piece']['error'] !== UPLOAD_ERR_OK) {
        jsonError(400, 'Aucun fichier recu ou erreur d\'upload.');
    }

    $file = $_FILES['piece'];

    if ($file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024) {
        jsonError(400, 'Fichier trop volumineux. Taille maximale : 5 Mo.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedTypes = [
        'image/jpeg'        => 'jpg',
        'image/png'         => 'png',
        'application/pdf'   => 'pdf',
    ];

    if (!isset($allowedTypes[$mime])) {
        jsonError(400, 'Format non autorise. Formats acceptes : JPEG, JPG, PNG, PDF.');
    }

    $originalName = basename(str_replace('\\', '/', (string)$file['name']));
    $originalName = mb_substr($originalName, 0, 200);

    ensureSession();
    $sessionHash = hash('sha256', session_id());
    $token       = bin2hex(random_bytes(16));
    $ext         = $allowedTypes[$mime];
    $dir         = ROOT_PATH . '/private/candidatures';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $fileName = $token . '.' . $ext;
    $filePath = $dir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        jsonError(500, 'Erreur lors de la sauvegarde du fichier.');
    }

    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO application_pieces
                (token, session_hash, nom_fichier, chemin_fichier, mime_type, taille, statut)
             VALUES
                (:token, :sh, :nom, :chemin, :mime, :taille, "pending")'
        );
        $stmt->execute([
            ':token'  => $token,
            ':sh'     => $sessionHash,
            ':nom'    => $originalName,
            ':chemin' => 'candidatures/' . $fileName,
            ':mime'   => $mime,
            ':taille' => (int) $file['size'],
        ]);
    } catch (PDOException $e) {
        @unlink($filePath);
        error_log('Upload piece error: ' . $e->getMessage());
        jsonError(500, 'Une erreur interne est survenue.');
    }

    jsonSuccess('Piece jointe enregistree.', [
        'piece_token' => $token,
        'nom'         => $originalName,
        'taille'      => (int) $file['size'],
        'mime'        => $mime,
    ]);
}

// ---------------------------------------------------------------------------
// DOWNLOAD PIECE (admin/gestionnaire - lecture securisee du fichier)
// ---------------------------------------------------------------------------
function handleDownloadPiece(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError(405, 'Methode non autorisee.');
    }

    if (!isLoggedIn()) {
        jsonError(401, 'Acces refuse.');
    }

    if (!hasPermission(['admin', 'gestionnaire'])) {
        jsonError(403, 'Permissions insuffisantes.');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        jsonError(400, 'Identifiant invalide.');
    }

    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM application_pieces WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $piece = $stmt->fetch();

    if (!$piece || $piece['application_id'] === null) {
        jsonError(404, 'Piece introuvable.');
    }

    $path = resolvePiecePath($piece['chemin_fichier']);
    if (!$path || !is_file($path)) {
        jsonError(404, 'Fichier introuvable sur le serveur.');
    }

    $filename = str_replace(["\r", "\n"], '', (string) $piece['nom_fichier']);

    header('Content-Type: ' . $piece['mime_type']);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: inline; filename="' . addslashes(basename($filename)) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'none\'');
    readfile($path);
    exit;
}

// ---------------------------------------------------------------------------
// HELPERS - PIECES JOINTES
// ---------------------------------------------------------------------------

/**
 * Resout un chemin stocke vers un chemin disque securise (anti-travelling).
 */
function resolvePiecePath(string $storedPath): ?string
{
    $storedPath = str_replace('\\', '/', $storedPath);
    $matches = [];
    if (!preg_match('#^candidatures/([a-f0-9]{32}\.(?:jpg|png|pdf))$#', $storedPath, $matches)) {
        return null;
    }
    return ROOT_PATH . '/private/candidatures/' . $matches[1];
}

/**
 * Supprime les pieces jointes en attente expirées (fichiers + enregistrements).
 */
function cleanupExpiredPieces(): void
{
    try {
        $pdo   = getDB();
        $stmt  = $pdo->query(
            "SELECT id, chemin_fichier FROM application_pieces
             WHERE statut = 'pending'
               AND application_id IS NULL
               AND created_at < (NOW() - INTERVAL 1 DAY)"
        );
        $expired = $stmt->fetchAll();

        foreach ($expired as $row) {
            $path = resolvePiecePath($row['chemin_fichier']);
            if ($path && is_file($path)) {
                @unlink($path);
            }
            $del = $pdo->prepare('DELETE FROM application_pieces WHERE id = :id');
            $del->execute([':id' => $row['id']]);
        }
    } catch (PDOException $e) {
        error_log('Cleanup pieces failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// LIST (admin/gestionnaire)
// ---------------------------------------------------------------------------
function handleList(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError(405, 'Methode non autorisee.');
    }

    if (!isLoggedIn()) {
        jsonError(401, 'Acces refuse.');
    }

    if (!hasPermission(['admin', 'gestionnaire'])) {
        jsonError(403, 'Permissions insuffisantes.');
    }

    $pdo    = getDB();
    $type   = $_GET['type'] ?? '';
    $statut = $_GET['statut'] ?? '';
    $search = $_GET['search'] ?? '';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    $where  = [];
    $params = [];

    if ($type !== '' && in_array($type, ['partenariat', 'recrutement'], true)) {
        $where[]   = 'type = :type';
        $params[':type'] = $type;
    }

    $allowedStatuses = ['en_attente', 'en_cours', 'valide', 'refuse', 'archive'];
    if ($statut !== '') {
        // Support d'un filtre multiple : statut=en_attente,en_cours
        $validStatuses = [];
        foreach (explode(',', $statut) as $oneStatut) {
            $one = trim($oneStatut);
            if (in_array($one, $allowedStatuses, true)) {
                $validStatuses[] = $one;
            }
        }
        if (!empty($validStatuses)) {
            $statutKeys = [];
            foreach ($validStatuses as $j => $one) {
                $key         = ':statut' . ($j + 1);
                $statutKeys[] = $key;
                $params[$key] = $one;
            }
            $where[] = 'statut IN (' . implode(', ', $statutKeys) . ')';
        }
    }

    if ($search !== '') {
        $where[] = '(nom LIKE :search OR prenom LIKE :search OR telephone LIKE :search OR email LIKE :search OR entreprise LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Compter le total
    $countSQL = "SELECT COUNT(*) as total FROM applications $whereSQL";
    $stmt     = $pdo->prepare($countSQL);
    $stmt->execute($params);
    $total    = (int) $stmt->fetch()['total'];

    // Recuperer les donnees
    $dataSQL = "SELECT applications.*,
                       (SELECT COUNT(*) FROM application_pieces p WHERE p.application_id = applications.id) AS pieces_count
                FROM applications $whereSQL
                ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt    = $pdo->prepare($dataSQL);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    jsonSuccess('Liste recupereree.', [
        'items' => $items,
        'total' => $total,
        'page'  => $page,
        'pages' => (int) ceil($total / $limit),
    ]);
}

// ---------------------------------------------------------------------------
// DETAIL (admin/gestionnaire)
// ---------------------------------------------------------------------------
function handleDetail(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError(405, 'Methode non autorisee.');
    }

    if (!isLoggedIn()) {
        jsonError(401, 'Acces refuse.');
    }

    if (!hasPermission(['admin', 'gestionnaire'])) {
        jsonError(403, 'Permissions insuffisantes.');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        jsonError(400, 'Identifiant invalide.');
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $item = $stmt->fetch();

    if (!$item) {
        jsonError(404, 'Demande introuvable.');
    }

    // Recuperer les pieces jointes du dossier de candidature
    $stmtP = $pdo->prepare(
        'SELECT id, token, nom_fichier, mime_type, taille, statut, created_at
         FROM application_pieces
         WHERE application_id = :id
         ORDER BY id ASC'
    );
    $stmtP->execute([':id' => $id]);
    $pieces = $stmtP->fetchAll();

    jsonSuccess('Demande trouvee.', ['item' => $item, 'pieces' => $pieces]);
}

// ---------------------------------------------------------------------------
// UPDATE STATUS (admin/gestionnaire)
// ---------------------------------------------------------------------------
function handleUpdateStatus(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError(405, 'Methode non autorisee.');
    }

    if (!isLoggedIn()) {
        jsonError(401, 'Acces refuse.');
    }

    if (!hasPermission(['admin', 'gestionnaire'])) {
        jsonError(403, 'Permissions insuffisantes.');
    }

    // CSRF : lire le token depuis le header Authorization ou le body
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide. Veuillez recharger la page.');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        jsonError(400, 'Identifiant invalide.');
    }

    // Lire les donnees (JSON ou formulaire)
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $statut = $input['statut'] ?? '';

    $allowedStatuses = ['en_attente', 'en_cours', 'valide', 'refuse', 'archive'];
    if (!in_array($statut, $allowedStatuses, true)) {
        jsonError(400, 'Statut invalide.');
    }

    try {
        $pdo = getDB();

        // Recuperer l'ancien statut
        $stmt = $pdo->prepare('SELECT statut FROM applications WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetch();

        if (!$current) {
            jsonError(404, 'Demande introuvable.');
        }

        $stmt = $pdo->prepare('UPDATE applications SET statut = :statut WHERE id = :id');
        $stmt->execute([':statut' => $statut, ':id' => $id]);

        logAudit(
            'update_application_status',
            'application',
            $id,
            'Statut change de "' . $current['statut'] . '" vers "' . $statut . '"'
        );

        jsonSuccess('Statut mis a jour avec succes.');
    } catch (PDOException $e) {
        error_log('Update status error: ' . $e->getMessage());
        jsonError(500, 'Erreur interne. Veuillez reessayer.');
    }
}

// ---------------------------------------------------------------------------
// DELETE (admin/gestionnaire)
// ---------------------------------------------------------------------------
function handleDelete(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonError(405, 'Methode non autorisee.');
    }

    if (!isLoggedIn()) {
        jsonError(401, 'Acces refuse.');
    }

    if (!hasPermission(['admin', 'gestionnaire'])) {
        jsonError(403, 'Permissions insuffisantes.');
    }

    // CSRF
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide. Veuillez recharger la page.');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        jsonError(400, 'Identifiant invalide.');
    }

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare('SELECT nom, prenom, type FROM applications WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetch();

        if (!$current) {
            jsonError(404, 'Demande introuvable.');
        }

        // Supprimer d'abord les fichiers des pieces jointes
        $stmtP = $pdo->prepare('SELECT chemin_fichier FROM application_pieces WHERE application_id = :id');
        $stmtP->execute([':id' => $id]);
        foreach ($stmtP->fetchAll() as $pieceRow) {
            $deletePath = resolvePiecePath($pieceRow['chemin_fichier']);
            if ($deletePath && is_file($deletePath)) {
                @unlink($deletePath);
            }
        }

        $stmt = $pdo->prepare('DELETE FROM applications WHERE id = :id');
        $stmt->execute([':id' => $id]);

        logAudit(
            'delete_application',
            'application',
            $id,
            'Demande supprimee : ' . $current['nom'] . ' ' . ($current['prenom'] ?? '') . ' (' . $current['type'] . ')'
        );

        jsonSuccess('La demande a ete supprimee.');
    } catch (PDOException $e) {
        error_log('Delete application error: ' . $e->getMessage());
        jsonError(500, 'Erreur interne. Veuillez reessayer.');
    }
}
