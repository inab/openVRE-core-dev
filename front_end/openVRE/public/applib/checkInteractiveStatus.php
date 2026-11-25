<?php

require __DIR__ . "/../../config/bootstrap.php";
redirectOutside();

// Polled by launch-interactive/ to know whether the interactive tool is up
header('Content-Type: application/json');
echo json_encode(checkStatus($_GET['pid'] ?? ""), JSON_UNESCAPED_SLASHES);
