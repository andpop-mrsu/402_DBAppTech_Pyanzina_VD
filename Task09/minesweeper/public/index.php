<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);

$dbDir = __DIR__ . '/../db';
if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}
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

$app->get('/', function (Request $request, Response $response) {
    return $response
        ->withStatus(302)
        ->withHeader('Location', '/index.html');
});

$app->get('/games', function (Request $request, Response $response) use ($pdo) {
    $stmt = $pdo->query("SELECT id, date, player_name, width, height, mines_count, outcome FROM games ORDER BY id DESC");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $payload = json_encode($games, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/games/{id}', function (Request $request, Response $response, array $args) use ($pdo) {
    $id = (int)$args['id'];

    $stmtGame = $pdo->prepare("SELECT * FROM games WHERE id = ?");
    $stmtGame->execute([$id]);
    $game = $stmtGame->fetch(PDO::FETCH_ASSOC);

    if (!$game) {
        $error = json_encode(['error' => 'Game not found'], JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($error);
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $game['mine_positions'] = json_decode($game['mine_positions'], true);

    $stmtMoves = $pdo->prepare("SELECT move_number, x, y, result FROM moves WHERE game_id = ? ORDER BY move_number ASC");
    $stmtMoves->execute([$id]);
    $moves = $stmtMoves->fetchAll(PDO::FETCH_ASSOC);

    $payload = json_encode(['game' => $game, 'moves' => $moves], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/games', function (Request $request, Response $response) use ($pdo) {
    $input = json_decode($request->getBody()->getContents(), true);

    if (!isset($input['player_name']) || !isset($input['width']) || !isset($input['height']) || !isset($input['mines_count']) || !isset($input['mine_positions'])) {
        $error = json_encode(['error' => 'Invalid input data'], JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($error);
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

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

    $payload = json_encode(['id' => $pdo->lastInsertId()], JSON_UNESCAPED_UNICODE);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
});

$app->post('/step/{gameId}', function (Request $request, Response $response, array $args) use ($pdo) {
    $gameId = (int)$args['gameId'];
    $input = json_decode($request->getBody()->getContents(), true);

    if (!isset($input['move_number']) || !isset($input['x']) || !isset($input['y']) || !isset($input['result'])) {
        $error = json_encode(['error' => 'Missing move data'], JSON_UNESCAPED_UNICODE);
        $response->getBody()->write($error);
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $stmt = $pdo->prepare("INSERT INTO moves (game_id, move_number, x, y, result) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$gameId, $input['move_number'], $input['x'], $input['y'], $input['result']]);

    if (in_array($input['result'], ['lost', 'won'])) {
        $stmtUpdate = $pdo->prepare("UPDATE games SET outcome = ? WHERE id = ?");
        $stmtUpdate->execute([$input['result'], $gameId]);
    }

    $payload = json_encode(['status' => 'ok'], JSON_UNESCAPED_UNICODE);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();