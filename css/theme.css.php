<?php
require_once __DIR__ . '/../lib/bootstrap.php';
header('Content-Type: text/css');
header('Cache-Control: public, max-age=300');

$accent  = setting_get('accent')  ?: '#B89C67';
$accent2 = setting_get('accent_2') ?: '#B89C67';
$accent3 = setting_get('accent_3') ?: '#CDB27E';
echo ":root{--accent:$accent;--accent-2:$accent2;--accent-3:$accent3;}";
