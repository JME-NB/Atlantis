<?php
/**
 * ============================================================================
 * ATLANTIS v2 - API : Journalisation des erreurs
 * ============================================================================
 *
 * Endpoints :
 *   GET  ?action=list (admin)
 *   POST ?action=delete&id=X (admin)
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'list':
        handleList();
        break;
    case 'delete':
        handleDelete();
        break;
    default:
        jsonError(400, 'Action inconnue.');
}

// ---------------------------------------------------------------------------
// LIST
// ---------------------------------------------------------------------------
function handleList(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError(405, 'Methode non autorisee.');
    if (!isLoggedIn()) jsonError(401, 'Acces refuse.');
    if (!hasPermission('admin')) jsonError(403, 'Permissions insuffisantes.');

    $pdo    = getDB();
    $search = $_GET['search'] ?? '';
    $niveau = $_GET['niveau'] ?? '';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(message LIKE :search OR fichier LIKE :search OR url LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    if ($niveau !== '') {
        $where[] = 'niveau = :niveau';
        $params[':niveau'] = $niveau;
    }

    $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSQL = "SELECT COUNT(*) as total FROM error_logs $whereSQL";
    $stmt     = $pdo->prepare($countSQL);
    $stmt->execute($params);
    $total    = (int) $stmt->fetch()['total'];

    $dataSQL = "SELECT * FROM error_logs $whereSQL ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt    = $pdo->prepare($dataSQL);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

    jsonSuccess('Journal recupere.', [
        'items' => $logs,
        'total' => $total,
        'page'  => $page,
        'pages' => (int) ceil($total / $limit),
    ]);
}

// ---------------------------------------------------------------------------
// DELETE
// ---------------------------------------------------------------------------
function handleDelete(): void
{
    adminApiGuard('POST', 'admin');

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) jsonError(400, 'Identifiant invalide.');

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare('DELETE FROM error_logs WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) jsonError(404, 'Entree introuvable.');

        logAudit('delete_log', 'error_log', $id, 'Log entry #' . $id . ' supprime');
        jsonSuccess('Entree supprimee.');
    } catch (PDOException $e) {
        logError('ERROR', 'Delete log entry failed: ' . $e->getMessage(), 'api/logs.php', 0, 'delete_log');
        jsonError(500, 'Erreur interne.');
    }
}
