<?php
// Main Menu with Authentication
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
    <title>Unauthorized - Patente App</title>
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

// Build auth query string for links
$authParams = '?user=' . urlencode($user['username']) . '&pass=' . urlencode($_GET['pass']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patente App - Main Menu</title>
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
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            width: 100%;
            background: var(--card);
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, var(--accent) 0%, #0056b3 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
            font-weight: 700;
        }
        .header p {
            margin: 0;
            font-size: 16px;
            opacity: 0.9;
        }
        .user-info {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 20px;
            border-radius: 8px;
            margin-top: 20px;
            display: inline-block;
        }
        .content {
            padding: 40px 30px;
        }
        .apps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        .app-card {
            background: var(--card);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 30px 25px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text);
            position: relative;
            overflow: hidden;
        }
        .app-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        .app-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            border-color: var(--accent);
        }
        .app-card:hover::before {
            transform: scaleX(1);
        }
        .app-icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        .app-card h3 {
            margin: 0 0 10px 0;
            font-size: 20px;
            font-weight: 600;
            color: var(--text);
        }
        .app-card p {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.4;
        }
        .quiz-card {
            border-color: #28a745;
        }
        .quiz-card .app-icon {
            color: #28a745;
        }
        .flashcards-card {
            border-color: #ffc107;
        }
        .flashcards-card .app-icon {
            color: #ffc107;
        }
        .questions-card {
            border-color: #dc3545;
        }
        .questions-card .app-icon {
            color: #dc3545;
        }
        .footer {
            text-align: center;
            padding: 20px 30px;
            border-top: 1px solid var(--border);
            background: #f8f9fa;
            color: var(--muted);
            font-size: 14px;
        }
        @media (max-width: 768px) {
            body { padding: 10px; }
            .header { padding: 30px 20px; }
            .header h1 { font-size: 24px; }
            .content { padding: 30px 20px; }
            .apps-grid { grid-template-columns: 1fr; gap: 20px; }
            .app-card { padding: 25px 20px; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h1 style="margin: 0;">🎓 Patente App</h1>
            <a href="index.php<?php echo $authParams; ?>" style="background: rgba(255, 255, 255, 0.2); color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px; border: 1px solid rgba(255, 255, 255, 0.3);">🏠 HOME</a>
        </div>
        <p>Your Italian Driving License Learning Platform</p>
        <div class="user-info">
            Welcome back, <strong><?php echo htmlspecialchars($user['username']); ?></strong>!
        </div>
    </div>
    
    <div class="content">
        <h2 style="text-align: center; margin: 0 0 20px 0; color: var(--text);">Choose Your Learning Mode</h2>
        
        <div class="apps-grid">
            <a href="quiz.php<?php echo $authParams; ?>" class="app-card quiz-card">
                <div class="app-icon">📝</div>
                <h3>Quiz Mode</h3>
                <p>Take a 30-question quiz with True/False answers. Perfect for testing your knowledge and tracking your progress.</p>
            </a>
            
            <a href="flashcards.php<?php echo $authParams; ?>" class="app-card flashcards-card">
                <div class="app-icon">🔄</div>
                <h3>Flashcards</h3>
                <p>Practice Italian to English/Persian translations with spaced repetition. Learn at your own pace.</p>
            </a>
            
            <a href="questions.php<?php echo $authParams; ?>" class="app-card questions-card">
                <div class="app-icon">📊</div>
                <h3>Questions List</h3>
                <p>Browse all questions with filters and statistics. Review your performance and identify areas for improvement.</p>
            </a>
        </div>
    </div>
    
    <div class="footer">
        <p>🚗 Master the Italian driving license exam with confidence!</p>
    </div>
</div>
</body>
</html>
