<?php
/**
 * BVA League Management API
 * Single endpoint for all league admin operations.
 * Deployed to SiteGround; PHP sessions handle authentication.
 */

// ── Config ────────────────────────────────────────────────────────────────────
define('DATA_DIR',    dirname(__DIR__) . '/content/league-data');
define('TEAMS_FILE',  DATA_DIR . '/teams.json');
define('INDEX_FILE',  DATA_DIR . '/index.json');
define('PIN_FILE',    __DIR__ . '/pin.hash');
define('SESSION_KEY', 'bva_league_auth');

// ── Bootstrap ─────────────────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

session_start();

$action = $_GET['action'] ?? '';

// ── Route ─────────────────────────────────────────────────────────────────────
try {
    switch ($action) {
        // Public routes (no auth required)
        case 'check-setup': respond(check_setup());                    break;
        case 'setup-pin':   respond(setup_pin());                      break;
        case 'auth':        respond(do_auth());                        break;
        case 'logout':      session_destroy(); respond(['ok' => true]); break;

        // Protected routes
        default:
            require_auth();
            switch ($action) {
                case 'get-teams':    respond(get_teams());          break;
                case 'add-team':     respond(add_team());           break;
                case 'delete-team':  respond(delete_team());        break;
                case 'import-teams': respond(import_teams());       break;
                case 'get-nights':   respond(get_nights());         break;
                case 'get-night':    respond(get_night());          break;
                case 'save-night':   respond(save_night());         break;
                case 'delete-night': respond(delete_night());       break;
                default: error(400, 'Unknown action');
            }
    }
} catch (Throwable $e) {
    error(500, $e->getMessage());
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function respond(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function error(int $code, string $msg): void {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function require_auth(): void {
    if (empty($_SESSION[SESSION_KEY])) error(401, 'Not authenticated');
}

function safe_path(string $filename): string {
    // Ensure the file resolves inside DATA_DIR — prevent path traversal
    $basename = basename($filename);
    $resolved = realpath(DATA_DIR) . '/' . $basename;
    if (strpos($resolved, realpath(DATA_DIR)) !== 0) {
        error(400, 'Invalid filename');
    }
    return $resolved;
}

function read_json(string $path): array {
    if (!file_exists($path)) return [];
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function write_json(string $path, array $data): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function rebuild_index(): void {
    $files = array_values(array_filter(
        array_map('basename', glob(DATA_DIR . '/*.json') ?: []),
        fn($f) => $f !== 'index.json' && $f !== 'teams.json'
    ));
    sort($files);
    write_json(INDEX_FILE, $files);
}

function body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ── Auth ──────────────────────────────────────────────────────────────────────

function check_setup(): array {
    $pinExists = file_exists(PIN_FILE) && filesize(PIN_FILE) > 0;
    $authed    = !empty($_SESSION[SESSION_KEY]);
    return ['setup' => $pinExists, 'authed' => $authed];
}

function setup_pin(): array {
    if (file_exists(PIN_FILE) && filesize(PIN_FILE) > 0) {
        error(403, 'PIN already configured');
    }
    $b = body();
    $pin = trim($b['pin'] ?? '');
    if (strlen($pin) < 4) error(400, 'PIN must be at least 4 characters');
    file_put_contents(PIN_FILE, password_hash($pin, PASSWORD_DEFAULT));
    $_SESSION[SESSION_KEY] = true;
    return ['ok' => true];
}

function do_auth(): array {
    if (!file_exists(PIN_FILE)) error(403, 'PIN not set up');
    $b   = body();
    $pin = trim($b['pin'] ?? '');
    $hash = file_get_contents(PIN_FILE);
    if (!password_verify($pin, $hash)) error(401, 'Incorrect PIN');
    $_SESSION[SESSION_KEY] = true;
    return ['ok' => true];
}

// ── Teams ─────────────────────────────────────────────────────────────────────

function get_teams(): array {
    return read_json(TEAMS_FILE);
}

function add_team(): array {
    $b      = body();
    $name   = trim($b['name']   ?? '');
    $league = trim($b['league'] ?? '');
    $rung   = trim($b['rung']   ?? '');
    if (!$name || !$league || !$rung) error(400, 'name, league and rung are required');

    $teams = read_json(TEAMS_FILE);
    if (!isset($teams[$league])) $teams[$league] = [];
    if (!isset($teams[$league][$rung])) $teams[$league][$rung] = [];
    if (!in_array($name, $teams[$league][$rung], true)) {
        $teams[$league][$rung][] = $name;
        sort($teams[$league][$rung]);
    }
    write_json(TEAMS_FILE, $teams);
    return ['ok' => true, 'teams' => $teams];
}

function delete_team(): array {
    $b      = body();
    $name   = trim($b['name']   ?? '');
    $league = trim($b['league'] ?? '');
    $rung   = trim($b['rung']   ?? '');
    if (!$name || !$league || !$rung) error(400, 'name, league and rung are required');

    $teams = read_json(TEAMS_FILE);
    if (isset($teams[$league][$rung])) {
        $teams[$league][$rung] = array_values(
            array_filter($teams[$league][$rung], fn($t) => $t !== $name)
        );
        if (empty($teams[$league][$rung])) unset($teams[$league][$rung]);
        if (empty($teams[$league])) unset($teams[$league]);
    }
    write_json(TEAMS_FILE, $teams);
    return ['ok' => true, 'teams' => $teams];
}

function import_teams(): array {
    if (empty($_FILES['csv'])) error(400, 'No CSV file uploaded');
    $file = $_FILES['csv']['tmp_name'];
    if (!is_uploaded_file($file)) error(400, 'Invalid upload');

    $handle = fopen($file, 'r');
    if (!$handle) error(500, 'Could not read CSV');

    $teams   = read_json(TEAMS_FILE);
    $imported = 0;
    $first    = true;

    while (($row = fgetcsv($handle)) !== false) {
        // Skip blank rows
        $row = array_map('trim', $row);
        if (count(array_filter($row)) === 0) continue;

        // Skip header row (first non-empty row, or if first cell looks like a header)
        if ($first) {
            $first = false;
            $lower = strtolower($row[0] ?? '');
            if (in_array($lower, ['team name', 'team', 'name'], true)) continue;
        }

        $name   = $row[0] ?? '';
        $league = strtolower($row[1] ?? '');
        $rung   = $row[2] ?? '';

        if (!$name || !$league || !$rung) continue;

        if (!isset($teams[$league])) $teams[$league] = [];
        if (!isset($teams[$league][$rung])) $teams[$league][$rung] = [];
        if (!in_array($name, $teams[$league][$rung], true)) {
            $teams[$league][$rung][] = $name;
            $imported++;
        }
    }
    fclose($handle);

    // Sort team lists
    foreach ($teams as &$rungs) {
        foreach ($rungs as &$list) sort($list);
    }

    write_json(TEAMS_FILE, $teams);
    return ['ok' => true, 'imported' => $imported, 'teams' => $teams];
}

// ── Match Nights ──────────────────────────────────────────────────────────────

function get_nights(): array {
    $index = read_json(INDEX_FILE);
    $nights = [];
    foreach ($index as $filename) {
        $path = DATA_DIR . '/' . $filename;
        if (!file_exists($path)) continue;
        $data = read_json($path);
        $matchCount = 0;
        foreach ($data['rungs'] ?? [] as $rung) {
            $matchCount += count($rung['matches'] ?? []);
        }
        $nights[] = [
            'file'       => $filename,
            'league'     => $data['league'] ?? '',
            'date'       => $data['date']   ?? '',
            'rungCount'  => count($data['rungs'] ?? []),
            'matchCount' => $matchCount,
        ];
    }
    // Sort newest first
    usort($nights, fn($a, $b) => strcmp($b['date'], $a['date']));
    return $nights;
}

function get_night(): array {
    $file = basename($_GET['file'] ?? '');
    if (!$file) error(400, 'file param required');
    $path = safe_path($file);
    if (!file_exists($path)) error(404, 'Match night not found');
    return read_json($path);
}

function save_night(): array {
    $b = body();
    $league = trim($b['league'] ?? '');
    $date   = trim($b['date']   ?? '');
    $rungs  = $b['rungs'] ?? [];

    if (!$league || !$date) error(400, 'league and date are required');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) error(400, 'Invalid date format (YYYY-MM-DD)');
    if (!in_array($league, ['beach', 'indoor', 'grass'], true)) error(400, 'Invalid league');

    $filename = $league . '-' . $date . '.json';
    $path     = DATA_DIR . '/' . $filename;

    $night = [
        'league' => $league,
        'date'   => $date,
        'rungs'  => array_values(array_filter($rungs, fn($r) => !empty($r['name']))),
    ];

    write_json($path, $night);
    rebuild_index();
    return ['ok' => true, 'file' => $filename];
}

function delete_night(): array {
    $b    = body();
    $file = basename($b['file'] ?? '');
    if (!$file) error(400, 'file param required');
    $path = safe_path($file);

    if (file_exists($path)) unlink($path);
    rebuild_index();
    return ['ok' => true];
}
