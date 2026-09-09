<?php

require_once __DIR__ . '/Config.php';

$_SESSION = [];
session_destroy();

header('Location: index.php');
exit;