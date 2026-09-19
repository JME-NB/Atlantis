<?php
/**
 * ============================================================================
 * ATLANTIS v2 - API : Gestion des demandes (applications)
 * ============================================================================
 *
 * Endpoints :
 *   POST   ?action=create     (public - formulaire)
 *   GET    ?action=list       (admin/gestionnaire) [type, statut multi-CSV, search, page, sort, dir]
 *   GET    ?action=detail&id= (admin/gestionnaire)
 *   POST   ?action=update_status&id= (admin/gestionnaire) [statut] - archive interdit via ce endpoint
 *   DELETE ?action=delete&id= (admin/gestionnaire) -> archive (soft delete, memorise statut_precedent)
 *   DELETE ?action=permanent_delete&id= (admin uniquement)
 *   POST   ?action=restore&id= (admin uniquement) [retour=precedent|attente]
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

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
    case 'permanent_delete':
        handlePermanentDelete();
        break;
    case 'restore':
        handleRestore();
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

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }

    $pieceTokens = $_POST['pieces'] ?? [];
    if (!is_array($pieceTokens)) $pieceTokens = [];
    $pieceTokens = array_values(array_map('strval', $pieceTokens));
    $pieceTokens = array_filter($pieceTokens, fn($t) => $t !== '');
    $pieceTokens = array_slice($pieceTokens, 0, MAX_PIECES_PAR_DEMANDE);

    // --- Champs configures (reglage landing_form_fields, defaut = actuel) ---
    $fields = getLandingFormFields();

    $piecesVisible  = true;
    $piecesRequired = true;
    foreach ($fields as $f) {
        if (($f['cle'] ?? '') === 'pieces') {
            $piecesVisible  = !empty($f['visible']);
            $piecesRequired = !empty($f['obligatoire']);
        }
    }

    $errors = [];

    // --- Validation des pieces (dossier de candidature) ---
    if ($piecesVisible && $piecesRequired && count($pieceTokens) < 1) {
        $errors['pieces'] = 'Le dossier de candidature est obligatoire (ajoutez au moins une piece jointe).';
    } elseif (count($pieceTokens) > MAX_PIECES_PAR_DEMANDE) {
        $errors['pieces'] = 'Maximum 6 pieces jointes autorisees.';
    }

    // --- Validation des champs (seuls les champs visibles sont valides) ---
    $colValues   = []; // colonnes applications (built-in)
    $customValues = []; // champs personnalises (custom_*)

    foreach ($fields as $field) {
        if (empty($field['visible'])) continue;

        $cle      = (string)($field['cle'] ?? '');
        $libelle  = (string)($field['libelle'] ?? $cle);
        $type     = fieldType($field);
        $obligato = !empty($field['obligatoire']);

        if ($cle === 'pieces') continue; // traite ci-dessus

        $raw = $_POST[$cle] ?? '';
        if (!is_string($raw)) $raw = '';

        if ($type === 'text' || $type === 'textarea' || $type === 'select') {
            $val = cleanRaw($raw);
        } else {
            $val = cleanRaw($raw);
        }

        if ($obligato && $val === '') {
            $errors[$cle] = 'Le champ « ' . $libelle . ' » est obligatoire.';
            continue;
        }
        if ($val === '') {
            if (isset($colValues[$cle])) unset($colValues[$cle]);
            continue;
        }

        switch ($type) {
            case 'email':
                if (!isValidEmail($val)) {
                    $errors[$cle] = 'Le champ « ' . $libelle . ' » doit contenir une adresse email valide.';
                    continue 2;
                }
                break;
            case 'tel':
                if (!isValidPhone($val)) {
                    $errors[$cle] = 'Le champ « ' . $libelle . ' » doit contenir un numero de telephone valide.';
                    continue 2;
                }
                break;
            case 'select':
                $options = $field['options'] ?? [];
                $ok = empty($options) || in_array($val, array_map('strval', $options), true);
                if ($cle === 'type') {
                    // La colonne applications.type est un ENUM fixe
                    $ok = $ok && in_array($val, ['partenariat', 'recrutement'], true);
                }
                if (!$ok) {
                    $errors[$cle] = 'Veuillez selectionner une valeur valide pour le champ « ' . $libelle . ' ».';
                    continue 2;
                }
                break;
        }

        // Contraintes specifiques aux colonnes existantes
        if ($cle === 'nom' && mb_strlen($val) > 100) {
            $errors['nom'] = 'Le nom est trop long (100 caracteres max).';
            continue;
        }
        if ($cle === 'message' && mb_strlen($val) > 5000) {
            $errors['message'] = 'Le message est trop long (5000 caracteres max).';
            continue;
        }

        if (in_array($cle, ['nom', 'prenom', 'telephone', 'email', 'entreprise', 'type', 'message'], true)) {
            $colValues[$cle] = $val;
        } else {
            $customValues[$cle] = $val;
        }
    }

    if (!empty($errors)) {
        jsonError(400, 'Certains champs sont invalides.', ['errors' => $errors]);
    }

    try {
        $pdo = getDB();

        // Verifier que chaque jeton de piece existe, est "pending" et appartient a la session
        ensureSession();
        $sessionHash = hash('sha256', session_id());
        $ph          = [];
        $validateParams = [':session_hash' => $sessionHash];
        if (!empty($pieceTokens)) {
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
        }

        $nom        = $colValues['nom'] ?? '';
        $prenom     = $colValues['prenom'] ?? '';
        $telephone  = $colValues['telephone'] ?? '';
        $email      = $colValues['email'] ?? '';
        $entreprise = $colValues['entreprise'] ?? '';
        $type       = $colValues['type'] ?? 'partenariat';
        $message    = $colValues['message'] ?? '';

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
        if (!empty($pieceTokens)) {
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
        }

        // Sauvegarder les champs personnalises
        if (!empty($customValues)) {
            $insC = $pdo->prepare(
                'INSERT INTO application_champs_personnalises (application_id, champ, valeur)
                 VALUES (:aid, :champ, :valeur)'
            );
            foreach ($customValues as $cleC => $valC) {
                $insC->execute([':aid' => $newId, ':champ' => $cleC, ':valeur' => $valC]);
            }
        }

        // Nettoyage opportuniste des pieces en attente expirees ( +24h )
        cleanupExpiredPieces();

        logAudit(
            'create_application',
            'application',
            (int)$newId,
            'Nouvelle demande recue : ' . $nom . ' (' . $type . ') avec ' . count($pieceTokens) . ' piece(s) jointe(s)'
        );

        notify(
            'ticket_nouveau',
            'Nouvelle demande n°' . $newId . ' : ' . $nom . ' ' . $prenom . ' (' . $type . ')',
            ['admin', 'gestionnaire'],
            'application',
            (int)$newId,
            false
        );

        jsonSuccess('Merci pour votre demande ! Votre demande a bien ete transmise a l\'equipe ATLANTIS. Nous vous contacterons prochainement.');
    } catch (PDOException $e) {
        logError('ERROR', 'Create application error: ' . $e->getMessage(), 'api/applications.php', 275);
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

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide.');
    }

    if (!isset($_FILES['piece']) || $_FILES['piece']['error'] !== UPLOAD_ERR_OK) {
        jsonError(400, 'Aucun fichier recu ou erreur d\'upload.');
    }

    $file = $_FILES['piece'];

    if ($file['size'] <= 0 || $file['size'] > MAX_TAILLE_PIECE) {
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

    $actualSize = filesize($filePath);
    if ($actualSize === false || $actualSize > MAX_TAILLE_PIECE) {
        @unlink($filePath);
        jsonError(400, 'Fichier trop volumineux. Taille maximale : 5 Mo.');
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
        logError('ERROR', 'Upload piece error: ' . $e->getMessage(), 'api/applications.php', 351);
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
        logError('ERROR', 'Cleanup pieces failed: ' . $e->getMessage(), 'api/applications.php', 451);
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

    // Tri par colonnes (whitelist stricte contre toute injection)
    $sortColumns = ['id', 'nom', 'prenom', 'telephone', 'entreprise', 'type', 'statut', 'created_at'];
    $sort = $_GET['sort'] ?? 'created_at';
    if (!in_array($sort, $sortColumns, true)) {
        $sort = 'created_at';
    }
    $dir = strtolower((string)($_GET['dir'] ?? 'desc'));
    if (!in_array($dir, ['asc', 'desc'], true)) {
        $dir = 'desc';
    }

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
                ORDER BY $sort $dir, id $dir LIMIT :limit OFFSET :offset";
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

    // Champs personnalises (champs libres du formulaire) avec leur libelle
    $stmtC = $pdo->prepare(
        'SELECT champ, valeur FROM application_champs_personnalises
         WHERE application_id = :id
         ORDER BY id ASC'
    );
    $stmtC->execute([':id' => $id]);
    $champsPerso = $stmtC->fetchAll();

    $labels = [];
    foreach (getLandingFormFields() as $f) {
        $labels[$f['cle'] ?? ''] = $f['libelle'] ?? ($f['cle'] ?? '');
    }
    foreach ($champsPerso as &$cp) {
        $cp['libelle'] = $labels[$cp['champ']] ?? $cp['champ'];
    }
    unset($cp);

    jsonSuccess('Demande trouvee.', ['item' => $item, 'pieces' => $pieces, 'champs_personnalises' => $champsPerso]);
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

        // Recuperer l'ancien statut et l'identite du candidat
        $stmt = $pdo->prepare('SELECT statut, nom, prenom FROM applications WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetch();

        if (!$current) {
            jsonError(404, 'Demande introuvable.');
        }

        // L'archivage passait par le statut : il doit passer par la suppression (popup de confirmation)
        if ($statut === 'archive') {
            jsonError(400, 'Utilisez la suppression pour archiver une candidature.');
        }

        // Une candidature acceptee ou refusee est definitive : plus aucun changement de statut
        if (in_array($current['statut'], ['valide', 'refuse'], true)) {
            $terme = $current['statut'] === 'valide' ? 'acceptee' : 'refusee';
            jsonError(400, 'Cette candidature est ' . $terme . ', elle ne peut plus changer de statut. Elle peut uniquement etre archivee.');
        }

        $stmt = $pdo->prepare('UPDATE applications SET statut = :statut WHERE id = :id');
        $stmt->execute([':statut' => $statut, ':id' => $id]);

        logAudit(
            'update_application_status',
            'application',
            $id,
            'Statut change de "' . $current['statut'] . '" vers "' . $statut . '"'
        );

        notify(
            'ticket_statut',
            'Demande n°' . $id . ' (' . $current['nom'] . ') : "' . $current['statut'] . '" -> "' . $statut . '" par ' . getAdminDisplayName(),
            ['admin', 'gestionnaire'],
            'application',
            $id
        );

        jsonSuccess('Statut mis a jour avec succes.');
    } catch (PDOException $e) {
        logError('ERROR', 'Update status error: ' . $e->getMessage(), 'api/applications.php', 676);
        jsonError(500, 'Erreur interne. Veuillez reessayer.');
    }
}

// ---------------------------------------------------------------------------
// DELETE -> ARCHIVE (soft delete) (admin/gestionnaire)
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

        $stmt = $pdo->prepare('SELECT nom, prenom, type, statut FROM applications WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetch();

        if (!$current) {
            jsonError(404, 'Demande introuvable.');
        }

        // Soft delete : passage au statut "archive" (les fichiers sont conserves)
        // On memorise le statut d'avant archivage pour permettre une restauration
        // au statut precedent (ou un retour simple en "en_attente").
        $stmt = $pdo->prepare('UPDATE applications SET statut = "archive", statut_precedent = :statut_precedent, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id, ':statut_precedent' => $current['statut']]);

        logAudit(
            'delete_application',
            'application',
            $id,
            'Demande archivee : ' . $current['nom'] . ' ' . ($current['prenom'] ?? '') . ' (' . $current['type'] . ')'
        );

        notify(
            'ticket_supprime',
            'Demande n°' . $id . ' de ' . $current['nom'] . ' (' . $current['type'] . ') archivee par ' . getAdminDisplayName(),
            ['admin', 'gestionnaire'],
            'application',
            $id
        );

        jsonSuccess('La demande a ete archivee.');
    } catch (PDOException $e) {
        logError('ERROR', 'Delete application error: ' . $e->getMessage(), 'api/applications.php', 741);
        jsonError(500, 'Erreur interne. Veuillez reessayer.');
    }
}

// ---------------------------------------------------------------------------
// PERMANENT DELETE (admin uniquement - suppression definitive)
// ---------------------------------------------------------------------------
function handlePermanentDelete(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        jsonError(405, 'Methode non autorisee.');
    }

    if (!isLoggedIn()) {
        jsonError(401, 'Acces refuse.');
    }

    if (!hasPermission('admin')) {
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
            'permanent_delete',
            'application',
            $id,
            'Demande supprimee definitivement : ' . $current['nom'] . ' ' . ($current['prenom'] ?? '') . ' (' . $current['type'] . ')'
        );

        notify(
            'ticket_supprime',
            'Demande n°' . $id . ' de ' . $current['nom'] . ' (' . $current['type'] . ') supprimee definitivement par ' . getAdminDisplayName(),
            ['admin', 'gestionnaire'],
            'application',
            $id
        );

        jsonSuccess('La demande a ete supprimee definitivement.');
    } catch (PDOException $e) {
        logError('ERROR', 'Permanent delete application error: ' . $e->getMessage(), 'api/applications.php', 815);
        jsonError(500, 'Erreur interne. Veuillez reessayer.');
    }
}

// ---------------------------------------------------------------------------
// RESTORE (admin uniquement - restauration depuis les archivees)
// ---------------------------------------------------------------------------
function handleRestore(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError(405, 'Methode non autorisee.');
    }

    if (!isLoggedIn()) {
        jsonError(401, 'Acces refuse.');
    }

    if (!hasPermission('admin')) {
        jsonError(403, 'Permissions insuffisantes.');
    }

    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        jsonError(403, 'Token CSRF invalide. Veuillez recharger la page.');
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        jsonError(400, 'Identifiant invalide.');
    }

    // Mode de restauration :
    //   "precedent" -> retour au statut d'avant archivage (si renseigne, sinon en_attente)
    //   "attente"   -> retour systematique en "en_attente"
    $input   = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $retour  = $input['retour'] ?? 'precedent';
    if (!in_array($retour, ['precedent', 'attente'], true)) {
        $retour = 'precedent';
    }

    try {
        $pdo = getDB();

        $stmt = $pdo->prepare('SELECT nom, prenom, type, statut, statut_precedent FROM applications WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetch();

        if (!$current) {
            jsonError(404, 'Demande introuvable.');
        }

        if ($retour === 'precedent' && !empty($current['statut_precedent'])) {
            $nouveauStatut = $current['statut_precedent'];
        } else {
            $nouveauStatut = 'en_attente';
        }

        $stmt = $pdo->prepare('UPDATE applications SET statut = :statut, statut_precedent = NULL, updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id, ':statut' => $nouveauStatut]);

        logAudit(
            'restore_application',
            'application',
            $id,
            'Demande restauree : ' . $current['nom'] . ' ' . ($current['prenom'] ?? '') . ' (' . $current['type'] . ') -> statut "' . $nouveauStatut . '"'
        );

        notify(
            'ticket_statut',
            'Demande n°' . $id . ' de ' . $current['nom'] . ' (' . $current['type'] . ') restauree par ' . getAdminDisplayName(),
            ['admin', 'gestionnaire'],
            'application',
            $id
        );

        jsonSuccess('La demande a ete restauree.');
    } catch (PDOException $e) {
        logError('ERROR', 'Restore application error: ' . $e->getMessage(), 'api/applications.php', 885);
        jsonError(500, 'Erreur interne. Veuillez reessayer.');
    }
}
