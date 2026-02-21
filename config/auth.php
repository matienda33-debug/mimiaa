<?php
class Auth {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function login($username, $password) {
        $query = "SELECT u.*, r.nombre as rol_nombre, r.permisos 
                  FROM usuarios u 
                  INNER JOIN roles r ON u.id_rol = r.id_rol 
                  WHERE u.username = :username AND u.activo = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id_usuario'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['rol'] = $row['id_rol'];
                $_SESSION['rol_nombre'] = $row['rol_nombre'];
                $_SESSION['permisos'] = json_decode($row['permisos'], true);
                $_SESSION['nombre'] = $row['nombre'] . ' ' . $row['apellido'];
                
                // Actualizar último login
                $this->updateLastLogin($row['id_usuario']);
                
                return true;
            }
        }
        return false;
    }
    
    private function updateLastLogin($user_id) {
        $query = "UPDATE usuarios SET ultimo_login = NOW() WHERE id_usuario = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $user_id);
        $stmt->execute();
    }
    
    public function logout() {
        session_destroy();
        header('Location: ../index.php');
        exit();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function hasPermission($permission) {
        if (!isset($_SESSION['permisos'])) {
            return false;
        }
        return in_array($permission, $_SESSION['permisos']);
    }
    
    public function requirePermission($permission) {
        if (!$this->isLoggedIn() || !$this->hasPermission($permission)) {
            header('Location: ../index.php?error=no_permission');
            exit();
        }
    }
    
    public function isAdmin() {
        return isset($_SESSION['rol']) && $_SESSION['rol'] == 1;
    }
}
?>