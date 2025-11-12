// js/main.js
import { MinesweeperGame } from './game.js';
import * as db from './db.js';

const playerNameInput = document.getElementById('playerName');
const boardSizeInput = document.getElementById('boardSize');
const mineCountInput = document.getElementById('mineCount');
const newGameBtn = document.getElementById('newGameBtn');
const gameBoardElem = document.getElementById('game-board');
const statusMessageElem = document.getElementById('status-message');
const showHistoryBtn = document.getElementById('showHistoryBtn');
const historyListElem = document.getElementById('history-list');

let currentGame = null;
let currentMoves = [];
let currentGameId = null;
let inReplayMode = false;

document.addEventListener('DOMContentLoaded', async () => {
    await db.initDB();
    newGameBtn.addEventListener('click', startNewGame);
    showHistoryBtn.addEventListener('click', displayHistory);
    historyListElem.addEventListener('click', handleHistoryClick);

    gameBoardElem.addEventListener('click', handleCellClick);
    gameBoardElem.addEventListener('contextmenu', handleRightClick);
});

async function startNewGame() {
    inReplayMode = false;
    const size = parseInt(boardSizeInput.value);
    const mineCount = parseInt(mineCountInput.value);
    const playerName = playerNameInput.value.trim();

    if (!playerName) {
        alert("Пожалуйста, введите имя игрока.");
        return;
    }
    if (mineCount >= size * size) {
        alert("Количество мин должно быть меньше, чем общее количество ячеек.");
        return;
    }
    
    currentGameId = null;
    currentMoves = [];
    currentGame = new MinesweeperGame(size, mineCount);
    
    renderBoard();
    statusMessageElem.textContent = "Удачи! Правый клик - поставить флаг.";
}

function renderBoard() {
    gameBoardElem.innerHTML = '';
    gameBoardElem.style.gridTemplateColumns = `repeat(${currentGame.size}, 1fr)`;
    
    for (let y = 0; y < currentGame.size; y++) {
        for (let x = 0; x < currentGame.size; x++) {
            const cellElem = document.createElement('div');
            cellElem.classList.add('cell');
            cellElem.dataset.x = x;
            cellElem.dataset.y = y;
            gameBoardElem.appendChild(cellElem);
        }
    }
}

async function handleCellClick(event) {
    if (inReplayMode || !currentGame || currentGame.isGameOver) return;

    const cellElem = event.target.closest('.cell');
    if (!cellElem || cellElem.classList.contains('flag')) return;

    const x = parseInt(cellElem.dataset.x);
    const y = parseInt(cellElem.dataset.y);

    const isFirstClick = currentGame.isFirstClick;

    const { revealedCells, result } = currentGame.revealCell(x, y);


    if (isFirstClick) {
        const gameData = {
            playerName: playerNameInput.value.trim(),
            boardSize: currentGame.size,
            mineCount: currentGame.mineCount,
            mineLayout: currentGame.mineLocations,
            date: new Date().toISOString(),
            outcome: 'in_progress'
        };
        currentGameId = await db.saveGame(gameData);
    }
    
    if (result !== 'no_change') {
        currentMoves.push({ x, y });
        if (currentGameId) {
            await db.saveMove({
                gameId: currentGameId,
                moveNumber: currentMoves.length,
                coordinates: `${x},${y}`,
                result: result
            });
        }
        updateBoardUI(revealedCells);
    }
    
    if (result === 'exploded') {
        statusMessageElem.textContent = "Взрыв! Вы проиграли.";
        revealAllMines();
        await db.updateGameOutcome(currentGameId, 'loss');
    } else if (result === 'win') {
        statusMessageElem.textContent = "Поздравляем! Вы выиграли!";
        await db.updateGameOutcome(currentGameId, 'win');
    }
}

function handleRightClick(event) {
    event.preventDefault();

    if (inReplayMode || !currentGame || currentGame.isGameOver) return;

    const cellElem = event.target.closest('.cell');
    if (!cellElem) return;

    const x = parseInt(cellElem.dataset.x);
    const y = parseInt(cellElem.dataset.y);

    if (currentGame.toggleFlag(x, y)) {
        cellElem.classList.toggle('flag');
        cellElem.innerHTML = currentGame.board[y][x].isFlagged ? '🚩' : '';
    }
}

function updateBoardUI(revealedCells) {
    for (const {x, y, cell} of revealedCells) {
        const cellElem = gameBoardElem.querySelector(`[data-x='${x}'][data-y='${y}']`);
        if (!cellElem || cellElem.classList.contains('revealed')) continue;

        cellElem.classList.add('revealed');
        if (cell.adjacentMines > 0) {
            cellElem.textContent = cell.adjacentMines;
            cellElem.classList.add(`c${cell.adjacentMines}`);
        }
    }
}

function revealAllMines() {
    for (let y = 0; y < currentGame.size; y++) {
        for (let x = 0; x < currentGame.size; x++) {
            if (currentGame.board[y][x].isMine) {
                const cellElem = gameBoardElem.querySelector(`[data-x='${x}'][data-y='${y}']`);
                if (!cellElem.classList.contains('flag')) {
                    cellElem.classList.add('mine');
                    cellElem.innerHTML = '💣';
                }
            }
        }
    }
}

async function displayHistory() {
    const games = await db.getAllGames();
    historyListElem.innerHTML = '';
    if (games.length === 0) {
        historyListElem.innerHTML = '<li>Сохраненных игр нет.</li>';
        return;
    }
    
    games.reverse().forEach(game => {
        const li = document.createElement('li');
        li.innerHTML = `
            <span>
                ${new Date(game.date).toLocaleString()} - 
                ${game.playerName} (${game.boardSize}x${game.boardSize}, ${game.mineCount} мин) - 
                <strong>${game.outcome === 'win' ? 'Победа' : game.outcome === 'loss' ? 'Поражение' : 'В процессе'}</strong>
            </span>
            <button class="replay-btn" data-game-id="${game.id}">Повтор</button>
        `;
        historyListElem.appendChild(li);
    });
}

function handleHistoryClick(event) {
    if (event.target.classList.contains('replay-btn')) {
        const gameId = parseInt(event.target.dataset.gameId);
        startReplay(gameId);
    }
}

async function startReplay(gameId) {
    inReplayMode = true;
    const { game, moves } = await db.getGameForReplay(gameId);
    
    currentGame = new MinesweeperGame(game.boardSize, game.mineCount);
    currentGame.reconstructBoard(game.boardSize, game.mineLayout);
    
    renderBoard();
    statusMessageElem.textContent = `Повтор игры от ${new Date(game.date).toLocaleString()}`;
    
    let moveIndex = 0;
    const replayInterval = setInterval(() => {
        if (moveIndex >= moves.length) {
            clearInterval(replayInterval);
            const finalOutcome = moves[moves.length - 1].result;
            if(finalOutcome === 'exploded') {
                revealAllMines();
                statusMessageElem.textContent = "Повтор завершен. Результат: Поражение.";
            } else {
                 statusMessageElem.textContent = "Повтор завершен. Результат: Победа.";
            }
            return;
        }
        
        const move = moves[moveIndex];
        const [x, y] = move.coordinates.split(',').map(Number);
        
        const { revealedCells } = currentGame.revealCell(x, y);
        updateBoardUI(revealedCells);
        
        moveIndex++;
    }, 500);
}