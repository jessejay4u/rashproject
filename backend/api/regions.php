<?php
require_once __DIR__ . '/bootstrap.php';
auth();
$rows = db()->query("SELECT id, name, code FROM regions WHERE is_active = 1 ORDER BY name")->fetchAll();
ok($rows);
