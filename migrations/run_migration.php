<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Verificar si el campo existe
    $query = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_NAME = 'factura_cabecera' 
              AND COLUMN_NAME = 'correo_envio' 
              AND TABLE_SCHEMA = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([DB_NAME]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        // El campo no existe, agregarlo
        echo "Campo 'correo_envio' no encontrado. Agregando...\n";
        
        // Agregar campo
        $alter_query = "ALTER TABLE `factura_cabecera` 
                       ADD COLUMN `correo_envio` VARCHAR(255) DEFAULT NULL 
                       AFTER `correo_enviado`";
        $db->exec($alter_query);
        echo "✓ Campo 'correo_envio' agregado exitosamente\n";
        
        // Crear índice
        $index_query = "ALTER TABLE `factura_cabecera` 
                       ADD INDEX `IDX_correo_envio` (`correo_envio`)";
        $db->exec($index_query);
        echo "✓ Índice 'IDX_correo_envio' creado exitosamente\n";
        
    } else {
        echo "✓ Campo 'correo_envio' ya existe en la tabla.\n";
    }
    
    echo "\nMigración completada.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
