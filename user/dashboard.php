<?php
declare(strict_types=1);
session_start();

$page = isset($_GET['page']) ? (string) $_GET['page'] : 'dashboard';
header('Location: main.php?page=' . rawurlencode($page));
exit();
