<?php

namespace iambadatnicknames\minesweeper;

class Database
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = new \PDO("sqlite:" . __DIR__ . "/../bin/minesweeper.db");
        $this->createTables();
    }

    public function createTables()
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS games (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_name TEXT NOT NULL,
                size INTEGER NOT NULL,
                mines INTEGER NOT NULL,
                moves INTEGER NOT NULL,
                result TEXT CHECK(result IN ('win', 'lose')) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS game_cells (
                game_id INTEGER NOT NULL,
                row INTEGER NOT NULL,
                col INTEGER NOT NULL,
                mine_value INTEGER NOT NULL,
                visible_state TEXT NOT NULL,
                PRIMARY KEY (game_id, row, col),
                FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE
            )
        ");
    }

    public function listGames()
    {
        $stmt = $this->pdo->query("SELECT * FROM games");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getGameById($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$id]);
        $game = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($game) {
            $stmtMoves = $this->pdo->prepare("SELECT * FROM moves WHERE game_id = ? ORDER BY move_number ASC");
            $stmtMoves->execute([$id]);
            $moves = $stmtMoves->fetchAll(\PDO::FETCH_ASSOC);

            $game['moves'] = $moves;
        }

        return $game;
    }

    public function getGameList(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, player_name, size, mines, moves, result, created_at
            FROM games
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function loadGame(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$id]);
        $game = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$game) {
            return null;
        }

        $cellsStmt = $this->pdo->prepare("
            SELECT row, col, mine_value, visible_state
            FROM game_cells
            WHERE game_id = ?
            ORDER BY row, col
        ");
        $cellsStmt->execute([$id]);
        $cells = $cellsStmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'game' => $game,
            'cells' => $cells
        ];
    }

    public function getPlayerName(int $id): ?string
    {
        $stmt = $this->pdo->prepare("SELECT player_name FROM games WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    public function getSize(int $id): ?int
    {
        $stmt = $this->pdo->prepare("SELECT size FROM games WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    public function getMines(int $id): ?int
    {
        $stmt = $this->pdo->prepare("SELECT mines FROM games WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn();
    }

    public function saveGame(GameModel $model, string $result, int $moves): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO games (player_name, size, mines, moves, result)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $model->getPlayerName(),
            $model->getSize(),
            $model->getMines(),
            $moves,
            $result
        ]);

        $gameId = $this->pdo->lastInsertId();

        $cellStmt = $this->pdo->prepare("
            INSERT INTO game_cells (game_id, row, col, mine_value, visible_state)
            VALUES (?, ?, ?, ?, ?)
        ");

        $mineField = $model->getFullField();
        $visibleField = $model->getVisibleField();

        for ($row = 0; $row < $model->getSize(); $row++) {
            for ($col = 0; $col < $model->getSize(); $col++) {
                $mineValue = $model->getMineField()[$row][$col];
                $visibleState = $visibleField[$row][$col];

                $cellStmt->execute([$gameId, $row, $col, $mineValue, $visibleState]);
            }
        }

        return $gameId;
    }
}
