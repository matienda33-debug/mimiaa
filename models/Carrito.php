<?php
// app/models/Carrito.php
class Carrito {
    private $conn;
    private $table_carrito = 'carritos_temporales';
    private $table_variantes = 'variantes_productos';
    private $table_productos = 'productos_raiz';
    private $table_fotos = 'fotos_productos';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Métodos para carrito de compras
    public function agregarAlCarrito($session_id, $id_variante, $cantidad) {
        try {
            // Verificar stock disponible
            $stock = $this->verificarStock($id_variante, $cantidad);
            if (!$stock['disponible']) {
                return [
                    'success' => false,
                    'message' => 'Stock insuficiente. Disponible: ' . $stock['disponible_total']
                ];
            }
            
            // Obtener precio del producto
            $precio = $this->obtenerPrecioVariante($id_variante);
            if (!$precio) {
                return [
                    'success' => false,
                    'message' => 'Producto no disponible'
                ];
            }
            
            // Verificar si ya existe en el carrito
            $query = "SELECT * FROM " . $this->table_carrito . " 
                     WHERE session_id = :session_id AND id_variante = :id_variante";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->bindParam(':id_variante', $id_variante);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Actualizar cantidad
                $query = "UPDATE " . $this->table_carrito . " 
                         SET cantidad = cantidad + :cantidad, 
                         actualizado_en = NOW()
                         WHERE session_id = :session_id AND id_variante = :id_variante";
            } else {
                // Insertar nuevo
                $query = "INSERT INTO " . $this->table_carrito . " 
                         (session_id, id_variante, cantidad, precio_unitario)
                         VALUES (:session_id, :id_variante, :cantidad, :precio_unitario)";
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->bindParam(':id_variante', $id_variante);
            $stmt->bindParam(':cantidad', $cantidad);
            
            if ($stmt->rowCount() == 0) {
                $stmt->bindParam(':precio_unitario', $precio);
            }
            
            $stmt->execute();
            
            return [
                'success' => true,
                'message' => 'Producto agregado al carrito'
            ];
            
        } catch(PDOException $e) {
            error_log("Error agregar al carrito: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al agregar al carrito'
            ];
        }
    }
    
    public function obtenerCarrito($session_id) {
        try {
            $query = "SELECT c.*, v.talla, v.color, v.color_hex, 
                             p.id_producto_raiz, p.nombre as producto_nombre, 
                             p.precio_venta, p.precio_oferta, p.codigo_producto,
                             (SELECT nombre_archivo FROM " . $this->table_fotos . " f 
                              WHERE f.id_producto_raiz = p.id_producto_raiz AND f.es_principal = 1 LIMIT 1) as foto
                     FROM " . $this->table_carrito . " c
                     JOIN " . $this->table_variantes . " v ON c.id_variante = v.id_variante
                     JOIN " . $this->table_productos . " p ON v.id_producto_raiz = p.id_producto_raiz
                     WHERE c.session_id = :session_id
                     ORDER BY c.creado_en DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error obtener carrito: " . $e->getMessage());
            return [];
        }
    }
    
    public function actualizarCantidad($session_id, $id_variante, $cantidad) {
        try {
            if ($cantidad <= 0) {
                return $this->eliminarDelCarrito($session_id, $id_variante);
            }
            
            // Verificar stock
            $stock = $this->verificarStock($id_variante, $cantidad);
            if (!$stock['disponible']) {
                return [
                    'success' => false,
                    'message' => 'Stock insuficiente. Disponible: ' . $stock['disponible_total']
                ];
            }
            
            $query = "UPDATE " . $this->table_carrito . " 
                     SET cantidad = :cantidad, actualizado_en = NOW()
                     WHERE session_id = :session_id AND id_variante = :id_variante";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->bindParam(':id_variante', $id_variante);
            $stmt->bindParam(':cantidad', $cantidad);
            $stmt->execute();
            
            return [
                'success' => true,
                'message' => 'Cantidad actualizada'
            ];
            
        } catch(PDOException $e) {
            error_log("Error actualizar cantidad: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al actualizar cantidad'
            ];
        }
    }
    
