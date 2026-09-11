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

    if (!empty($errors)) {
        jsonError(400, 'Certains champs sont invalides.', ['errors' => $errors]);
    }

    try {
        $pdo  = getDB();
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
        logAudit('create_application', 'application', (int)$newId, 'Nouvelle demande recue : ' . $nom . ' (' . $type . ')');

        jsonSuccess('Merci pour votre demande ! Votre demande a bien ete transmise a l\'equipe ATLANTIS. Nous vous contacterons prochainement.');
    } catch (PDOException $e) {
        error_log('Create application error: ' . $e->getMessage());
        jsonError(500, 'Une erreur interne est survenue. Veuillez reessayer plus tard.');
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
    $dataSQL = "SELECT * FROM applications $whereSQL ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
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

    jsonSuccess('Demande trouvee.', ['item' => $item]);
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
