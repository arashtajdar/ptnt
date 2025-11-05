<?php
// Authentication check
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'patente';
$dbUser = getenv('DB_USER') ?: 'admin';
$dbPass = getenv('DB_PASS') ?: 'admin';
$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
$pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// Helper: authenticate user from URL (?user=...&pass=...) or POST data
function authenticate(PDO $pdo): ?array {
    $username = $_GET['user'] ?? $_POST['user'] ?? null;
    $password = $_GET['pass'] ?? $_POST['pass'] ?? null;

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

// Check authentication
$authenticatedUser = authenticate($pdo);
if (!$authenticatedUser) {
    http_response_code(401);
    echo '<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Unauthorized - Patente Quiz</title>
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

// Handle quiz submission via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_quiz') {
    header('Content-Type: application/json');
    
    try {
        $answers = json_decode($_POST['answers'] ?? '[]', true);
        $questionIds = json_decode($_POST['question_ids'] ?? '[]', true);
        
        if (empty($answers) || empty($questionIds)) {
            echo json_encode(['error' => 'No answers or questions provided']);
            exit;
        }
        
        $userId = $authenticatedUser['id'];
        
        // Update stats for each question
        foreach ($questionIds as $index => $questionId) {
            $userAnswer = $answers[$index] ?? null;
            if ($userAnswer === null) continue;
            
            // Get correct answer from database
            $stmt = $pdo->prepare('SELECT answer FROM question WHERE id = ?');
            $stmt->execute([$questionId]);
            $question = $stmt->fetch();
            
            if (!$question) continue;
            
            $correctAnswer = strtoupper(trim($question['answer']));
            $userAnswerUpper = strtoupper($userAnswer);
            
            // Determine if answer is correct
            $isCorrect = ($correctAnswer === 'V' && $userAnswerUpper === 'V') || 
                        ($correctAnswer === 'F' && $userAnswerUpper === 'F');
            
            // Update user_question_stats
            if ($isCorrect) {
                $stmt = $pdo->prepare('INSERT INTO user_question_stats (user_id, question_id, correct, wrong) 
                                     VALUES (?, ?, 1, 0)
                                     ON DUPLICATE KEY UPDATE correct = correct + 1, updated_at = CURRENT_TIMESTAMP');
            } else {
                $stmt = $pdo->prepare('INSERT INTO user_question_stats (user_id, question_id, correct, wrong) 
                                     VALUES (?, ?, 0, 1)
                                     ON DUPLICATE KEY UPDATE wrong = wrong + 1, updated_at = CURRENT_TIMESTAMP');
            }
            $stmt->execute([$userId, $questionId]);
        }
        
        echo json_encode(['success' => true, 'message' => 'Quiz results saved successfully']);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to save quiz results: ' . $e->getMessage()]);
        exit;
    }
}

// Fetch 30 random questions each page load

// Optional varying seed to ensure different order
$seed = random_int(1, PHP_INT_MAX);
$stmt = $pdo->query("SELECT id, text, image, answer FROM question ORDER BY RAND($seed) LIMIT 30");
$questions = $stmt->fetchAll();
if (!$questions) {
    echo 'No questions available in the database.';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patente Quiz</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; }
        .container { max-width: 880px; margin: 0 auto; }
        .question { font-size: 20px; margin: 12px 0 16px; }
        .image { margin: 12px 0; }
        .nav { display: flex; justify-content: space-between; margin: 16px 0; }
        .choices { display: flex; gap: 12px; margin: 16px 0; }
        button { padding: 10px 16px; font-size: 16px; cursor: pointer; }
        .selected { outline: 3px solid #0078D7; }
        .status { color: #666; font-size: 14px; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; border: 1px solid #ddd; margin-left: 8px; font-size: 12px; color: #333; }
        .grid { display: grid; grid-template-columns: repeat(10, 1fr); gap: 6px; margin-top: 16px; }
        .grid button { text-align: center; padding: 6px 0; border: 1px solid #ddd; border-radius: 4px; background: #fff; }
        .grid button.current { background: #eef6ff; border-color: #b6dbff; }
        .grid button.answered { background: #f7f7f7; }
        .grid button.ok { background: #16fa16; border-color: #b9e3b9; }
        .grid button.bad { background: #ff1919; border-color: #f3c2c2; }
        .correct-label { font-size: 14px; color: #333; margin-left: 8px; }
        .hidden { display: none; }
    </style>
</head>
<body>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;">Patente Quiz</h2>
        <a href="index.php<?php echo '?user=' . urlencode($authenticatedUser['username']) . '&pass=' . urlencode($_GET['pass']); ?>" style="background: #6c757d; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; font-size: 14px;">🏠 HOME</a>
    </div>

    <div id="status" class="status"></div>
    <div id="userInfo" class="status" style="color: #28a745; font-weight: bold;">Welcome, <?php echo htmlspecialchars($authenticatedUser['username']); ?>!</div>

    <div id="question" class="question"></div>
    <div id="image" class="image"></div>

    <div class="choices">
        <button id="btnTrue" type="button">True (V)</button>
        <button id="btnFalse" type="button">False (F)</button>
        <span id="correctLabel" class="correct-label"></span>
    </div>

    <div class="nav">
        <div>
            <button id="btnPrev" type="button">« Previous</button>
        </div>
        <div>
            <button id="btnNext" type="button">Next »</button>
        </div>
    </div>

    <div id="grid" class="grid"></div>

    <hr>
    <button id="btnSubmit" type="button">Submit Answers</button>
    <button id="btnRestart" type="button" style="margin-left:8px;">New Quiz</button>

    <div id="results" style="margin-top:16px; display:none;">
        <div id="resultsSummary" style="margin-bottom:8px; font-weight:bold;"></div>
        <div id="resultsList"></div>
        <div id="saveStatus" style="margin-top:8px; font-size:14px; color:#666;"></div>
    </div>
</div>

<script>
const QUESTIONS = <?php echo json_encode($questions, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
const NUM = QUESTIONS.length;
let index = 0;
let answers = new Array(NUM).fill(null); // 'V' or 'F'
let submitted = false;

const elStatus = document.getElementById('status');
const elQuestion = document.getElementById('question');
const elImage = document.getElementById('image');
const elBtnTrue = document.getElementById('btnTrue');
const elBtnFalse = document.getElementById('btnFalse');
const elBtnPrev = document.getElementById('btnPrev');
const elBtnNext = document.getElementById('btnNext');
const elGrid = document.getElementById('grid');
const elSubmit = document.getElementById('btnSubmit');
const elRestart = document.getElementById('btnRestart');
const elResults = document.getElementById('results');
const elResultsSummary = document.getElementById('resultsSummary');
const elResultsList = document.getElementById('resultsList');
const elSaveStatus = document.getElementById('saveStatus');

function render() {
    elStatus.textContent = `Question ${index + 1} / ${NUM}`;
    elQuestion.textContent = (QUESTIONS[index]?.text ?? '');
    const img = QUESTIONS[index]?.image;
    elImage.innerHTML = img ? `<img src="./image/${img}" alt="question image" style="max-width:200px;height:auto;">` : '<img src="./image/no-image.svg" alt="question image" style="max-width:200px;height:auto;">';

    // selection state
    elBtnTrue.classList.toggle('selected', answers[index] === 'V');
    elBtnFalse.classList.toggle('selected', answers[index] === 'F');

    // show correct answer after submit
    const correctAns = String(QUESTIONS[index]?.answer || '').toUpperCase();
    const label = correctAns === 'V' ? '[True]' : correctAns === 'F' ? '[False]' : '';
    const lblEl = document.getElementById('correctLabel');
    if (lblEl) lblEl.textContent = submitted ? `Correct: ${label}` : '';

    elBtnPrev.disabled = index === 0;
    elBtnNext.disabled = index === NUM - 1;

    // grid
    elGrid.innerHTML = '';
    for (let i = 0; i < NUM; i++) {
        const b = document.createElement('button');
        b.textContent = String(i + 1);
        let classes = [];
        if (i === index) classes.push('current');
        if (answers[i]) classes.push('answered');
        if (submitted && answers[i]) {
            const truth = String(QUESTIONS[i].answer || '').toUpperCase();
            classes.push(answers[i].toUpperCase() === truth ? 'ok' : 'bad');
        }
        b.className = classes.join(' ').trim();
        b.onclick = () => { index = i; render(); };
        elGrid.appendChild(b);
    }
}

elBtnTrue.onclick = () => { if (!submitted) { answers[index] = 'V'; if (index < NUM - 1) { index++; } render(); } };
elBtnFalse.onclick = () => { if (!submitted) { answers[index] = 'F'; if (index < NUM - 1) { index++; } render(); } };
elBtnPrev.onclick = () => { if (index > 0) { index--; render(); } };
elBtnNext.onclick = () => { if (index < NUM - 1) { index++; render(); } };

function updateResults() {
    let correct = 0, wrong = 0, unanswered = 0;
    const rows = [];
    for (let i = 0; i < NUM; i++) {
        const a = answers[i];
        const truth = String(QUESTIONS[i].answer || '').toUpperCase();
        const truthLabel = truth === 'V' ? 'True' : truth === 'F' ? 'False' : '-';
        let yourLabel = '- (unanswered)';
        if (a) yourLabel = a.toUpperCase() === 'V' ? 'True' : 'False';
        if (!a) { unanswered++; wrong++; }
        else if (a.toUpperCase() === truth) { correct++; }
        else { wrong++; }
        const icon = !a ? '' : (a.toUpperCase() === truth ? '✅' : '❌');
        rows.push(`<div style="margin:4px 0;">Q${i+1}: Your: <strong>${yourLabel}</strong> | Correct: <strong>${truthLabel}</strong> ${icon}</div>`);
    }
    elResultsSummary.textContent = `Result: Correct ${correct} | Wrong ${wrong} | Unanswered ${unanswered}`;
    elResultsList.innerHTML = rows.join('');
    elResults.style.display = 'block';
}

elSubmit.onclick = async () => {
    if (submitted) return;
    submitted = true;
    updateResults();
    render();
    
    // Show saving status
    elSaveStatus.textContent = 'Saving your results...';
    elSaveStatus.style.color = '#0078D7';
    
    // Send results to server
    try {
        const questionIds = QUESTIONS.map(q => q.id);
        const formData = new FormData();
        formData.append('action', 'submit_quiz');
        formData.append('answers', JSON.stringify(answers));
        formData.append('question_ids', JSON.stringify(questionIds));
        
        // Include authentication parameters in POST request
        const params = new URLSearchParams(window.location.search);
        const user = params.get('user');
        const pass = params.get('pass');
        if (user && pass) {
            formData.append('user', user);
            formData.append('pass', pass);
        }
        
        const response = await fetch(window.location.pathname, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            elSaveStatus.textContent = '✅ Quiz results saved successfully!';
            elSaveStatus.style.color = '#28a745';
            console.log('Quiz results saved successfully');
        } else {
            elSaveStatus.textContent = '❌ Failed to save results: ' + result.error;
            elSaveStatus.style.color = '#dc3545';
            console.error('Failed to save results:', result.error);
        }
    } catch (error) {
        elSaveStatus.textContent = '❌ Error saving quiz results';
        elSaveStatus.style.color = '#dc3545';
        console.error('Error saving quiz results:', error);
    }
};

elRestart.onclick = () => { 
    const params = new URLSearchParams(window.location.search);
    const user = params.get('user');
    const pass = params.get('pass');
    let url = 'quiz.php';
    if (user && pass) {
        url += `?user=${encodeURIComponent(user)}&pass=${encodeURIComponent(pass)}`;
    }
    window.location.href = url; 
};

render();
</script>
</body>
</html>
