<?php
class ShopController {
    public static function home() {
        $productModel = new Product();
        $products = $productModel->getAll();
        
        render('shop/home', [
            'title' => 'Startseite',
            'products' => array_slice($products, 0, 6)
        ]);
    }
    
    public static function shop() {
        $productModel = new Product();
        $products = $productModel->getAll();
        
        render('shop/shop', [
            'title' => 'Shop',
            'products' => $products
        ]);
    }
    
    public static function product($slug) {
        if (!$slug) {
            redirect('?route=shop');
        }
        
        $productModel = new Product();
        $product = $productModel->getBySlug($slug);
        
        if (!$product) {
            http_response_code(404);
            render('errors/404', ['title' => 'Produkt nicht gefunden']);
            return;
        }
        
        $product['sizes'] = json_decode($product['sizes'], true);
        $product['option_groups'] = json_decode($product['option_groups'], true);
        $product['images'] = json_decode($product['images'], true);
        
        render('shop/product', [
            'title' => $product['name'],
            'product' => $product
        ]);
    }
    
    public static function cart() {
        $cart = $_SESSION['cart'] ?? [];
        $total = 0;
        
        foreach ($cart as $item) {
            $total += $item['price'] * $item['qty'];
        }
        
        render('shop/cart', [
            'title' => 'Warenkorb',
            'cart' => $cart,
            'total' => $total
        ]);
    }
    
    public static function checkout() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $cart = $_SESSION['cart'] ?? [];
            
            if (empty($email) || empty($cart)) {
                die('Fehler: E-Mail oder Warenkorb leer');
            }
            
            $total = 0;
            foreach ($cart as $item) {
                $total += $item['price'] * $item['qty'];
            }
            
            $orderModel = new Order();
            $orderId = $orderModel->create($email, $cart, (int)($total * 100));
            
            $_SESSION['cart'] = [];
            redirect('?route=order-confirmation&id=' . $orderId);
        }
        
        render('shop/checkout', [
            'title' => 'Kasse'
        ]);
    }
    
    public static function contact() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            $subject = $_POST['subject'] ?? '';
            $message = $_POST['message'] ?? '';
            
            if (empty($name) || empty($email) || empty($subject) || empty($message)) {
                die('Alle Felder erforderlich');
            }
            
            $messageModel = new Message();
            $messageModel->create($name, $email, $subject, $message);
            
            redirect('?route=home');
        }
        
        render('shop/contact', [
            'title' => 'Kontakt'
        ]);
    }
    
    public static function addToCart() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }
        
        $productId = $_POST['productId'] ?? null;
        $qty = (int)($_POST['qty'] ?? 1);
        
        if (!$productId || $qty < 1) {
            http_response_code(400);
            return;
        }
        
        $productModel = new Product();
        $product = $productModel->getById($productId);
        
        if (!$product) {
            http_response_code(404);
            return;
        }
        
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Prüfe ob Produkt bereits im Warenkorb
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['productId'] == $productId) {
                $item['qty'] += $qty;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $_SESSION['cart'][] = [
                'productId' => $productId,
                'name' => $product['name'],
                'price' => $product['price_cents'] / 100,
                'qty' => $qty
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'cartCount' => count($_SESSION['cart'])]);
    }
    
    public static function removeFromCart() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }
        
        $productId = $_POST['productId'] ?? null;
        
        if (!$productId) {
            http_response_code(400);
            return;
        }
        
        if (isset($_SESSION['cart'])) {
            $_SESSION['cart'] = array_filter($_SESSION['cart'], function($item) use ($productId) {
                return $item['productId'] != $productId;
            });
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }
}
?>
