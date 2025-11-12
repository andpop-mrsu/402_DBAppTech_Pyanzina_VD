// js/db.js
const DB_NAME = 'MinesweeperDB';
const DB_VERSION = 1;
const GAMES_STORE = 'games';
const MOVES_STORE = 'moves';

let db;

// Функция для инициализации БД
export function initDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            db = event.target.result;
            // Хранилище для игр
            if (!db.objectStoreNames.contains(GAMES_STORE)) {
                const gamesStore = db.createObjectStore(GAMES_STORE, { keyPath: 'id', autoIncrement: true });
                gamesStore.createIndex('date', 'date', { unique: false });
            }
            // Хранилище для ходов
            if (!db.objectStoreNames.contains(MOVES_STORE)) {
                const movesStore = db.createObjectStore(MOVES_STORE, { keyPath: 'id', autoIncrement: true });
                movesStore.createIndex('gameId', 'gameId', { unique: false });
            }
        };

        request.onsuccess = (event) => {
            db = event.target.result;
            resolve(db);
        };

        request.onerror = (event) => {
            console.error("Database error: ", event.target.errorCode);
            reject(event.target.errorCode);
        };
    });
}

// Сохранить новую игру и вернуть ее ID
export function saveGame(gameData) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([GAMES_STORE], 'readwrite');
        const store = transaction.objectStore(GAMES_STORE);
        const request = store.add(gameData);

        request.onsuccess = (event) => {
            resolve(event.target.result); // Возвращает ID новой записи
        };

        request.onerror = (event) => {
            reject("Error saving game: " + event.target.error);
        };
    });
}

// Обновить исход игры
export function updateGameOutcome(gameId, outcome) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([GAMES_STORE], 'readwrite');
        const store = transaction.objectStore(GAMES_STORE);
        const request = store.get(gameId);

        request.onsuccess = () => {
            const game = request.result;
            game.outcome = outcome;
            const updateRequest = store.put(game);
            updateRequest.onsuccess = resolve;
            updateRequest.onerror = reject;
        };
        request.onerror = reject;
    });
}

// Сохранить ход
export function saveMove(moveData) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([MOVES_STORE], 'readwrite');
        const store = transaction.objectStore(MOVES_STORE);
        const request = store.add(moveData);
        request.onsuccess = resolve;
        request.onerror = reject;
    });
}

// Получить все игры
export function getAllGames() {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction([GAMES_STORE], 'readonly');
        const store = transaction.objectStore(GAMES_STORE);
        const request = store.getAll();

        request.onsuccess = () => {
            resolve(request.result);
        };
        request.onerror = reject;
    });
}

// Получить игру и все ее ходы для повтора
export function getGameForReplay(gameId) {
    return new Promise(async (resolve, reject) => {
        const transaction = db.transaction([GAMES_STORE, MOVES_STORE], 'readonly');
        
        const gamesStore = transaction.objectStore(GAMES_STORE);
        const gameRequest = gamesStore.get(gameId);
        
        const movesStore = transaction.objectStore(MOVES_STORE);
        const movesIndex = movesStore.index('gameId');
        const movesRequest = movesIndex.getAll(gameId);

        let game, moves;

        gameRequest.onsuccess = () => game = gameRequest.result;
        movesRequest.onsuccess = () => moves = movesRequest.result;

        transaction.oncomplete = () => {
            if (game && moves) {
                // Сортируем ходы по номеру
                moves.sort((a, b) => a.moveNumber - b.moveNumber);
                resolve({ game, moves });
            } else {
                reject("Game or moves not found");
            }
        };

        transaction.onerror = reject;
    });
}