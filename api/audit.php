<?php
/**
 * ============================================================================
 * ATLANTIS v2 - API : Journal d'audit
 * ============================================================================
 *
 * Endpoints :
 *   GET ?action=list (admin/super-admin)
 * ============================================================================
 */

require_once __DIR__ . '/../includes/functions.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'list':
        handleList();
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
    $action = $_GET['action_filter'] ?? '';
    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 50;
    $offset = ($page - 1) * $limit;

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(identifiant_snapshot LIKE :search OR action LIKE :search OR details LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    if ($action !== '') {
        $where[]       = 'action = :action';
        $params[':action'] = $action;
    }

    if ($userId && $userId > 0) {
        $where[]       = 'user_id = :user_id';
        $params[':user_id'] = $userId;
    }

    $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSQL = "SELECT COUNT(*) as total FROM audit_log $whereSQL";
    $stmt     = $pdo->prepare($countSQL);
    $stmt->execute($params);
    $total    = (int) $stmt->fetch()['total'];

    $dataSQL = "SELECT * FROM audit_log $whereSQL ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt    = $pdo->prepare($dataSQL);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll();

    jsonSuccess('Journal recuper.', [
        'items' => $logs,
        'total' => $total,
        'page'  => $page,
        'pages' => (int) ceil($total / $limit),
    ]);
}
