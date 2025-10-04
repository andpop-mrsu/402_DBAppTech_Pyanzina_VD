<?php

namespace iambadatnicknames\minesweeper;

use iambadatnicknames\minesweeper\GameController;

class ConsoleApplication
{
    private $controller;
    private $database;

    public function __construct()
    {
        $this->controller = new GameController();
        $this->database = new Database();
    }

    public function run(array $args): void
    {
        array_shift($args);
        $command = $args[0] ?? '--new';

        switch ($command) {
            case '--new':
            case '-n':
                $this->controller->startNewGame();
                break;

            case '--list':
            case '-l':
                $this->showGameList();
                break;

            case '--replay':
            case '-r':
                $id = $args[1] ?? null;
                if ($id) {
                    $this->replayGame($id);
                } else {
                    \cli\err("Error: Game ID is required for replay mode");
                }
                break;

            case '--help':
            case '-h':
                $this->showHelp();
                break;

            default:
                \cli\err("Unknown command: $command");
                $this->showHelp();
                break;
        }
    }

    private function showGameList(): void
    {
        \cli\line("\n=== SAVED GAMES ===\n");

        $games = $this->database->getGameList();

        if (empty($games)) {
            \cli\line("No games saved yet.");
            return;
        }

        foreach ($games as $game) {
            $result = $game['result'] === 'win' ? '✅ WIN' : '❌ LOSE';
            \cli\line(
                sprintf(
                    "[%d] %s | %dx%d | %d mines | %d moves | %s",
                    $game['id'],
                    $game['player_name'],
                    $game['size'],
                    $game['size'],
                    $game['mines'],
                    $game['moves'],
                    $result
                )
            );
        }
        \cli\line("");
    }

    private function replayGame(int $id): void
    {
        $data = $this->database->loadGame($id);

        if (!$data) {
            \cli\err("Game with ID $id not found.");
            return;
        }

        $gameData = $data['game'];
        $cells = $data['cells'];

        \cli\line("\n=== REPLAYING GAME #$id ===");
        \cli\line("Player: {$gameData['player_name']}");
        \cli\line("Size: {$gameData['size']}x{$gameData['size']}, Mines: {$gameData['mines']}");
        \cli\line("Moves: {$gameData['moves']}, Result: " . ($gameData['result'] === 'win' ? '✅ WIN' : '❌ LOSE'));
        \cli\line("Created: {$gameData['created_at']}");
        \cli\line("");

        $model = new GameModel();
        $size = $gameData['size'];
        $mines = $gameData['mines'];
        $model->initializeGame($size, $mines, $gameData['player_name']);

        $mineField = array_fill(0, $size, array_fill(0, $size, 0));
        $visibleField = array_fill(0, $size, array_fill(0, $size, ' '));

        foreach ($cells as $cell) {
            $r = $cell['row'];
            $c = $cell['col'];
            $mineField[$r][$c] = $cell['mine_value'];
            $visibleField[$r][$c] = $cell['visible_state'];
        }

        $model->setMineField($mineField);
        $model->setVisibleField($visibleField);

        $model->setGameStarted(true);

        $view = new GameView();
        $view->displayField($visibleField, $model->getRemainingMines());

        \cli\line("\n🔁 This is a replay of the original game. No interactions allowed.");
        \cli\line("Press Enter to exit...");
        \cli\prompt('', '');
        return;
    }

    private function showHelp(): void
    {
        \cli\line("Minesweeper Game - Console Version");
        \cli\line("Usage: minesweeper [OPTION]");
        \cli\line("");
        \cli\line("Options:");
        \cli\line("  -n, --new\t\tStart a new game (default)");
        \cli\line("  -l, --list\t\tShow list of all saved games");
        \cli\line("  -r, --replay ID\tReplay game with specified ID");
        \cli\line("  -h, --help\t\tShow this help message");
        \cli\line("");
        \cli\line("Game instructions:");
        \cli\line("  Enter coordinates as 'row column' (e.g., '3 5')");
        \cli\line("  Flag mines with 'M row column' (e.g., 'M 3 5')");
    }
}
