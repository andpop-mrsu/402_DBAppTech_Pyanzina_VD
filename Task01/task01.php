<?php

$readmeFile = 'README.md';

if (!file_exists($readmeFile)) {
    echo "Файл README.md не найден: $readmeFile\n";
    exit(1);
}

$readmeContent = file_get_contents($readmeFile);
echo $readmeContent;