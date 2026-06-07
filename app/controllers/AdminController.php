<?php
class AdminController {
    public static function dashboard() {
        $productModel = new Product();
        $orderModel = new Order();
        $newsletterModel = new Newsletter();
        
        $stats = [
            'products' => count($productModel->getAll()),
            'orders' => count($orderModel->getAll()),
            'subscribers' => $newsletterModel->count()
        ];
        
        render('admin/dashboard', [
            'title' => 'Admin Dashboard',
            'stats' => $stats
        ]);
    }
    
    public static function products() {
        $productModel = new Product();
        $products = $productModel->getAll();
        
        render('admin/products', [
            'title' => 'Produkte',
            'products' => $products
        ]);
    }
    
    public static function addProduct() {
        $productModel = new Product();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'slug' => $_POST['slug'] ?? '',
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'category' => $_POST['category'] ?? 'Allgemein',
                'price_cents' => (int)($_POST['price_cents'] ?? 0),
                'sale_price_cents' => (int)($_POST['sale_price_cents'] ?? 0) ?: null,
                'stock' => (int)($_POST['stock'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            $productModel->create($data);
            redirect('?route=admin/products');
        }
        
        render('admin/product-form', [
            'title' => 'Produkt hinzufügen',
            'product' => null
        ]);
    }
    
    public static function editProduct($id) {
        if (!$id) {
            redirect('?route=admin/products');
        }
        
        $productModel = new Product();
        $product = $productModel->getById($id);
        
        if (!$product) {
            redirect('?route=admin/products');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'category' => $_POST['category'] ?? 'Allgemein',
                'price_cents' => (int)($_POST['price_cents'] ?? 0),
                'sale_price_cents' => (int)($_POST['sale_price_cents'] ?? 0) ?: null,
                'stock' => (int)($_POST['stock'] ?? 0),
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];
            
            $productModel->update($id, $data);
            redirect('?route=admin/products');
        }
        
        render('admin/product-form', [
            'title' => 'Produkt bearbeiten',
            'product' => $product
        ]);
    }
    
    public static function deleteProduct($id) {
        if (!$id) {
            redirect('?route=admin/products');
        }
        
        $productModel = new Product();
        $productModel->delete($id);
        redirect('?route=admin/products');
    }
    
    public static function orders() {
        $orderModel = new Order();
        $orders = $orderModel->getAll();
        
        render('admin/orders', [
            'title' => 'Bestellungen',
            'orders' => $orders
        ]);
    }
    
    public static function orderDetail($id) {
        if (!$id) {
            redirect('?route=admin/orders');
        }
        
        $orderModel = new Order();
        $order = $orderModel->getById($id);
        
        if (!$order) {
            redirect('?route=admin/orders');
        }
        
        $order['items'] = json_decode($order['items'], true);
        
        render('admin/order-detail', [
            'title' => 'Bestellung ' . $order['order_number'],
            'order' => $order
        ]);
    }
    
    public static function messages() {
        $messageModel = new Message();
        $messages = $messageModel->getAll();
        
        render('admin/messages', [
            'title' => 'Nachrichten',
            'messages' => $messages
        ]);
    }
    
    public static function newsletter() {
        $newsletterModel = new Newsletter();
        $subscribers = $newsletterModel->getAll();
        
        render('admin/newsletter', [
            'title' => 'Newsletter',
            'subscribers' => $subscribers
        ]);
    }
    
    public static function settings() {
        $settingModel = new Setting();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settingModel->set('shop_name', $_POST['shop_name'] ?? 'ABJ Shop');
            $settingModel->set('currency', $_POST['currency'] ?? 'CHF');
            $settingModel->set('announcement', $_POST['announcement'] ?? '');
            $settingModel->set('sale_ends_at', $_POST['sale_ends_at'] ?? '');
            redirect('?route=admin/settings');
        }
        
        $settings = $settingModel->all();
        
        render('admin/settings', [
            'title' => 'Einstellungen',
            'settings' => $settings
        ]);
    }
}
?>
