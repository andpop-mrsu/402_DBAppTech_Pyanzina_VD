<?php

namespace iambadatnicknames\minesweeper;

class GameView
{
    public function showWelcomeMessage(): void
    {
        \cli\line("=== MINESWEEPER ===");
        \cli\line("Welcome to the classic Minesweeper game!");
        \cli\line("");
    }

    public function displayField(array $field, int $remainingMines): void
    {
        $size = count($field);

        \cli\line("\nRemaining mines: " . $remainingMines);
        \cli\line("");

        $header = "   ";
        for ($col = 0; $col < $size; $col++) {
            $header .= sprintf("%2d ", $col);
        }
        \cli\line($header);
        \cli\line("   " . str_repeat("---", $size));

        for ($row = 0; $row < $size; $row++) {
            $line = sprintf("%2d|", $row);
            for ($col = 0; $col < $size; $col++) {
                $line .= " " . $this->formatCell($field[$row][$col]) . " ";
            }
            \cli\line($line);
        }
        \cli\line("");
    }

    private function formatCell(string $cell): string
    {
        switch ($cell) {
            case ' ':
                return '.';
            case 'F':
                return '⚑';
            case 'X':
                return '💣';
            case '*':
                return '💥';
            default:
                return $cell;
        }
    }

    public function showWinMessage(int $moves): void
    {
        \cli\line("\n🎉 CONGRATULATIONS! 🎉");
        \cli\line("You won the game in $moves moves!");
        \cli\line("All mines have been successfully avoided!");
    }

    public function showGameOverMessage(): void
    {
        \cli\line("\n💥 GAME OVER! 💥");
        \cli\line("You stepped on a mine!");
        \cli\line("Better luck next time!");
    }
}
