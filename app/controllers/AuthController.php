<?php
class AuthController {
    public static function login() {
        $error = null;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $error = 'Benutzername und Passwort erforderlich.';
            } else {
                $user = new User();
                $verified = $user->verify($username, $password);
                
                if ($verified) {
                    $_SESSION['adminId'] = $verified['id'];
                    $_SESSION['adminUsername'] = $verified['username'];
                    redirect('?route=admin');
                } else {
                    $error = 'Benutzername oder Passwort falsch.';
                }
            }
        }
        
        render('admin/login', [
            'title' => 'Admin-Login',
            'error' => $error
        ]);
    }
    
    public static function logout() {
        $_SESSION['adminId'] = null;
        $_SESSION['adminUsername'] = null;
        session_destroy();
        redirect('?route=home');
    }
}
?>
