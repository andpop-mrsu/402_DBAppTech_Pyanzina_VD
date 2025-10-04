<?php

namespace iambadatnicknames\minesweeper;

class GameModel
{
    private $size;
    private $mines;
    private $playerName;
    private $mineField;
    private $visibleField;
    private $remainingMines;
    private $gameStarted = false;

    public function initializeGame(int $size, int $mines, string $playerName): void
    {
        $this->size = $size;
        $this->mines = $mines;
        $this->playerName = $playerName;
        $this->remainingMines = $mines;
        $this->gameStarted = false;

        $this->initializeFields();
    }

    private function initializeFields(): void
    {
        $this->mineField = array_fill(0, $this->size, array_fill(0, $this->size, 0));
        $this->visibleField = array_fill(0, $this->size, array_fill(0, $this->size, ' '));
    }

    private function placeMines(int $firstRow, int $firstCol): void
    {
        $minesPlaced = 0;

        while ($minesPlaced < $this->mines) {
            $row = random_int(0, $this->size - 1);
            $col = random_int(0, $this->size - 1);

            if (($row === $firstRow && $col === $firstCol) || $this->mineField[$row][$col] === -1) {
                continue;
            }

            $this->mineField[$row][$col] = -1;
            $minesPlaced++;

            for ($dr = -1; $dr <= 1; $dr++) {
                for ($dc = -1; $dc <= 1; $dc++) {
                    if ($dr === 0 && $dc === 0) {
                        continue;
                    }

                    $nr = $row + $dr;
                    $nc = $col + $dc;

                    if (
                        $nr >= 0 && $nr < $this->size &&
                        $nc >= 0 && $nc < $this->size &&
                        $this->mineField[$nr][$nc] !== -1
                    ) {
                        $this->mineField[$nr][$nc]++;
                    }
                }
            }
        }

        $this->gameStarted = true;
    }

    public function openCell(int $row, int $col): array
    {
        if (!$this->isValidCoordinate($row, $col)) {
            return ['game_over' => false, 'win' => false, 'adjacent_mines' => 0];
        }

        if (!$this->gameStarted) {
            $this->placeMines($row, $col);
        }

        if ($this->visibleField[$row][$col] !== ' ' && $this->visibleField[$row][$col] !== 'F') {
            return ['game_over' => false, 'win' => false, 'adjacent_mines' => 0];
        }

        if ($this->mineField[$row][$col] === -1) {
            $this->visibleField[$row][$col] = '*';
            $this->revealAllMines();
            return ['game_over' => true, 'win' => false, 'adjacent_mines' => 0];
        }


        $this->revealCell($row, $col);

        if ($this->checkWin()) {
            return ['game_over' => true, 'win' => true, 'adjacent_mines' => $this->mineField[$row][$col]];
        }

        return [
            'game_over' => false,
            'win' => false,
            'adjacent_mines' => $this->mineField[$row][$col]
        ];
    }

    private function revealCell(int $row, int $col): void
    {
        if (!$this->isValidCoordinate($row, $col) || $this->visibleField[$row][$col] !== ' ') {
            return;
        }

        $mineCount = $this->mineField[$row][$col];
        $this->visibleField[$row][$col] = $mineCount === 0 ? '0' : (string)$mineCount;

        if ($mineCount === 0) {
            for ($dr = -1; $dr <= 1; $dr++) {
                for ($dc = -1; $dc <= 1; $dc++) {
                    if ($dr === 0 && $dc === 0) {
                        continue;
                    }

                    $this->revealCell($row + $dr, $col + $dc);
                }
            }
        }
    }

    public function toggleFlag(int $row, int $col): bool
    {
        if (!$this->isValidCoordinate($row, $col)) {
            return false;
        }

        $cell = $this->visibleField[$row][$col];

        if ($cell === ' ') {
            $this->visibleField[$row][$col] = 'F';
            $this->remainingMines--;
            return true;
        } elseif ($cell === 'F') {
            $this->visibleField[$row][$col] = ' ';
            $this->remainingMines++;
            return true;
        }

        return false;
    }

    private function revealAllMines(): void
    {
        for ($row = 0; $row < $this->size; $row++) {
            for ($col = 0; $col < $this->size; $col++) {
                if ($this->mineField[$row][$col] === -1 && $this->visibleField[$row][$col] !== '*') {
                    $this->visibleField[$row][$col] = 'X';
                }
            }
        }
    }

    private function checkWin(): bool
    {
        $unrevealedSafeCells = 0;

        for ($row = 0; $row < $this->size; $row++) {
            for ($col = 0; $col < $this->size; $col++) {
                if ($this->visibleField[$row][$col] === ' ' && $this->mineField[$row][$col] !== -1) {
                    $unrevealedSafeCells++;
                }
            }
        }

        return $unrevealedSafeCells === 0;
    }

    public function getVisibleField(): array
    {
        return $this->visibleField;
    }

    public function getFullField(): array
    {
        $field = [];

        for ($row = 0; $row < $this->size; $row++) {
            for ($col = 0; $col < $this->size; $col++) {
                if ($this->mineField[$row][$col] === -1) {
                    $field[$row][$col] = 'X';
                } else {
                    $field[$row][$col] = $this->mineField[$row][$col] === 0
                        ? '0'
                        : (string) $this->mineField[$row][$col];
                }
            }
        }

        return $field;
    }

    public function getRemainingMines(): int
    {
        return $this->remainingMines;
    }

    private function isValidCoordinate(int $row, int $col): bool
    {
        return $row >= 0 && $row < $this->size && $col >= 0 && $col < $this->size;
    }

    public function getMineField(): array
    {
        return $this->mineField;
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getMines(): int
    {
        return $this->mines;
    }
    public function setMineField(array $mineField): void
    {
        $this->mineField = $mineField;
    }

    public function setVisibleField(array $visibleField): void
    {
        $this->visibleField = $visibleField;
    }

    public function setGameStarted(bool $started): void
    {
        $this->gameStarted = $started;
    }
}
