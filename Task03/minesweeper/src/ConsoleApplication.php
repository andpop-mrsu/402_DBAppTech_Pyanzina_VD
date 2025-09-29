<?php

namespace iambadatnicknames\minesweeper;

use iambadatnicknames\minesweeper\GameController;

class ConsoleApplication
{
    private $controller;

    public function __construct()
    {
        $this->controller = new GameController();
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
        \cli\line("Game list functionality will be available with database support");
        \cli\line("This feature is not implemented yet");
    }

    private function replayGame(string $id): void
    {
        \cli\line("Game replay functionality will be available with database support");
        \cli\line("Replaying game ID: $id (not implemented yet)");
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
        \cli\line("  Note: Database functionality is not implemented yet");
    }
}
