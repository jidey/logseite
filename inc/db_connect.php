<?php
// Remplacer le contenu par :
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/TestLogRepository.php';
$testLogRepo = new TestLogRepository($pdo);
?>