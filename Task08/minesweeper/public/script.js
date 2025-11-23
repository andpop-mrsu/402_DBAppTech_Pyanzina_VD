let currentGameId = null;
let boardData = [];
let size = 10;
let moveCounter = 0;
let isReplayMode = false;
let replayMoves = [];
let replayCurrentIndex = 0;


async function apiCreateGame(data) {
    const res = await fetch('/games', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return await res.json();
}

async function apiSendStep(id, data) {
    await fetch(`/step/${id}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
}

async function apiGetGames() {
    const res = await fetch('/games');
    return await res.json();
}

async function apiGetGameDetails(id) {
    const res = await fetch(`/games/${id}`);
    return await res.json();
}


function showScreen(id) {
    document.querySelectorAll('.screen').forEach(s => s.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
}

function showNewGameScreen() {
    showScreen('new-game-screen');
}

async function loadGamesList() {
    const games = await apiGetGames();
    const tbody = document.querySelector('#games-table tbody');
    tbody.innerHTML = '';
    games.forEach(g => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${g.date}</td>
            <td>${g.player_name}</td>
            <td>${g.width}x${g.height}</td>
            <td>${g.mines_count}</td>
            <td>${translateOutcome(g.outcome)}</td>
            <td><button onclick="startReplay(${g.id})">Повтор</button></td>
        `;
        tbody.appendChild(tr);
    });
    showScreen('list-screen');
}

function translateOutcome(outcome) {
    if (outcome === 'playing') return 'Не закончена';
    if (outcome === 'won') return 'Победа';
    if (outcome === 'lost') return 'Проигрыш';
    return outcome;
}


async function startGame() {
    const playerName = document.getElementById('player-name').value;
    size = parseInt(document.getElementById('field-size').value);
    const minesCount = parseInt(document.getElementById('mines-count').value);

    const minePositions = [];
    while (minePositions.length < minesCount) {
        const x = Math.floor(Math.random() * size);
        const y = Math.floor(Math.random() * size);
        if (!minePositions.some(p => p.x === x && p.y === y)) {
            minePositions.push({x, y});
        }
    }

    const gameData = {
        player_name: playerName,
        width: size,
        height: size,
        mines_count: minesCount,
        mine_positions: minePositions
    };

    const response = await apiCreateGame(gameData);
    currentGameId = response.id;
    
    isReplayMode = false;
    moveCounter = 0;
    initBoard(size, minePositions);
    showScreen('game-screen');
    document.getElementById('game-status').innerText = 'Игра идет';
    document.getElementById('game-status').style.color = 'black';
    document.getElementById('replay-controls').classList.add('hidden');
}

function initBoard(boardSize, minePositions) {
    const boardDiv = document.getElementById('board');
    boardDiv.style.gridTemplateColumns = `repeat(${boardSize}, 30px)`;
    boardDiv.innerHTML = '';
    boardData = [];

    for (let y = 0; y < boardSize; y++) {
        for (let x = 0; x < boardSize; x++) {
            const isMine = minePositions.some(p => p.x === x && p.y === y);
            
            const cellData = { x, y, isMine, isOpen: false, isFlagged: false, count: 0 };
            boardData.push(cellData);

            const cellDiv = document.createElement('div');
            cellDiv.className = 'cell';
            cellDiv.dataset.x = x;
            cellDiv.dataset.y = y;
            
            cellDiv.onclick = () => handleCellClick(cellData);
            
            cellDiv.oncontextmenu = (e) => {
                e.preventDefault();
                handleRightClick(cellData);
                return false;
            };
            
            boardDiv.appendChild(cellDiv);
        }
    }

    boardData.forEach(cell => {
        if (!cell.isMine) {
            cell.count = countMinesAround(cell.x, cell.y);
        }
    });
}

function countMinesAround(x, y) {
    let count = 0;
    for (let dy = -1; dy <= 1; dy++) {
        for (let dx = -1; dx <= 1; dx++) {
            const nx = x + dx;
            const ny = y + dy;
            const neighbor = boardData.find(c => c.x === nx && c.y === ny);
            if (neighbor && neighbor.isMine) count++;
        }
    }
    return count;
}

