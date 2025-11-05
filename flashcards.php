<?php
// Simple Flashcards for `translation` table
// Configure DB via environment variables or edit defaults below.

declare(strict_types=1);

// Error reporting (you can disable in production)
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Database configuration
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'patente';
$dbUser = getenv('DB_USER') ?: 'admin';
$dbPass = getenv('DB_PASS') ?: 'admin';
$dbCharset = 'utf8mb4';

// Ensure all variables are defined
if (!isset($dbPort)) {
    $dbPort = '3306';
}

function getPdo(string $host, string $port, string $dbname, string $user, string $pass, string $charset): PDO {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

function jsonResponse($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function computeProgress(PDO $pdo, int $userId): float {
    // Sum user's correct scores
    $stmtSum = $pdo->prepare('SELECT COALESCE(SUM(correct), 0) AS total FROM user_translation_stats WHERE user_id = ?');
    $stmtSum->execute([$userId]);
    $sumRow = $stmtSum->fetch();
    $totalCorrect = (int)($sumRow['total'] ?? 0);

    // Count all translations (each with max score 3)
    $countRow = $pdo->query('SELECT COUNT(*) AS cnt FROM translation')->fetch();
    $totalTranslations = (int)($countRow['cnt'] ?? 0);
    $denominator = $totalTranslations * 3;
    if ($denominator <= 0) return 0.0;

    $percent = ($totalCorrect / $denominator) * 100.0;
    $percent = max(0.0, min(100.0, $percent));
    return round($percent, 2);
}

// Handle API request
// Helper: authenticate user from URL (?user=...&pass=...)
function authenticate(PDO $pdo): ?array {
    $username = $_GET['user'] ?? null;
    $password = $_GET['pass'] ?? null;

    if (!$username || !$password) {
        return null;
    }
    try {
        $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user) return null;
        $hash = $user['password'] ?? '';
        $ok = false;
        // Support both hashed and plain passwords
        if (is_string($hash) && $hash !== '' && strlen($hash) > 20) {
            $ok = password_verify($password, $hash);
        } else {
            $ok = hash_equals((string)$hash, (string)$password);
        }
        if ($ok) {
            return ['id' => (int)$user['id'], 'username' => (string)$user['username']];
        }
        return null;
    } catch (Throwable $e) {
        return null;
    }
}

// Handle API request
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    try {
        $pdo = getPdo($dbHost, $dbPort, $dbName, $dbUser, $dbPass, $dbCharset);
        $user = authenticate($pdo);
        if (!$user) {
            jsonResponse(['error' => 'Unauthorized: provide valid ?user=&pass='], 401);
            exit;
        }
        if ($action === 'random') {
            // Random row for current user, excluding items with score > 5
            $stmt = $pdo->prepare(
                'SELECT t.id, t.italian, t.english, t.persian
                 FROM translation t
                 LEFT JOIN user_translation_stats s
                   ON s.translation_id = t.id AND s.user_id = ?
                 WHERE (s.correct IS NULL OR s.correct <= 3)
                 ORDER BY RAND()
                 LIMIT 1'
            );
            $stmt->execute([(int)$user['id']]);
            $row = $stmt->fetch();
            if (!$row) {
                jsonResponse(['error' => 'No data in translation table'], 404);
                exit;
            }
            $progress = computeProgress($pdo, (int)$user['id']);
            jsonResponse(['data' => $row, 'user' => $user, 'progress' => $progress]);
            exit;
        }
        if ($action === 'answer') {
            $raw = file_get_contents('php://input');
            $payload = json_decode($raw ?: 'null', true) ?: [];
            $translationId = isset($payload['translationId']) ? (int)$payload['translationId'] : 0;
            $result = isset($payload['result']) ? (string)$payload['result'] : '';
            if ($translationId <= 0 || ($result !== 'correct' && $result !== 'wrong')) {
                jsonResponse(['error' => 'Bad request'], 400);
                exit;
            }
            $delta = $result === 'correct' ? 1 : 0;
            // If correct → increment by 1; if wrong → reset to 0
            $stmt = $pdo->prepare('INSERT INTO user_translation_stats (user_id, translation_id, correct)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE correct = CASE WHEN VALUES(correct) = 1 THEN correct + 1 ELSE 0 END, updated_at = CURRENT_TIMESTAMP');
            $stmt->execute([(int)$user['id'], $translationId, $delta]);
            // Return new value
            $stmt2 = $pdo->prepare('SELECT correct FROM user_translation_stats WHERE user_id = ? AND translation_id = ?');
            $stmt2->execute([(int)$user['id'], $translationId]);
            $row = $stmt2->fetch();
            $correct = $row ? (int)$row['correct'] : 0;
            $progress = computeProgress($pdo, (int)$user['id']);
            jsonResponse(['ok' => true, 'correct' => $correct, 'progress' => $progress]);
            exit;
        }
        jsonResponse(['error' => 'Unknown action'], 400);
    } catch (Throwable $e) {
        jsonResponse(['error' => 'Server error', 'detail' => $e->getMessage()], 500);
    }
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flashcards • Italian → English/Persian</title>
    <style>
        :root {
            --bg: #0f172a;
            --card: #111827;
            --muted: #94a3b8;
            --text: #e5e7eb;
            --accent: #60a5fa;
            --accent-2: #34d399;
            --danger: #f87171;
            --shadow: 0 20px 40px rgba(0,0,0,0.35);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: radial-gradient(1200px 600px at 80% -10%, #1f2937 0%, var(--bg) 60%);
            color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .wrap {
            width: min(920px, 100%);
            display: grid;
            gap: 18px;
        }
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #d1d5db;
            letter-spacing: 0.3px;
        }
        .card {
            background: linear-gradient(180deg, #0b1020 0%, var(--card) 100%);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            padding: 28px;
            box-shadow: var(--shadow);
        }
        .italian {
            font-size: clamp(22px, 4vw, 36px);
            font-weight: 700;
            line-height: 1.25;
            letter-spacing: 0.2px;
            margin: 0 0 8px 0;
        }
        .subtitle {
            margin: 0 0 18px 0;
            color: var(--muted);
            font-size: 14px;
        }
        .answers {
            display: grid;
            gap: 12px;
        }
        .answer {
            background: rgba(255,255,255,0.03);
            border: 1px dashed rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 14px 16px;
        }
        .answer label { color: var(--muted); font-size: 12px; display: block; margin-bottom: 6px; }
        .answer .value { font-size: 18px; }
        .hidden .value { filter: blur(10px); opacity: 0.4; }
        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }
        button {
            appearance: none;
            border: 0;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.05s ease, background 0.2s ease;
            color: #0b1020;
        }
        button:hover { transform: translateY(-1px); }
        .primary { background: linear-gradient(135deg, var(--accent) 0%, #3b82f6 100%); color: white; }
        .secondary { background: linear-gradient(135deg, var(--accent-2) 0%, #10b981 100%); color: white; }
        .ghost { background: rgba(255,255,255,0.08); color: var(--text); border: 1px solid rgba(255,255,255,0.12); }
        .danger { background: linear-gradient(135deg, var(--danger) 0%, #ef4444 100%); color: white; }
        .status { color: var(--muted); font-size: 12px; margin-left: auto; align-self: center; }
        .footer { text-align: center; color: var(--muted); font-size: 12px; padding: 8px; }
        .rtl { direction: rtl; font-family: "Vazirmatn", Tahoma, Arial, sans-serif; }
        @media (max-width: 560px) { .controls { justify-content: space-between; } }
        .progressBig {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 50;
            text-align: right;
            font-size: clamp(42px, 10vw, 96px);
            font-weight: 900;
            line-height: 1;
            letter-spacing: 1px;
            color: #ffffff;
            filter: drop-shadow(0 8px 24px rgba(16,185,129,0.25));
            background: linear-gradient(135deg, var(--accent-2), #22c55e);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
            pointer-events: none;
            user-select: none;
        }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <h1 style="margin: 0;">Italian → English / Persian</h1>
            <a href="index.php<?php echo '?user=' . urlencode($_GET['user'] ?? '') . '&pass=' . urlencode($_GET['pass'] ?? ''); ?>" style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px;">🏠 HOME</a>
        </div>
        <div class="status" id="status">Loading…</div>
    </header>
    <section class="card" id="card">
        <div class="progressBig" id="progressBig">0%</div>
        <div class="italian" id="italian">—</div>
        <p class="subtitle">Tap buttons below to reveal translations.</p>

        <div class="answers">
            <div class="answer hidden" id="englishBox">
                <label>English</label>
                <div class="value" id="english">—</div>
            </div>
            <div class="answer hidden rtl" id="persianBox">
                <label>Persian</label>
                <div class="value" id="persian">—</div>
            </div>
        </div>

        <div class="controls">
            <button class="ghost" id="reveal">Show answer</button>
            <button class="secondary" id="hideAll">Hide</button>
            <button class="primary" id="next">Next ↻</button>

            <span class="status" id="idLabel"></span>
            <button class="ghost" id="wrong" style="background-color: red">Wrong</button>
            <button class="ghost" id="correct" style="background-color: green">Correct</button>
        </div>
    </section>
    <div class="footer">Tip: Use Next to practice randomly. Hide resets the answers.</div>
</div>

<script>
const els = {
  status: document.getElementById('status'),
  italian: document.getElementById('italian'),
  english: document.getElementById('english'),
  persian: document.getElementById('persian'),
  englishBox: document.getElementById('englishBox'),
  persianBox: document.getElementById('persianBox'),
  reveal: document.getElementById('reveal'),
  hideAll: document.getElementById('hideAll'),
  next: document.getElementById('next'),
  idLabel: document.getElementById('idLabel')
};
els.progressBig = document.getElementById('progressBig');

let current = null;
let authQS = '';

function setLoading(isLoading) {
  els.status.textContent = isLoading ? 'Loading…' : 'Ready';
}

function hideAnswers() {
  els.englishBox.classList.add('hidden');
  els.persianBox.classList.add('hidden');
}

function revealAll() {
  els.englishBox.classList.remove('hidden');
  els.persianBox.classList.remove('hidden');
}

async function fetchRandom() {
  setLoading(true);
  try {
    const res = await fetch(window.location.pathname + '?action=random' + authQS, {cache: 'no-store'});
    if (!res.ok) throw new Error('Failed to load');
    const json = await res.json();
    if (!json || !json.data) throw new Error('No data');
    current = json.data;
    renderCard(current);
    if (typeof json.progress === 'number') {
      const pctStr = json.progress.toFixed(2) + '%';
      els.status.textContent = 'Ready • ' + pctStr;
      if (els.progressBig) els.progressBig.textContent = pctStr;
    }
  } catch (e) {
    els.status.textContent = 'Error loading data';
    console.error(e);
  } finally {
    setLoading(false);
  }
}

function sanitize(text) {
  if (text === null || text === undefined) return '—';
  return String(text);
}

function renderCard(data) {
  els.italian.textContent = sanitize(data.italian);
  els.english.textContent = sanitize(data.english);
  els.persian.textContent = sanitize(data.persian);
  els.idLabel.textContent = data.id ? '#' + data.id : '';
  hideAnswers();
}

// Event wiring
els.reveal.addEventListener('click', revealAll);
els.hideAll.addEventListener('click', hideAnswers);
els.next.addEventListener('click', fetchRandom);
els.correct = document.getElementById('correct');
els.wrong = document.getElementById('wrong');
async function confirmAndAnswer(result) {
  if (!current || !current.id) return;
  // const label = result === 'correct' ? 'Correct' : 'Wrong';
  // const msg = `Confirm ${label} for:\n${sanitize(current.italian)}\n→ ${sanitize(current.english)} / ${sanitize(current.persian)}?`;
  // const ok = window.confirm(msg);
  // if (!ok) return;
  try {
    await sendAnswer(result);
    fetchRandom();
  } catch (e) {
    // sendAnswer already sets status; stay on current card on failure
  }
}
async function sendAnswer(result) {
  if (!current || !current.id) return;
  try {
    const res = await fetch(window.location.pathname + '?action=answer' + authQS, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({ translationId: current.id, result })
    });
    const json = await res.json().catch(() => null);
    if (!res.ok) throw new Error(json && json.error ? json.error : 'Failed');
    // Show feedback including progress
    const score = (json && typeof json.correct === 'number') ? json.correct : '—';
    const pct = (json && typeof json.progress === 'number') ? json.progress.toFixed(2) + '%' : '—';
    els.status.textContent = 'Score: ' + score + ' • ' + pct;
    if (els.progressBig && typeof json.progress === 'number') {
      els.progressBig.textContent = json.progress.toFixed(2) + '%';
    }
  } catch (e) {
    console.error(e);
    els.status.textContent = 'Save failed';
  }
}
els.correct.addEventListener('click', () => confirmAndAnswer('correct'));
els.wrong.addEventListener('click', () => confirmAndAnswer('wrong'));

// Keyboard shortcuts: S=Show, N/Space=Next, H=Hide
window.addEventListener('keydown', (ev) => {
  const key = ev.key.toLowerCase();
  if (key === 's' || key === 'enter') revealAll();
  if (key === 'h') hideAnswers();
  if (key === 'n' || key === ' ') fetchRandom();
  if (key === '1') confirmAndAnswer('wrong');
  if (key === '2') confirmAndAnswer('correct');
});

// Initial load
// Build auth query string passthrough from current URL (?user=&pass=)
(function initAuthQS(){
  const p = new URLSearchParams(window.location.search);
  const u = p.get('user');
  const pw = p.get('pass');
  authQS = '';
  if (u && pw) {
    const qp = new URLSearchParams({ user: u, pass: pw });
    authQS = '&' + qp.toString();
  }
})();
fetchRandom();
</script>
</body>
</html>


