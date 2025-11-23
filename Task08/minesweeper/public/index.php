<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (preg_match('/\.(?:html|css|js|png|jpg)$/', $_SERVER["REQUEST_URI"])) {
    return false;
}

header('Content-Type: application/json; charset=utf-8');

$dbDir = __DIR__ . '/../db';
if (!is_dir($dbDir)) mkdir($dbDir);
$pdo = new PDO('sqlite:' . $dbDir . '/minesweeper.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS games (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date TEXT,
    player_name TEXT,
    width INTEGER,
    height INTEGER,
    mines_count INTEGER,
    mine_positions TEXT,
    outcome TEXT
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS moves (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER,
    move_number INTEGER,
    x INTEGER,
    y INTEGER,
    result TEXT,
    FOREIGN KEY(game_id) REFERENCES games(id)
)");

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

try {
    if ($method === 'GET' && $pathParts[0] === 'games' && !isset($pathParts[1])) {
        $stmt = $pdo->query("SELECT id, date, player_name, width, height, mines_count, outcome FROM games ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($method === 'GET' && $pathParts[0] === 'games' && isset($pathParts[1])) {
        $id = (int)$pathParts[1];
        
        $stmtGame = $pdo->prepare("SELECT * FROM games WHERE id = ?");
        $stmtGame->execute([$id]);
        $game = $stmtGame->fetch(PDO::FETCH_ASSOC);
        
        if (!$game) {
            http_response_code(404);
            echo json_encode(['error' => 'Game not found']);
            exit;
        }
        
        $game['mine_positions'] = json_decode($game['mine_positions']);

        $stmtMoves = $pdo->prepare("SELECT move_number, x, y, result FROM moves WHERE game_id = ? ORDER BY move_number ASC");
        $stmtMoves->execute([$id]);
        $moves = $stmtMoves->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['game' => $game, 'moves' => $moves]);
        exit;
    }

    if ($method === 'POST' && $pathParts[0] === 'games') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $pdo->prepare("INSERT INTO games (date, player_name, width, height, mines_count, mine_positions, outcome) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            date('Y-m-d H:i:s'),
            $input['player_name'],
            $input['width'],
            $input['height'],
            $input['mines_count'],
            json_encode($input['mine_positions']),
            'playing'
        ]);
        
        echo json_encode(['id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($method === 'POST' && $pathParts[0] === 'step' && isset($pathParts[1])) {
        $gameId = (int)$pathParts[1];
        $input = json_decode(file_get_contents('php://input'), true);

        $stmt = $pdo->prepare("INSERT INTO moves (game_id, move_number, x, y, result) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $gameId,
            $input['move_number'],
            $input['x'],
            $input['y'],
            $input['result']
        ]);

        if ($input['result'] === 'lost' || $input['result'] === 'won') {
            $stmtUpdate = $pdo->prepare("UPDATE games SET outcome = ? WHERE id = ?");
            $stmtUpdate->execute([$input['result'], $gameId]);
        }

        echo json_encode(['status' => 'ok']);
        exit;
    }

    if ($path === '/' || $path === '') {
        header("Location: /index.html");
        exit;
    }

    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}