function handleRightClick(cell) {
    if (isReplayMode || document.getElementById('game-status').innerText !== 'Игра идет') return;
    if (cell.isOpen) return; 

    cell.isFlagged = !cell.isFlagged;

    const div = document.querySelector(`.cell[data-x='${cell.x}'][data-y='${cell.y}']`);
    if (cell.isFlagged) {
        div.classList.add('flag');
        div.innerText = '🚩';
    } else {
        div.classList.remove('flag');
        div.innerText = '';
    }
}

async function handleCellClick(cell, isReplayStep = false) {
    if (cell.isFlagged) return;

    if (cell.isOpen) return;
    if (!isReplayStep && isReplayMode) return;
    if (!isReplayStep && document.getElementById('game-status').innerText !== 'Игра идет') return;

    moveCounter++;
    let result = 'safe';

    if (cell.isMine) {
        result = 'lost';
        openCellVisual(cell, true);
        endGame(false);
    } else {
        openCellVisual(cell);
        if (cell.count === 0) {
            floodFill(cell.x, cell.y);
        }
        const closedNonMines = boardData.filter(c => !c.isMine && !c.isOpen).length;
        if (closedNonMines === 0) {
            result = 'won';
            endGame(true);
        }
    }

    if (!isReplayMode) {
        await apiSendStep(currentGameId, {
            move_number: moveCounter,
            x: cell.x,
            y: cell.y,
            result: result
        });
    }
}

function openCellVisual(cell, exploded = false) {
    cell.isOpen = true;
    const div = document.querySelector(`.cell[data-x='${cell.x}'][data-y='${cell.y}']`);
    div.classList.add('opened');
    div.classList.remove('flag'); 
    
    if (cell.isMine) {
        div.classList.add('mine');
        div.innerText = '💣';
        if (exploded) div.style.backgroundColor = 'orange';
    } else if (cell.count > 0) {
        div.innerText = cell.count;
        const colors = ['blue', 'green', 'red', 'darkblue', 'brown', 'teal', 'black', 'gray'];
        div.style.color = colors[cell.count - 1] || 'black';
    } else {
        div.innerText = '';
    }
}

function floodFill(x, y) {
    for (let dy = -1; dy <= 1; dy++) {
        for (let dx = -1; dx <= 1; dx++) {
            const nx = x + dx;
            const ny = y + dy;
            const neighbor = boardData.find(c => c.x === nx && c.y === ny);
            if (neighbor && !neighbor.isOpen && !neighbor.isMine && !neighbor.isFlagged) {
                openCellVisual(neighbor);
                if (neighbor.count === 0) {
                    floodFill(nx, ny);
                }
            }
        }
    }
}

function endGame(won) {
    const status = document.getElementById('game-status');
    status.innerText = won ? 'Вы выиграли!' : 'Вы проиграли!';
    status.style.color = won ? 'green' : 'red';
    
    if (!won) {
        boardData.filter(c => c.isMine).forEach(c => openCellVisual(c));
    }
}


async function startReplay(gameId) {
    const data = await apiGetGameDetails(gameId);
    const game = data.game;
    replayMoves = data.moves;
    
    isReplayMode = true;
    replayCurrentIndex = 0;
    size = game.width;
    
    initBoard(game.width, game.mine_positions);
    
    showScreen('game-screen');
    document.getElementById('game-status').innerText = `Повтор партии игрока ${game.player_name}`;
    document.getElementById('game-status').style.color = 'black';
    document.getElementById('replay-controls').classList.remove('hidden');
}

function replayNextStep() {
    if (replayCurrentIndex >= replayMoves.length) {
        alert("Повтор завершен");
        return;
    }

    const move = replayMoves[replayCurrentIndex];
    const cell = boardData.find(c => c.x === move.x && c.y === move.y);
    
    if (cell) {
        handleCellClick(cell, true);
    }
    
    replayCurrentIndex++;
}