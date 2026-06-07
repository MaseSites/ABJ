<?php
// Hauptrouter
require_once 'config.php';
require_once 'Database.php';

// Models laden
require_once 'models/User.php';
require_once 'models/Setting.php';
require_once 'models/Product.php';
require_once 'models/Order.php';
require_once 'models/Newsletter.php';
require_once 'models/Message.php';

// Controller laden
require_once 'controllers/AuthController.php';
require_once 'controllers/AdminController.php';
require_once 'controllers/ShopController.php';

// Routing
$request = $_GET['route'] ?? 'home';
$method = $_SERVER['REQUEST_METHOD'];

// Admin-Schutz: Prüfe ob Admin angemeldet sein muss
$isAdmin = (isset($_SESSION['adminId']) && !empty($_SESSION['adminId']));

// Route-Handler
if (strpos($request, 'admin/') === 0) {
    if (!$isAdmin && $request !== 'admin/login') {
        redirect('?route=admin/login');
    }
}

switch ($request) {
    // Auth
    case 'admin/login':
        AuthController::login();
        break;
    case 'admin/logout':
        AuthController::logout();
        break;
    
    // Admin
    case 'admin':
    case 'admin/dashboard':
        AdminController::dashboard();
        break;
    case 'admin/products':
        AdminController::products();
        break;
    case 'admin/product/add':
        AdminController::addProduct();
        break;
    case 'admin/product/edit':
        AdminController::editProduct($_GET['id'] ?? null);
        break;
    case 'admin/product/delete':
        AdminController::deleteProduct($_GET['id'] ?? null);
        break;
    case 'admin/orders':
        AdminController::orders();
        break;
    case 'admin/order/detail':
        AdminController::orderDetail($_GET['id'] ?? null);
        break;
    case 'admin/messages':
        AdminController::messages();
        break;
    case 'admin/newsletter':
        AdminController::newsletter();
        break;
    case 'admin/settings':
        AdminController::settings();
        break;
    
    // Shop
    case 'home':
        ShopController::home();
        break;
    case 'shop':
        ShopController::shop();
        break;
    case 'product':
        ShopController::product($_GET['slug'] ?? null);
        break;
    case 'cart':
        ShopController::cart();
        break;
    case 'checkout':
        ShopController::checkout();
        break;
    case 'order-confirmation':
        render('shop/order-confirmation', ['title' => 'Bestellung abgeschlossen']);
        break;
    case 'contact':
        ShopController::contact();
        break;
    case 'api/cart/add':
        ShopController::addToCart();
        break;
    case 'api/cart/remove':
        ShopController::removeFromCart();
        break;
    
    // 404
    default:
        http_response_code(404);
        render('errors/404', ['title' => 'Nicht gefunden']);
        break;
}
?>
