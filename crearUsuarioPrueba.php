<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Crear usuario admin por defecto
$username = 'admin';
$password = password_hash('admin123', PASSWORD_DEFAULT);
$email = 'admin@tiendamm.com';
$nombre = 'Administrador';
$apellido = 'Sistema';
$id_rol = 1;
$dpi = '1234567890123';

try {
    // Verificar si ya existe
    $check_query = "SELECT id_usuario FROM usuarios WHERE username = :username";
    $stmt = $db->prepare($check_query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Insertar nuevo usuario
        $insert_query = "INSERT INTO usuarios (username, password, email, id_rol, nombre, apellido, dpi, activo) 
                         VALUES (:username, :password, :email, :id_rol, :nombre, :apellido, :dpi, 1)";
        
        $stmt = $db->prepare($insert_query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $password);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':id_rol', $id_rol);
        $stmt->bindParam(':nombre', $nombre);
        $stmt->bindParam(':apellido', $apellido);
        $stmt->bindParam(':dpi', $dpi);
        
        if ($stmt->execute()) {
            echo "Usuario admin creado exitosamente.<br>";
            echo "Username: admin<br>";
            echo "Password: admin123<br>";
        } else {
            echo "Error al crear usuario.";
        }
    } else {
        echo "El usuario admin ya existe.";
    }
    
    // Crear trabajadores de prueba
    $trabajadores = [
        ['trabajador1', 'trab1pass', 'trab1@tiendamm.com', 2, 'Juan', 'Pérez', '2345678901234'],
        ['trabajador2', 'trab2pass', 'trab2@tiendamm.com', 3, 'María', 'Gómez', '3456789012345']
    ];
    
    foreach ($trabajadores as $trab) {
        $check_query = "SELECT id_usuario FROM usuarios WHERE username = :username";
        $stmt = $db->prepare($check_query);
        $stmt->bindParam(':username', $trab[0]);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            $password_hash = password_hash($trab[1], PASSWORD_DEFAULT);
            
            $insert_query = "INSERT INTO usuarios (username, password, email, id_rol, nombre, apellido, dpi, activo) 
                             VALUES (:username, :password, :email, :id_rol, :nombre, :apellido, :dpi, 1)";
            
            $stmt = $db->prepare($insert_query);
            $stmt->bindParam(':username', $trab[0]);
            $stmt->bindParam(':password', $password_hash);
            $stmt->bindParam(':email', $trab[2]);
            $stmt->bindParam(':id_rol', $trab[3]);
            $stmt->bindParam(':nombre', $trab[4]);
            $stmt->bindParam(':apellido', $trab[5]);
            $stmt->bindParam(':dpi', $trab[6]);
            $stmt->execute();
            
            echo "Usuario {$trab[0]} creado exitosamente.<br>";
        }
    }
    
    // Insertar datos de configuración inicial
    $departamentos = [
        ['Ropa de Mujer', 'Moda femenina', 'mujer.jpg'],
        ['Ropa de Hombre', 'Moda masculina', 'hombre.jpg'],
        ['Ropa de Niños', 'Ropa infantil', 'ninos.jpg'],
        ['Ropa de Bebé', 'Sección Ajitos Kids', 'bebe.jpg', 1]
    ];
    
    foreach ($departamentos as $depto) {
        $check_query = "SELECT id_departamento FROM departamentos WHERE nombre = :nombre";
        $stmt = $db->prepare($check_query);
        $stmt->bindParam(':nombre', $depto[0]);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            $es_ajitos = isset($depto[3]) ? 1 : 0;
            
            $insert_query = "INSERT INTO departamentos (nombre, descripcion, imagen, es_ajitos, activo) 
                             VALUES (:nombre, :descripcion, :imagen, :es_ajitos, 1)";
            
            $stmt = $db->prepare($insert_query);
            $stmt->bindParam(':nombre', $depto[0]);
            $stmt->bindParam(':descripcion', $depto[1]);
            $stmt->bindParam(':imagen', $depto[2]);
            $stmt->bindParam(':es_ajitos', $es_ajitos);
            $stmt->execute();
        }
    }
    
    echo "<br>Configuración inicial completada.";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>