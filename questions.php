<?php
// Questions List with Authentication and Filters
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

function getPdo(string $host, string $port, string $dbname, string $user, string $pass, string $charset): PDO {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

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

try {
    $pdo = getPdo($dbHost, $dbPort, $dbName, $dbUser, $dbPass, $dbCharset);
    $user = authenticate($pdo);
    if (!$user) {
        http_response_code(401);
        echo '<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unauthorized - Questions List</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; text-align: center; }
        .container { max-width: 600px; margin: 100px auto; }
        h1 { color: #dc3545; }
        p { color: #666; font-size: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Unauthorized Access</h1>
        <p>Please provide valid credentials via URL parameters: ?user=username&pass=password</p>
    </div>
</body>
</html>';
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    exit;
}

// Handle filters and pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$filterStats = $_GET['filter_stats'] ?? '';
$searchText = trim($_GET['search'] ?? '');

// Build WHERE conditions
$whereConditions = [];
$params = [];

if ($filterStats === 'correct') {
    $whereConditions[] = 'COALESCE(stats.correct, 0) > 0';
}
if ($filterStats === 'wrong') {
    $whereConditions[] = 'COALESCE(stats.wrong, 0) > 0';
}
if ($filterStats === 'none') {
    $whereConditions[] = 'COALESCE(stats.correct, 0) = 0 AND COALESCE(stats.wrong, 0) = 0';
}
if ($searchText !== '') {
    $whereConditions[] = 'q.text LIKE ?';
    $params[] = '%' . $searchText . '%';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total 
               FROM question q 
               LEFT JOIN user_question_stats stats ON q.id = stats.question_id AND stats.user_id = ?
               $whereClause";

$stmt = $pdo->prepare($countQuery);
$stmt->execute(array_merge([$user['id']], $params));
$totalCount = $stmt->fetch()['total'];
$totalPages = ceil($totalCount / $limit);

// Get questions with stats
$query = "SELECT q.id, q.text, q.image, q.answer, q.parent_number, q.question_number,
                 COALESCE(stats.correct, 0) as correct_count,
                 COALESCE(stats.wrong, 0) as wrong_count,
                 COALESCE(stats.updated_at, NULL) as last_attempted
          FROM question q 
          LEFT JOIN user_question_stats stats ON q.id = stats.question_id AND stats.user_id = ?
          $whereClause
          ORDER BY q.id ASC
          LIMIT $limit OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute(array_merge([$user['id']], $params));
$questions = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Questions List - Patente Quiz</title>
    <style>
        :root {
            --bg: #f8f9fa;
            --card: #ffffff;
            --text: #212529;
            --muted: #6c757d;
            --accent: #007bff;
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --border: #dee2e6;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        header {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            margin: 0 0 10px 0;
            color: var(--text);
            font-size: 24px;
        }
        .user-info {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 20px;
        }
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        label {
            font-weight: 500;
            font-size: 14px;
            color: var(--text);
        }
        input, select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 14px;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background: var(--accent);
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: var(--muted);
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .questions-grid {
            display: grid;
            gap: 15px;
        }
        .question-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .question-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 10px;
            gap: 15px;
        }
        .question-id {
            background: var(--accent);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            min-width: 40px;
            text-align: center;
        }
        .question-text {
            flex: 1;
            font-size: 16px;
            line-height: 1.4;
        }
        .question-answer {
            background: var(--success);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
        }
        .question-image {
            margin: 10px 0;
        }
        .question-image img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 4px;
            border: 1px solid var(--border);
        }
        .stats {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .stat {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .stat-correct {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
        }
        .stat-wrong {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
            padding: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text);
        }
        .pagination .current {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        .pagination a:hover {
            background: var(--bg);
        }
        .no-results {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }
        .summary {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: var(--muted);
        }
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .filters { grid-template-columns: 1fr; }
            .question-header { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
<div class="container">
    <header>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h1 style="margin: 0;">Questions List</h1>
            <a href="index.php<?php echo '?user=' . urlencode($user['username']) . '&pass=' . urlencode($_GET['pass']); ?>" class="btn btn-secondary" style="text-decoration: none;">🏠 HOME</a>
        </div>
        <div class="user-info">Welcome, <?php echo htmlspecialchars($user['username']); ?>!</div>
        
        <form method="GET" class="filters">
            <input type="hidden" name="user" value="<?php echo htmlspecialchars($_GET['user'] ?? ''); ?>">
            <input type="hidden" name="pass" value="<?php echo htmlspecialchars($_GET['pass'] ?? ''); ?>">
            
            <div class="filter-group">
                <label for="search">Search in question text:</label>
                <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($searchText); ?>" placeholder="Enter search term...">
            </div>
            
            <div class="filter-group">
                <label for="filter_stats">Filter by answer statistics:</label>
                <select id="filter_stats" name="filter_stats">
                    <option value="">All questions</option>
                    <option value="correct" <?php echo $filterStats === 'correct' ? 'selected' : ''; ?>>Correct answers > 0</option>
                    <option value="wrong" <?php echo $filterStats === 'wrong' ? 'selected' : ''; ?>>Wrong answers > 0</option>
                    <option value="none" <?php echo $filterStats === 'none' ? 'selected' : ''; ?>>No wrong or correct</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>&nbsp;</label>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="?user=<?php echo urlencode($_GET['user'] ?? ''); ?>&pass=<?php echo urlencode($_GET['pass'] ?? ''); ?>" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
        
        <div class="summary">
            Showing <?php echo count($questions); ?> of <?php echo $totalCount; ?> questions 
            (Page <?php echo $page; ?> of <?php echo $totalPages; ?>)
        </div>
    </header>
    
    <?php if (empty($questions)): ?>
        <div class="no-results">
            <h3>No questions found</h3>
            <p>Try adjusting your filters or search terms.</p>
        </div>
    <?php else: ?>
        <div class="questions-grid">
            <?php foreach ($questions as $question): ?>
                <div class="question-card">
                    <div class="question-header">
                        <div class="question-id">#<?php echo $question['id']; ?></div>
                        <div class="question-text"><?php echo htmlspecialchars($question['text'] ?? 'No text'); ?></div>
                        <div class="question-answer"><?php echo htmlspecialchars($question['answer'] ?? '?'); ?></div>
                    </div>
                    
                    <?php if ($question['image']): ?>
                        <div class="question-image">
                            <img src="./image/<?php echo htmlspecialchars($question['image']); ?>" alt="Question image">
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($question['parent_number'] || $question['question_number']): ?>
                        <div style="font-size: 12px; color: var(--muted); margin-top: 8px;">
                            <?php if ($question['parent_number']): ?>
                                Parent: <?php echo $question['parent_number']; ?>
                            <?php endif; ?>
                            <?php if ($question['question_number']): ?>
                                Question: <?php echo $question['question_number']; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="stats">
                        <div class="stat stat-correct">✅ Correct: <?php echo $question['correct_count']; ?></div>
                        <div class="stat stat-wrong">❌ Wrong: <?php echo $question['wrong_count']; ?></div>
                        <?php if ($question['last_attempted']): ?>
                            <div class="stat" style="background: rgba(108, 117, 125, 0.1); color: var(--muted);">
                                Last: <?php echo date('M j, Y', strtotime($question['last_attempted'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">« First</a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">‹ Previous</a>
                <?php endif; ?>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next ›</a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>">Last »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
