<?php
require_once __DIR__ . '/../lib/bootstrap.php';
admin_logout();
redirect('/admin/login');
