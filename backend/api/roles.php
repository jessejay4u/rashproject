<?php
require_once __DIR__ . '/bootstrap.php';
auth();
$rows = db()->query("SELECT id, name, display_name FROM roles ORDER BY name")->fetchAll();
ok($rows);
