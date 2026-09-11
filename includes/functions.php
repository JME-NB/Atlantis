<?php
/**
 * ============================================================================
 * ATLANTIS v2 - Fonctions utilitaires
 * ============================================================================
 *
 * Helpers utilises dans tout le projet : nettoyage de chaines,
 * validation, reponses JSON, audit, etc.
 * ============================================================================
 */

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------------------
// NETTOYAGE ET VALIDATION
// ---------------------------------------------------------------------------

/**
 * Nettoie une chaine de caracteres (trim + htmlspecialchars).
 *
 * @param string $value
 * @return string
 */
function clean(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

/**
 * Nettoie une chaine sans HTML encoding (pour stockage brut, ex: email).
 *
 * @param string $value
 * @return string
 */
function cleanRaw(string $value): string
{
    return trim($value);
}

/**
 * Verifie si un numero de telephone est valide.
 * Accepte chiffres, espaces, +, -, parentheses. Longueur 8 a 20.
 *
 * @param string $phone
 * @return bool
 */
function isValidPhone(string $phone): bool
{
    return (bool) preg_match('/^[0-9+\-\s()]{8,20}$/', $phone);
}

/**
 * Verifie si une adresse email est valide.
 *
 * @param string $email
 * @return bool
 */
function isValidEmail(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ---------------------------------------------------------------------------
// REPONSES JSON
// ---------------------------------------------------------------------------

/**
 * Envoie une reponse JSON avec le code HTTP specifie.
 *
 * @param int    $statusCode
 * @param array  $data
 * @return never
 */
function jsonResponse(int $statusCode, array $data): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Reponse de succes rapide.
 *
 * @param string $message
 * @param array  $extra
 * @return never
 */
function jsonSuccess(string $message, array $extra = []): void
{
    jsonResponse(200, array_merge(['success' => true, 'message' => $message], $extra));
}

/**
 * Reponse d'erreur rapide.
 *
 * @param int    $code
 * @param string $message
 * @param array  $extra
 * @return never
 */
function jsonError(int $code, string $message, array $extra = []): void
{
    jsonResponse($code, array_merge(['success' => false, 'message' => $message], $extra));
}

// ---------------------------------------------------------------------------
// JOURNAL D'AUDIT
// ---------------------------------------------------------------------------

/**
 * Enregistre une action dans le journal d'audit.
 *
 * @param string      $action         Description de l'action
 * @param string|null $cibleType      Type d'entite ('application', 'user', 'settings', etc.)
 * @param int|null    $cibleId        ID de l'entite concernee
 * @param string|null $details        Description detaillee
 */
function logAudit(
    string $action,
    ?string $cibleType = null,
    ?int $cibleId = null,
    ?string $details = null
): void {
    ensureSession();

    // Si pas connecte (formulaire public par ex.), on enregistre quand meme
    // avec user_id = NULL.
    $userId       = $_SESSION['admin_id'] ?? null;
    $identifiant  = $_SESSION['admin_username'] ?? 'systeme';
    $role         = $_SESSION['admin_role'] ?? 'systeme';
    $ip           = $_SERVER['REMOTE_ADDR'] ?? null;

    try {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO audit_log
                (user_id, identifiant_snapshot, role_snapshot, action, cible_type, cible_id, details, adresse_ip)
             VALUES
                (:user_id, :identifiant, :role, :action, :cible_type, :cible_id, :details, :ip)'
        );
        $stmt->execute([
            ':user_id'     => $userId,
            ':identifiant' => $identifiant,
            ':role'        => $role,
            ':action'      => $action,
            ':cible_type'  => $cibleType,
            ':cible_id'    => $cibleId,
            ':details'     => $details,
            ':ip'          => $ip,
        ]);
    } catch (PDOException $e) {
        // L'audit ne doit jamais faire echouer l'operation principale.
        error_log('Audit log failed: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// SESSIONS
// ---------------------------------------------------------------------------

/**
 * Demarre la session si elle n'est pas encore active.
 * Les reglages de securite du cookie doivent etre appliques AVANT session_start().
 */
function ensureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);
        session_start();
    }
}

// ---------------------------------------------------------------------------
// SECURITE SESSIONS
// ---------------------------------------------------------------------------

/**
 * Demarre une session admin avec les parametres de securite.
 * Doit etre appele apres une connexion reussie.
 */
function startAdminSession(): void
{
    ensureSession();

    session_regenerate_id(true);
}

/**
 * Verifie si l'utilisateur est connecte en tant qu'admin.
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    ensureSession();
    return isset($_SESSION['admin_id']);
}

/**
 * Redirige vers login.php si non connecte.
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Redirige vers le dashboard si le role n'est pas autorise.
 *
 * @param string|array $roles
 */
function requireRole(string|array $roles): void
{
    if (!hasPermission($roles)) {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }
}

/**
 * Retourne l'ID de l'admin connecte.
 *
 * @return int|null
 */
function getAdminId(): ?int
{
    ensureSession();
    return $_SESSION['admin_id'] ?? null;
}

/**
 * Retourne le nom d'utilisateur de l'admin connecte.
 *
 * @return string
 */
function getAdminUsername(): string
{
    ensureSession();
    return $_SESSION['admin_username'] ?? '';
}

/**
 * Retourne le role de l'admin connecte.
 *
 * @return string|null
 */
function getAdminRole(): ?string
{
    ensureSession();
    return $_SESSION['admin_role'] ?? null;
}

/**
 * Verifie si l'admin connecte a l'un des roles donnes.
 *
 * @param string|array $roles
 * @return bool
 */
function hasPermission(string|array $roles): bool
{
    $role = getAdminRole();
    if ($role === null) return false;

    $allowed = is_array($roles) ? $roles : [$roles];
    return in_array($role, $allowed, true);
}

/**
 * Verifie si l'admin connecte est le super admin.
 *
 * @return bool
 */
function isSuperAdmin(): bool
{
    ensureSession();
    return isset($_SESSION['admin_is_super']) && $_SESSION['admin_is_super'] === true;
}

/**
 * Verifie les tentatives de connexion echouees.
 * Retourne true si le compte est bloque.
 *
 * @param string $identifiant
 * @return bool
 */
function isLoginLocked(string $identifiant): bool
{
    ensureSession();

    $lockKey = 'login_lock_' . md5($identifiant);

    if (!isset($_SESSION[$lockKey])) {
        return false;
    }

    $lockData = $_SESSION[$lockKey];

    // Verifier si le delai de blocage est passe
    if (time() - $lockData['time'] > LOGIN_LOCKOUT_TIME) {
        unset($_SESSION[$lockKey]);
        return false;
    }

    return $lockData['attempts'] >= MAX_LOGIN_ATTEMPTS;
}

/**
 * Enregistre une tentative de connexion echouee.
 *
 * @param string $identifiant
 */
function recordFailedLogin(string $identifiant): void
{
    ensureSession();

    $lockKey = 'login_lock_' . md5($identifiant);

    if (!isset($_SESSION[$lockKey])) {
        $_SESSION[$lockKey] = ['attempts' => 0, 'time' => time()];
    }

    $_SESSION[$lockKey]['attempts']++;
    $_SESSION[$lockKey]['time'] = time();
}

/**
 * Reinitialise le compteur de tentatives apres connexion reussie.
 *
 * @param string $identifiant
 */
function resetLoginAttempts(string $identifiant): void
{
    ensureSession();
    unset($_SESSION['login_lock_' . md5($identifiant)]);
}

// ---------------------------------------------------------------------------
// FORMATAGE
// ---------------------------------------------------------------------------

/**
 * Formate une date en format lisible (francais).
 *
 * @param string $date
 * @return string
 */
function formatDate(string $date): string
{
    $dt = new DateTime($date);
    return $dt->format('d/m/Y \à H:i');
}

/**
 * Retourne le libelle francais d'un statut.
 *
 * @param string $statut
 * @return string
 */
function statutLabel(string $statut): string
{
    $labels = [
        'en_attente' => 'En attente',
        'en_cours'   => 'En cours',
        'valide'     => 'Valide',
        'refuse'     => 'Refuse',
        'archive'    => 'Archive',
    ];
    return $labels[$statut] ?? $statut;
}

/**
 * Retourne la classe CSS d'un statut (pour les badges).
 *
 * @param string $statut
 * @return string
 */
function statutClass(string $statut): string
{
    $classes = [
        'en_attente' => 'badge-warning',
        'en_cours'   => 'badge-info',
        'valide'     => 'badge-success',
        'refuse'     => 'badge-danger',
        'archive'    => 'badge-secondary',
    ];
    return $classes[$statut] ?? 'badge-secondary';
}

/**
 * Retourne le libelle francais d'un type de demande.
 *
 * @param string $type
 * @return string
 */
function typeLabel(string $type): string
{
    $labels = [
        'partenariat' => 'Partenariat',
        'recrutement' => 'Recrutement',
    ];
    return $labels[$type] ?? $type;
}

/**
 * Retourne l'icone d'un type de demande.
 *
 * @param string $type
 * @return string
 */
function typeIcon(string $type): string
{
    $icons = [
        'partenariat' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'recrutement' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>',
    ];
    return $icons[$type] ?? '';
}

/**
 * Nettoie l'identifiant pour un usage URL-safe.
 *
 * @param string $str
 * @return string
 */
function slugify(string $str): string
{
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9-]/', '-', $str);
    $str = preg_replace('/-+/', '-', $str);
    return trim($str, '-');
}
