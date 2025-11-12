// js/game.js
export class MinesweeperGame {
    constructor(size, mineCount) {
        this.size = size;
        this.mineCount = mineCount;
        this.board = [];
        this.isGameOver = false;
        this.isFirstClick = true;
        this.cellsRevealed = 0;
        this.mineLocations = [];
        this.createBoard();
    }

    createBoard() {
        for (let y = 0; y < this.size; y++) {
            this.board[y] = [];
            for (let x = 0; x < this.size; x++) {
                this.board[y][x] = {
                    isMine: false,
                    isRevealed: false,
                    isFlagged: false,
                    adjacentMines: 0
                };
            }
        }
    }

    placeMines(firstClickX, firstClickY) {
        let minesPlaced = 0;
        while (minesPlaced < this.mineCount) {
            const x = Math.floor(Math.random() * this.size);
            const y = Math.floor(Math.random() * this.size);

            if (!this.board[y][x].isMine && !(x === firstClickX && y === firstClickY)) {
                this.board[y][x].isMine = true;
                this.mineLocations.push({ x, y });
                minesPlaced++;
            }
        }
        this.calculateAdjacentMines();
    }

    calculateAdjacentMines() {
        for (let y = 0; y < this.size; y++) {
            for (let x = 0; x < this.size; x++) {
                if (this.board[y][x].isMine) continue;
                let count = 0;
                for (let dy = -1; dy <= 1; dy++) {
                    for (let dx = -1; dx <= 1; dx++) {
                        if (dx === 0 && dy === 0) continue;
                        const ny = y + dy;
                        const nx = x + dx;
                        if (this.isValid(nx, ny) && this.board[ny][nx].isMine) {
                            count++;
                        }
                    }
                }
                this.board[y][x].adjacentMines = count;
            }
        }
    }

    revealCell(x, y) {
        if (!this.isValid(x, y) || this.board[y][x].isRevealed || this.board[y][x].isFlagged) {
            return { revealedCells: [], result: 'no_change' };
        }

        if (this.isFirstClick) {
            this.placeMines(x, y);
            this.isFirstClick = false;
        }

        const cell = this.board[y][x];
        cell.isRevealed = true;
        
        let revealedCells = [{ x, y, cell }];

        if (cell.isMine) {
            this.isGameOver = true;
            return { revealedCells, result: 'exploded' };
        }

        this.cellsRevealed++;

        if (cell.adjacentMines === 0) {
            for (let dy = -1; dy <= 1; dy++) {
                for (let dx = -1; dx <= 1; dx++) {
                    if (dx === 0 && dy === 0) continue;
                    const { revealedCells: newRevealed } = this.revealCell(x + dx, y + dy);
                    revealedCells = revealedCells.concat(newRevealed);
                }
            }
        }
        
        if (this.checkWin()) {
            this.isGameOver = true;
            return { revealedCells, result: 'win' };
        }

        return { revealedCells, result: 'safe' };
    }

    toggleFlag(x, y) {
        if (this.isGameOver || !this.isValid(x, y) || this.board[y][x].isRevealed) {
            return false;
        }
        const cell = this.board[y][x];
        cell.isFlagged = !cell.isFlagged;
        return true;
    }
    
    reconstructBoard(size, mineLocations) {
        this.size = size;
        this.mineCount = mineLocations.length;
        this.mineLocations = mineLocations;
        this.isFirstClick = false;
        this.createBoard();
        
        for (const {x, y} of mineLocations) {
            this.board[y][x].isMine = true;
        }
        this.calculateAdjacentMines();
    }

    isValid(x, y) {
        return x >= 0 && x < this.size && y >= 0 && y < this.size;
    }

    checkWin() {
        const nonMineCells = this.size * this.size - this.mineCount;
        return this.cellsRevealed === nonMineCells;
    }
}