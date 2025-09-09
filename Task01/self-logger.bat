@echo off
chcp 65001 > nul

set DB_FILE=self_logger.db
set TABLE_NAME=launches
set USERNAME=%USERNAME%
set CURRENT_DATE_TIME=%Date% %TIME%

:CreateDB
if not exist %DB_FILE% (
    sqlite3 %DB_FILE% "CREATE TABLE %TABLE_NAME% (id INTEGER PRIMARY KEY AUTOINCREMENT, user TEXT, launch_time TEXT);"
)

sqlite3 %DB_FILE% "INSERT INTO %TABLE_NAME% (user, launch_time) VALUES ('%USERNAME%', '%CURRENT_DATE_TIME%');"

echo.
echo Имя программы: self-logger.bat
echo Количество запусков: 
sqlite3 %DB_FILE% "SELECT COUNT(*) FROM %TABLE_NAME%;"
echo Первый запуск: 
sqlite3 %DB_FILE% "SELECT launch_time FROM %TABLE_NAME% ORDER BY launch_time LIMIT 1;"

echo ---------------------------------------------
echo User      ^| Date
echo --------------------------------------------
sqlite3 %DB_FILE% "SELECT user, launch_time FROM %TABLE_NAME%;"
echo --------------------------------------------

endlocal