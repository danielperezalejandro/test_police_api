<?php
require_once __DIR__ . '/src/config/database.php';

$db = new Database();
$conn = $db->connect();
