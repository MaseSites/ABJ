<?php
// Fallback-Einstieg ins Admin-Dashboard: funktioniert auf jedem Server,
// auch ganz ohne URL-Rewrites (einfach /admin.php aufrufen).
require_once __DIR__ . '/lib/bootstrap.php';
redirect('/admin/index.php');
