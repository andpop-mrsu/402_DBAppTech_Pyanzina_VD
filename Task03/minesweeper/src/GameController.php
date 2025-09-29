<?php

namespace iambadatnicknames\minesweeper;

class GameController
{
    private $model;
    private $view;

    public function __construct()
    {
        $this->model = new GameModel();
        $this->view = new GameView();
    }

    public function startNewGame(): void
    {
        $this->view->showWelcomeMessage();

        $size = (int) \cli\prompt("Enter field size", 10);
        $mines = (int) \cli\prompt("Enter number of mines", 15);
        $playerName = \cli\prompt("Enter your name", "Player");

        if ($size < 5) {
            $size = 5;
            \cli\line("Field size set to minimum: 5");
        }
        if ($mines >= $size * $size) {
            $mines = $size * $size - 1;
            \cli\line("Number of mines reduced to: $mines");
        }
        if ($mines < 1) {
            $mines = 1;
            \cli\line("Number of mines set to minimum: 1");
        }

        $this->model->initializeGame($size, $mines, $playerName);
        $this->playGame();
    }

    private function playGame(): void
    {
        $gameOver = false;
        $moveCount = 0;

        while (!$gameOver) {
            $this->view->displayField(
                $this->model->getVisibleField(),
                $this->model->getRemainingMines()
            );

            $input = \cli\prompt("Enter coordinates (row column) or 'M row column' to flag");

            if (empty($input)) {
                continue;
            }

            $parts = explode(' ', $input);
            $command = strtoupper($parts[0]);

            if ($command === 'M' && count($parts) === 3) {
                $row = (int) $parts[1];
                $col = (int) $parts[2];
                $result = $this->model->toggleFlag($row, $col);
                \cli\line($result ? "Cell flagged/unflagged" : "Invalid coordinates");
            } elseif (count($parts) === 2) {
                $row = (int) $parts[0];
                $col = (int) $parts[1];
                $moveCount++;

                $result = $this->model->openCell($row, $col);

                if ($result['game_over']) {
                    $gameOver = true;
                    $this->view->displayField($this->model->getFullField(), 0);

                    if ($result['win']) {
                        $this->view->showWinMessage($moveCount);
                    } else {
                        $this->view->showGameOverMessage();
                    }
                } else {
                    \cli\line("Cell opened. Adjacent mines: " . $result['adjacent_mines']);
                }
            } else {
                \cli\line("Invalid input format. Use 'row column' or 'M row column'");
            }
        }
    }
}