    public function eliminarDelCarrito($session_id, $id_variante) {
        try {
            $query = "DELETE FROM " . $this->table_carrito . " 
                     WHERE session_id = :session_id AND id_variante = :id_variante";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->bindParam(':id_variante', $id_variante);
            $stmt->execute();
            
            return [
                'success' => true,
                'message' => 'Producto eliminado del carrito'
            ];
            
        } catch(PDOException $e) {
            error_log("Error eliminar del carrito: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al eliminar del carrito'
            ];
        }
    }
    
    public function vaciarCarrito($session_id) {
        try {
            $query = "DELETE FROM " . $this->table_carrito . " 
                     WHERE session_id = :session_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->execute();
            
            return [
                'success' => true,
                'message' => 'Carrito vaciado'
            ];
            
        } catch(PDOException $e) {
            error_log("Error vaciar carrito: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al vaciar el carrito'
            ];
        }
    }
    
    public function obtenerTotalCarrito($session_id) {
        try {
            $query = "SELECT SUM(c.cantidad * c.precio_unitario) as total,
                             SUM(c.cantidad) as items
                     FROM " . $this->table_carrito . " c
                     WHERE c.session_id = :session_id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':session_id', $session_id);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error obtener total carrito: " . $e->getMessage());
            return ['total' => 0, 'items' => 0];
        }
    }
    
    private function verificarStock($id_variante, $cantidad_solicitada) {
        try {
            $query = "SELECT (stock_tienda + stock_bodega) as stock_total 
                     FROM " . $this->table_variantes . " 
                     WHERE id_variante = :id_variante AND estado = 'disponible'";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_variante', $id_variante);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stock_total = $result['stock_total'] ?? 0;
            
            return [
                'disponible' => $stock_total >= $cantidad_solicitada,
                'disponible_total' => $stock_total
            ];
            
        } catch(PDOException $e) {
            error_log("Error verificar stock: " . $e->getMessage());
            return ['disponible' => false, 'disponible_total' => 0];
        }
    }
    
    private function obtenerPrecioVariante($id_variante) {
        try {
            $query = "SELECT p.precio_oferta as precio_oferta, p.precio_venta
                     FROM " . $this->table_productos . " p
                     JOIN " . $this->table_variantes . " v ON p.id_producto_raiz = v.id_producto_raiz
                     WHERE v.id_variante = :id_variante 
                     AND p.estado = 'activo'
                     AND (p.precio_oferta IS NULL OR CURDATE() BETWEEN p.fecha_inicio_oferta AND p.fecha_fin_oferta)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id_variante', $id_variante);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Usar precio de oferta si está disponible, sino precio regular
            return $result['precio_oferta'] ?? $result['precio_venta'];
            
        } catch(PDOException $e) {
            error_log("Error obtener precio: " . $e->getMessage());
            return null;
        }
    }
    
    // Métodos para procesar venta
    public function procesarVenta($data) {
        try {
            $this->conn->beginTransaction();
            
            // 1. Generar número de factura
            $numero_factura = $this->generarNumeroFactura();
            
            // 2. Crear maestro de factura
            $id_factura = $this->crearMaestroFactura($numero_factura, $data);
            
            // 3. Procesar items del carrito
            $carrito = $this->obtenerCarrito($data['session_id']);
            
            foreach ($carrito as $item) {
                $this->procesarItemVenta($id_factura, $item);
                
                // Actualizar stock
                $this->actualizarStock($item['id_variante'], $item['cantidad']);
            }
            
            // 4. Calcular puntos si hay DPI
            if (!empty($data['dpi_cliente'])) {
                $this->calcularPuntos($id_factura, $data['dpi_cliente'], $data['total']);
            }
            
            // 5. Registrar en contabilidad
            $this->registrarContabilidad($id_factura, $data);
            
            // 6. Vaciar carrito
            $this->vaciarCarrito($data['session_id']);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => 'Venta procesada exitosamente',
                'numero_factura' => $numero_factura,
                'id_factura' => $id_factura
            ];
            
        } catch(PDOException $e) {
            $this->conn->rollBack();
            error_log("Error procesar venta: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al procesar la venta: ' . $e->getMessage()
            ];
        }
    }
    
    private function generarNumeroFactura() {
        $year = date('Y');
        $month = date('m');
        
        // Obtener último número del mes
        $query = "SELECT MAX(CAST(SUBSTRING_INDEX(numero_factura, '-', -1) AS UNSIGNED)) as ultimo_numero
                 FROM maestro_facturas 
                 WHERE numero_factura LIKE 'FACT-$year-$month-%'";
        
        $stmt = $this->conn->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $siguiente_numero = ($result['ultimo_numero'] ?? 0) + 1;
        
        return sprintf('FACT-%s-%s-%06d', $year, $month, $siguiente_numero);
    }
    
    private function crearMaestroFactura($numero_factura, $data) {
        $query = "INSERT INTO maestro_facturas 
                 (numero_factura, numero_orden, tipo_venta, tipo_entrega, id_vendedor,
                  id_cliente, nombre_cliente, dpi_cliente, direccion_entrega, telefono_cliente,
                  referencia_envio, subtotal, descuento, total, puntos_generados, puntos_usados,
                  estado, metodo_pago, fecha_factura, llave_confirmacion, llave_expira)
                 VALUES (:numero_factura, :numero_orden, :tipo_venta, :tipo_entrega, :id_vendedor,
                         :id_cliente, :nombre_cliente, :dpi_cliente, :direccion_entrega, :telefono_cliente,
                         :referencia_envio, :subtotal, :descuento, :total, :puntos_generados, :puntos_usados,
                         :estado, :metodo_pago, NOW(), :llave_confirmacion, :llave_expira)";
        
        $llave_confirmacion = bin2hex(random_bytes(32));
        $llave_expira = date('Y-m-d H:i:s', strtotime('+72 hours'));
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':numero_factura', $numero_factura);
        $stmt->bindParam(':numero_orden', $data['numero_orden']);
        $stmt->bindParam(':tipo_venta', $data['tipo_venta']);
        $stmt->bindParam(':tipo_entrega', $data['tipo_entrega']);
        $stmt->bindParam(':id_vendedor', $data['id_vendedor']);
        $stmt->bindParam(':id_cliente', $data['id_cliente']);
        $stmt->bindParam(':nombre_cliente', $data['nombre_cliente']);
        $stmt->bindParam(':dpi_cliente', $data['dpi_cliente']);
        $stmt->bindParam(':direccion_entrega', $data['direccion_entrega']);
        $stmt->bindParam(':telefono_cliente', $data['telefono_cliente']);
        $stmt->bindParam(':referencia_envio', $data['referencia_envio']);
        $stmt->bindParam(':subtotal', $data['subtotal']);
        $stmt->bindParam(':descuento', $data['descuento']);
        $stmt->bindParam(':total', $data['total']);
        $stmt->bindParam(':puntos_generados', $data['puntos_generados']);
        $stmt->bindParam(':puntos_usados', $data['puntos_usados']);
        $stmt->bindParam(':estado', $data['estado']);
        $stmt->bindParam(':metodo_pago', $data['metodo_pago']);
        $stmt->bindParam(':llave_confirmacion', $llave_confirmacion);
        $stmt->bindParam(':llave_expira', $llave_expira);
        
        $stmt->execute();
        
        return $this->conn->lastInsertId();
    }
    
    private function procesarItemVenta($id_factura, $item) {
        $subtotal = $item['cantidad'] * $item['precio_unitario'];
        
        $query = "INSERT INTO detalle_facturas 
                 (id_factura_maestro, id_variante, cantidad, precio_unitario, 
                  precio_original, subtotal)
                 VALUES (:id_factura, :id_variante, :cantidad, :precio_unitario,
                         :precio_original, :subtotal)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_factura', $id_factura);
        $stmt->bindParam(':id_variante', $item['id_variante']);
        $stmt->bindParam(':cantidad', $item['cantidad']);
        $stmt->bindParam(':precio_unitario', $item['precio_unitario']);
        $stmt->bindParam(':precio_original', $item['precio_venta']);
        $stmt->bindParam(':subtotal', $subtotal);
        
        $stmt->execute();
    }
    
    private function actualizarStock($id_variante, $cantidad) {
        // Primero descontar de tienda, luego de bodega
        $query = "UPDATE variantes_productos 
                 SET stock_tienda = GREATEST(0, stock_tienda - :cantidad_tienda),
                     stock_bodega = GREATEST(0, stock_bodega - :cantidad_bodega)
                 WHERE id_variante = :id_variante";
        
        // Obtener stock actual
        $stmt = $this->conn->prepare("SELECT stock_tienda, stock_bodega FROM variantes_productos WHERE id_variante = :id_variante");
        $stmt->bindParam(':id_variante', $id_variante);
        $stmt->execute();
        $stock = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $cantidad_tienda = min($cantidad, $stock['stock_tienda']);
        $cantidad_bodega = $cantidad - $cantidad_tienda;
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id_variante', $id_variante);
        $stmt->bindParam(':cantidad_tienda', $cantidad_tienda);
        $stmt->bindParam(':cantidad_bodega', $cantidad_bodega);
        $stmt->execute();
    }
    
    private function calcularPuntos($id_factura, $dpi, $total) {
        // Obtener configuración de puntos
        $query = "SELECT valor FROM configuracion WHERE clave = 'puntos_por_quetzal'";
        $stmt = $this->conn->query($query);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $puntos_por_quetzal = $config['valor'] ?? 20;
        $puntos_generados = floor($total / $puntos_por_quetzal);
        
        if ($puntos_generados > 0) {
            // Actualizar maestro factura
            $query = "UPDATE maestro_facturas SET puntos_generados = :puntos WHERE id_factura_maestro = :id_factura";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':puntos', $puntos_generados);
            $stmt->bindParam(':id_factura', $id_factura);
            $stmt->execute();
            
            // Buscar o crear registro de puntos
            $query = "SELECT p.id_persona, pc.id_puntos 
                     FROM personas p
                     LEFT JOIN puntos_clientes pc ON p.id_persona = pc.id_persona
                     WHERE p.dpi = :dpi";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':dpi', $dpi);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                if ($result['id_puntos']) {
                    // Actualizar puntos existentes
                    $query = "UPDATE puntos_clientes 
                             SET puntos_acumulados = puntos_acumulados + :puntos,
                                 puntos_disponibles = puntos_disponibles + :puntos,
                                 total_gastado = total_gastado + :total,
                                 ultima_actualizacion = NOW()
                             WHERE id_puntos = :id_puntos";
                } else {
                    // Crear nuevo registro de puntos
                    $query = "INSERT INTO puntos_clientes 
                             (id_persona, puntos_acumulados, puntos_disponibles, total_gastado)
                             VALUES (:id_persona, :puntos, :puntos, :total)";
                }
                
                $stmt = $this->conn->prepare($query);
                if ($result['id_puntos']) {
                    $stmt->bindParam(':id_puntos', $result['id_puntos']);
                } else {
                    $stmt->bindParam(':id_persona', $result['id_persona']);
                }
                $stmt->bindParam(':puntos', $puntos_generados);
                $stmt->bindParam(':total', $total);
                $stmt->execute();
            }
        }
    }
    
    private function registrarContabilidad($id_factura, $data) {
        $query = "INSERT INTO contabilidad 
                 (tipo, categoria, descripcion, monto, id_factura, id_usuario, 
                  fecha_registro, fecha_contable, estado)
                 VALUES ('ingreso', 'venta', 'Venta #' . :numero_factura, :monto, 
                         :id_factura, :id_usuario, CURDATE(), CURDATE(), 'confirmado')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':numero_factura', $data['numero_factura']);
        $stmt->bindParam(':monto', $data['total']);
        $stmt->bindParam(':id_factura', $id_factura);
        $stmt->bindParam(':id_usuario', $data['id_vendedor']);
        
        $stmt->execute();
    }
}
?>