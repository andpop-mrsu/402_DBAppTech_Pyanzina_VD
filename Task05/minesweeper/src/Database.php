<?php

namespace iambadatnicknames\minesweeper;

use RedBeanPHP\R as R;

class Database
{
    public function __construct()
    {
        if (!R::testConnection()) {
            R::setup('sqlite:' . __DIR__ . '/../bin/minesweeper.db');
            R::useFeatureSet('novice/latest');
        }
    }

    public function listGames(): array
    {
        return R::getAll('SELECT * FROM game ORDER BY created_at DESC');
    }

    public function getGameList(): array
    {
        return R::getAll('SELECT id, player_name, size, mines, moves, result, created_at FROM game ORDER BY created_at DESC');
    }

    public function loadGame(int $id): ?array
    {
        $game = R::load('game', $id);
        if (!$game->id) {
            return null;
        }

        $cells = R::findAll('gamecell', 'game_id = ? ORDER BY row, col', [$id]);

        return [
            'game' => $game->export(),
            'cells' => R::exportAll($cells)
        ];
    }

    public function getPlayerName(int $id): ?string
    {
        return R::getCell('SELECT player_name FROM game WHERE id = ?', [$id]);
    }

    public function getSize(int $id): ?int
    {
        return R::getCell('SELECT size FROM game WHERE id = ?', [$id]);
    }

    public function getMines(int $id): ?int
    {
        return R::getCell('SELECT mines FROM game WHERE id = ?', [$id]);
    }

    public function saveGame(GameModel $model, string $result, int $moves): int
    {
        $game = R::dispense('game');
        $game->player_name = $model->getPlayerName();
        $game->size = $model->getSize();
        $game->mines = $model->getMines();
        $game->moves = $moves;
        $game->result = $result;
        $game->created_at = date('Y-m-d H:i:s');

        $gameId = R::store($game);

        $mineField = $model->getMineField();
        $visibleField = $model->getVisibleField();

        foreach ($mineField as $row => $cols) {
            foreach ($cols as $col => $mineValue) {
                $cell = R::dispense('gamecell');
                $cell->game_id = $gameId;
                $cell->row = $row;
                $cell->col = $col;
                $cell->mine_value = $mineValue;
                $cell->visible_state = $visibleField[$row][$col];
                R::store($cell);
            }
        }

        return $gameId;
    }
}