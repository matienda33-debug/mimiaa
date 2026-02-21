<?php
// app/models/Producto.php

class Producto {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    // --------------------------------------------------------
    // CRUD Productos Raíz
    // --------------------------------------------------------
    
    /**
     * Crear nuevo producto raíz
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();
            
            // Verificar si código ya existe
            if ($this->exists('codigo_producto', $data['codigo_producto'])) {
                throw new Exception('El código de producto ya existe');
            }
            
            // Preparar datos
            $sqlData = [
                'codigo_producto' => $data['codigo_producto'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'id_departamento' => $data['id_departamento'],
                'id_tipo' => $data['id_tipo'],
                'id_marca' => $data['id_marca'] ?? null,
                'precio_compra' => $data['precio_compra'],
                'precio_venta' => $data['precio_venta'],
                'precio_oferta' => $data['precio_oferta'] ?? null,
                'etiqueta' => $data['etiqueta'] ?? 'nuevo',
                'costo_fabricacion' => $data['costo_fabricacion'] ?? null,
                'fecha_inicio_oferta' => $data['fecha_inicio_oferta'] ?? null,
                'fecha_fin_oferta' => $data['fecha_fin_oferta'] ?? null,
                'es_kids' => $data['es_kids'] ?? 0,
                'estado' => $data['estado'] ?? 'activo'
            ];
            
            // Insertar producto
            $sql = "INSERT INTO productos_raiz 
                   (codigo_producto, nombre, descripcion, id_departamento, id_tipo, id_marca,
                    precio_compra, precio_venta, precio_oferta, etiqueta, costo_fabricacion,
                    fecha_inicio_oferta, fecha_fin_oferta, es_kids, estado) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $productId = $this->db->insert($sql, array_values($sqlData));
            
            // Subir fotos si existen
            if (!empty($data['fotos'])) {
                $this->uploadProductPhotos($productId, $data['fotos']);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Producto creado exitosamente',
                'id_producto' => $productId
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error creando producto: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al crear el producto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener producto por ID
     */
    public function getById($id) {
        try {
            $sql = "SELECT p.*, 
                           d.nombre as departamento_nombre,
                           t.nombre as tipo_nombre,
                           m.nombre as marca_nombre
                   FROM productos_raiz p
                   LEFT JOIN departamentos d ON p.id_departamento = d.id_departamento
                   LEFT JOIN tipos_ropa t ON p.id_tipo = t.id_tipo
                   LEFT JOIN marcas m ON p.id_marca = m.id_marca
                   WHERE p.id_producto_raiz = ?";
            
            $product = $this->db->getSingle($sql, [$id]);
            
            if ($product) {
                $product['fotos'] = $this->getProductPhotos($id);
                $product['variantes'] = $this->getProductVariants($id);
                $product['stock_total'] = $this->getProductTotalStock($id);
            }
            
            return $product;
            
        } catch (Exception $e) {
            error_log("Error obteniendo producto: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener todos los productos con filtros
     */
    public function getAll($filters = [], $page = 1, $perPage = 20) {
        try {
            $where = [];
            $params = [];
            $offset = ($page - 1) * $perPage;
            
            // Aplicar filtros
            if (!empty($filters['id_departamento'])) {
                $where[] = "p.id_departamento = ?";
                $params[] = $filters['id_departamento'];
            }
            
            if (!empty($filters['id_tipo'])) {
                $where[] = "p.id_tipo = ?";
                $params[] = $filters['id_tipo'];
            }
            
            if (!empty($filters['id_marca'])) {
                $where[] = "p.id_marca = ?";
                $params[] = $filters['id_marca'];
            }
            
            if (!empty($filters['etiqueta'])) {
                $where[] = "p.etiqueta = ?";
                $params[] = $filters['etiqueta'];
            }
            
            if (!empty($filters['estado'])) {
                $where[] = "p.estado = ?";
                $params[] = $filters['estado'];
            }
            
            if (isset($filters['es_kids'])) {
                $where[] = "p.es_kids = ?";
                $params[] = $filters['es_kids'];
            }
            
            if (!empty($filters['busqueda'])) {
                $where[] = "(p.nombre LIKE ? OR p.descripcion LIKE ? OR p.codigo_producto LIKE ?)";
                $searchTerm = '%' . $filters['busqueda'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            if (!empty($filters['precio_min'])) {
                $where[] = "p.precio_venta >= ?";
                $params[] = $filters['precio_min'];
            }
            
            if (!empty($filters['precio_max'])) {
                $where[] = "p.precio_venta <= ?";
                $params[] = $filters['precio_max'];
            }
            
            if (!empty($filters['en_oferta'])) {
                $where[] = "p.precio_oferta IS NOT NULL 
                           AND (p.fecha_inicio_oferta IS NULL OR p.fecha_inicio_oferta <= CURDATE())
                           AND (p.fecha_fin_oferta IS NULL OR p.fecha_fin_oferta >= CURDATE())";
            }
            
            // Construir consulta
            $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
            
            // Consulta principal
            $sql = "SELECT p.*, 
                           d.nombre as departamento_nombre,
                           t.nombre as tipo_nombre,
                           m.nombre as marca_nombre,
                           (SELECT nombre_archivo FROM fotos_productos fp 
                            WHERE fp.id_producto_raiz = p.id_producto_raiz 
                            AND fp.es_principal = 1 LIMIT 1) as foto_principal,
                           (SELECT SUM(stock_tienda + stock_bodega) FROM variantes_productos vp 
                            WHERE vp.id_producto_raiz = p.id_producto_raiz) as stock_total
                   FROM productos_raiz p
                   LEFT JOIN departamentos d ON p.id_departamento = d.id_departamento
                   LEFT JOIN tipos_ropa t ON p.id_tipo = t.id_tipo
                   LEFT JOIN marcas m ON p.id_marca = m.id_marca
                   WHERE $whereClause
                   ORDER BY p.creado_en DESC
                   LIMIT ?, ?";
            
            $params[] = $offset;
            $params[] = $perPage;
            
            $products = $this->db->getAll($sql, $params);
            
            // Contar total para paginación
            $countSql = "SELECT COUNT(*) as total 
                        FROM productos_raiz p
                        WHERE $whereClause";
            
            $countResult = $this->db->getSingle($countSql, array_slice($params, 0, -2));
            $total = $countResult['total'];
            
            return [
                'products' => $products,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
            
        } catch (Exception $e) {
            error_log("Error obteniendo productos: " . $e->getMessage());
            return [
                'products' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0
            ];
        }
    }
    
    /**
     * Actualizar producto
     */
    public function update($id, $data) {
        try {
            $this->db->beginTransaction();
            
            // Verificar si producto existe
            if (!$this->exists('id_producto_raiz', $id)) {
                throw new Exception('Producto no encontrado');
            }
            
            // Preparar campos a actualizar
            $fields = [];
            $params = [];
            
            $allowedFields = [
                'nombre', 'descripcion', 'id_departamento', 'id_tipo', 'id_marca',
                'precio_compra', 'precio_venta', 'precio_oferta', 'etiqueta',
                'costo_fabricacion', 'fecha_inicio_oferta', 'fecha_fin_oferta',
                'es_kids', 'estado'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                throw new Exception('No hay datos para actualizar');
            }
            
            // Agregar ID al final
            $params[] = $id;
            
            $sql = "UPDATE productos_raiz 
                   SET " . implode(', ', $fields) . ", 
                   actualizado_en = NOW()
                   WHERE id_producto_raiz = ?";
            
            $affected = $this->db->update($sql, $params);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
                'affected' => $affected
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error actualizando producto: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al actualizar el producto: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Eliminar producto (cambiar estado a inactivo)
     */
    public function delete($id) {
        try {
            $sql = "UPDATE productos_raiz SET estado = 'inactivo' WHERE id_producto_raiz = ?";
            $affected = $this->db->update($sql, [$id]);
            
            return [
                'success' => true,
                'message' => 'Producto desactivado exitosamente',
                'affected' => $affected
            ];
            
        } catch (Exception $e) {
            error_log("Error eliminando producto: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al desactivar el producto'
            ];
        }
    }
    
    /**
     * Verificar si existe un valor único
     */
    private function exists($field, $value) {
        $sql = "SELECT COUNT(*) as count FROM productos_raiz WHERE $field = ?";
        $result = $this->db->getSingle($sql, [$value]);
        return $result['count'] > 0;
    }
    
    // --------------------------------------------------------
    // Fotos de Productos
    // --------------------------------------------------------
    
    /**
     * Subir fotos de producto
     */
    private function uploadProductPhotos($productId, $files) {
        require_once __DIR__ . '/../../includes/funciones.php';
        
        foreach ($files['tmp_name'] as $index => $tmpName) {
            if ($files['error'][$index] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $files['name'][$index],
                    'type' => $files['type'][$index],
                    'tmp_name' => $tmpName,
                    'size' => $files['size'][$index]
                ];
                
                try {
                    $fileName = uploadProductImage($file, $productId);
                    
                    // Insertar en base de datos
                    $esPrincipal = ($index === 0); // Primera foto es principal
                    $this->addProductPhoto($productId, $fileName, $esPrincipal);
                    
                } catch (Exception $e) {
                    error_log("Error subiendo foto: " . $e->getMessage());
                    continue;
                }
            }
        }
    }
    
    /**
     * Agregar foto a producto
     */
    public function addProductPhoto($productId, $fileName, $esPrincipal = false) {
        try {
            // Si es principal, desmarcar otras como principales
            if ($esPrincipal) {
                $sql = "UPDATE fotos_productos SET es_principal = 0 WHERE id_producto_raiz = ?";
                $this->db->update($sql, [$productId]);
            }
            
            // Insertar nueva foto
            $sql = "INSERT INTO fotos_productos (id_producto_raiz, nombre_archivo, es_principal) 
                   VALUES (?, ?, ?)";
            
            $this->db->insert($sql, [$productId, $fileName, $esPrincipal ? 1 : 0]);
            
            return ['success' => true, 'message' => 'Foto agregada exitosamente'];
            
        } catch (Exception $e) {
            error_log("Error agregando foto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al agregar la foto'];
        }
    }
    
    /**
     * Obtener fotos de producto
     */
    public function getProductPhotos($productId) {
        try {
            $sql = "SELECT * FROM fotos_productos 
                   WHERE id_producto_raiz = ? 
                   ORDER BY es_principal DESC, orden ASC";
            
            return $this->db->getAll($sql, [$productId]);
            
        } catch (Exception $e) {
            error_log("Error obteniendo fotos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Eliminar foto de producto
     */
    public function deleteProductPhoto($photoId) {
        try {
            // Obtener nombre del archivo
            $sql = "SELECT nombre_archivo FROM fotos_productos WHERE id_foto = ?";
            $photo = $this->db->getSingle($sql, [$photoId]);
            
            if (!$photo) {
                throw new Exception('Foto no encontrada');
            }
            
            // Eliminar físicamente
            require_once __DIR__ . '/../../includes/funciones.php';
            deleteProductImage($photo['nombre_archivo']);
            
            // Eliminar de base de datos
            $sql = "DELETE FROM fotos_productos WHERE id_foto = ?";
            $this->db->delete($sql, [$photoId]);
            
            return ['success' => true, 'message' => 'Foto eliminada exitosamente'];
            
        } catch (Exception $e) {
            error_log("Error eliminando foto: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al eliminar la foto'];
        }
    }
    
    /**
     * Establecer foto como principal
     */
    public function setMainPhoto($productId, $photoId) {
        try {
            $this->db->beginTransaction();
            
            // Desmarcar todas como principales
            $sql = "UPDATE fotos_productos SET es_principal = 0 WHERE id_producto_raiz = ?";
            $this->db->update($sql, [$productId]);
            
            // Marcar la seleccionada como principal
            $sql = "UPDATE fotos_productos SET es_principal = 1 WHERE id_foto = ?";
            $this->db->update($sql, [$photoId]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Foto principal actualizada'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error estableciendo foto principal: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al actualizar foto principal'];
        }
    }
    
    // --------------------------------------------------------
    // Variantes de Productos
    // --------------------------------------------------------
    
    /**
     * Crear variante de producto
     */
    public function createVariant($productId, $data) {
        try {
            // Verificar si combinación ya existe
            $sql = "SELECT COUNT(*) as count 
                   FROM variantes_productos 
                   WHERE id_producto_raiz = ? AND talla = ? AND color = ?";
            
            $exists = $this->db->getSingle($sql, [$productId, $data['talla'], $data['color']]);
            
            if ($exists['count'] > 0) {
                throw new Exception('Ya existe una variante con esta talla y color');
            }
            
            // Insertar variante
            $sql = "INSERT INTO variantes_productos 
                   (id_producto_raiz, talla, color, color_hex, stock_tienda, stock_bodega,
                    stock_minimo, ubicacion_tienda, ubicacion_bodega, estado) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $variantId = $this->db->insert($sql, [
                $productId,
                $data['talla'],
                $data['color'],
                $data['color_hex'] ?? null,
                $data['stock_tienda'] ?? 0,
                $data['stock_bodega'] ?? 0,
                $data['stock_minimo'] ?? 5,
                $data['ubicacion_tienda'] ?? null,
                $data['ubicacion_bodega'] ?? null,
                $data['estado'] ?? 'disponible'
            ]);
            
            // Registrar movimiento de inventario
            $this->logInventoryMovement(
                $variantId,
                'entrada',
                ($data['stock_tienda'] ?? 0) + ($data['stock_bodega'] ?? 0),
                0,
                ($data['stock_tienda'] ?? 0) + ($data['stock_bodega'] ?? 0),
                'Creación de variante'
            );
            
            return [
                'success' => true,
                'message' => 'Variante creada exitosamente',
                'id_variante' => $variantId
            ];
            
        } catch (Exception $e) {
            error_log("Error creando variante: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al crear la variante: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener variantes de producto
     */
    public function getProductVariants($productId) {
        try {
            $sql = "SELECT v.*, 
                           (v.stock_tienda + v.stock_bodega) as stock_total,
                           CASE 
                               WHEN (v.stock_tienda + v.stock_bodega) = 0 THEN 'agotado'
                               WHEN (v.stock_tienda + v.stock_bodega) <= v.stock_minimo THEN 'bajo'
                               ELSE 'normal'
                           END as estado_stock
                   FROM variantes_productos v
                   WHERE v.id_producto_raiz = ?
                   ORDER BY v.talla, v.color";
            
            return $this->db->getAll($sql, [$productId]);
            
        } catch (Exception $e) {
            error_log("Error obteniendo variantes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener variante por ID
     */
    public function getVariantById($variantId) {
        try {
            $sql = "SELECT v.*, p.nombre as producto_nombre, p.codigo_producto
                   FROM variantes_productos v
                   INNER JOIN productos_raiz p ON v.id_producto_raiz = p.id_producto_raiz
                   WHERE v.id_variante = ?";
            
            return $this->db->getSingle($sql, [$variantId]);
            
        } catch (Exception $e) {
            error_log("Error obteniendo variante: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Actualizar variante
     */
    public function updateVariant($variantId, $data) {
        try {
            $this->db->beginTransaction();
            
            // Obtener datos actuales
            $current = $this->getVariantById($variantId);
            if (!$current) {
                throw new Exception('Variante no encontrada');
            }
            
            // Preparar campos a actualizar
            $fields = [];
            $params = [];
            
            $allowedFields = [
                'talla', 'color', 'color_hex', 'stock_tienda', 'stock_bodega',
                'stock_minimo', 'ubicacion_tienda', 'ubicacion_bodega', 'estado'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                throw new Exception('No hay datos para actualizar');
            }
            
            // Agregar ID al final
            $params[] = $variantId;
            
            $sql = "UPDATE variantes_productos 
                   SET " . implode(', ', $fields) . ", 
                   actualizado_en = NOW()
                   WHERE id_variante = ?";
            
            $affected = $this->db->update($sql, $params);
            
            // Registrar movimiento de inventario si cambió el stock
            if (isset($data['stock_tienda']) || isset($data['stock_bodega'])) {
                $oldTotal = $current['stock_tienda'] + $current['stock_bodega'];
                $newTotal = ($data['stock_tienda'] ?? $current['stock_tienda']) + 
                           ($data['stock_bodega'] ?? $current['stock_bodega']);
                
                if ($oldTotal != $newTotal) {
                    $tipo = $newTotal > $oldTotal ? 'entrada' : 'salida';
                    $this->logInventoryMovement(
                        $variantId,
                        $tipo,
                        abs($newTotal - $oldTotal),
                        $oldTotal,
                        $newTotal,
                        'Actualización manual de stock'
                    );
                }
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Variante actualizada exitosamente',
                'affected' => $affected
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error actualizando variante: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al actualizar la variante: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Eliminar variante
     */
    public function deleteVariant($variantId) {
        try {
            $sql = "DELETE FROM variantes_productos WHERE id_variante = ?";
            $affected = $this->db->delete($sql, [$variantId]);
            
            return [
                'success' => true,
                'message' => 'Variante eliminada exitosamente',
                'affected' => $affected
            ];
            
        } catch (Exception $e) {
            error_log("Error eliminando variante: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al eliminar la variante'
            ];
        }
    }
    
    /**
     * Ajustar stock de variante
     */
    public function adjustStock($variantId, $tipo, $cantidad, $motivo, $referencia = null) {
        try {
            $this->db->beginTransaction();
            
            // Obtener datos actuales
            $current = $this->getVariantById($variantId);
            if (!$current) {
                throw new Exception('Variante no encontrada');
            }
            
            // Calcular nuevos valores
            if ($tipo === 'entrada') {
                $newTienda = $current['stock_tienda'] + $cantidad;
                $newBodega = $current['stock_bodega'];
            } else if ($tipo === 'salida') {
                // Primero de tienda, luego de bodega
                $salidaTienda = min($cantidad, $current['stock_tienda']);
                $salidaBodega = $cantidad - $salidaTienda;
                
                $newTienda = $current['stock_tienda'] - $salidaTienda;
                $newBodega = $current['stock_bodega'] - $salidaBodega;
                
                if ($newBodega < 0) {
                    throw new Exception('Stock insuficiente');
                }
            } else {
                throw new Exception('Tipo de movimiento inválido');
            }
            
            // Actualizar stock
            $sql = "UPDATE variantes_productos 
                   SET stock_tienda = ?, stock_bodega = ?, actualizado_en = NOW()
                   WHERE id_variante = ?";
            
            $this->db->update($sql, [$newTienda, $newBodega, $variantId]);
            
            // Registrar movimiento
            $oldTotal = $current['stock_tienda'] + $current['stock_bodega'];
            $newTotal = $newTienda + $newBodega;
            
            $this->logInventoryMovement(
                $variantId,
                $tipo,
                $cantidad,
                $oldTotal,
                $newTotal,
                $motivo,
                $referencia
            );
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Stock ajustado exitosamente',
                'stock_tienda' => $newTienda,
                'stock_bodega' => $newBodega,
                'stock_total' => $newTotal
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error ajustando stock: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al ajustar el stock: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Registrar movimiento de inventario
     */
    private function logInventoryMovement($variantId, $tipo, $cantidad, $anterior, $nuevo, $motivo, $referencia = null) {
        try {
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            $sql = "INSERT INTO historico_inventario 
                   (id_variante, tipo_movimiento, cantidad, cantidad_anterior, 
                    cantidad_nueva, motivo, referencia, id_usuario) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $this->db->insert($sql, [
                $variantId,
                $tipo,
                $cantidad,
                $anterior,
                $nuevo,
                $motivo,
                $referencia,
                $userId
            ]);
            
        } catch (Exception $e) {
            error_log("Error registrando movimiento de inventario: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener historial de inventario
     */
    public function getInventoryHistory($variantId = null, $limit = 50) {
        try {
            $where = [];
            $params = [];
            
            if ($variantId) {
                $where[] = "hi.id_variante = ?";
                $params[] = $variantId;
            }
            
            $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
            
            $sql = "SELECT hi.*, 
                           v.talla, v.color,
                           p.nombre as producto_nombre,
                           u.username as usuario_nombre
                   FROM historico_inventario hi
                   INNER JOIN variantes_productos v ON hi.id_variante = v.id_variante
                   INNER JOIN productos_raiz p ON v.id_producto_raiz = p.id_producto_raiz
                   LEFT JOIN usuarios u ON hi.id_usuario = u.id_usuario
                   WHERE $whereClause
                   ORDER BY hi.creado_en DESC
                   LIMIT ?";
            
            $params[] = $limit;
            
            return $this->db->getAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("Error obteniendo historial de inventario: " . $e->getMessage());
            return [];
        }
    }
    
    // --------------------------------------------------------
    // Métodos para catálogo público
    // --------------------------------------------------------
    
    /**
     * Buscar productos para catálogo
     */
    public function searchProducts($filters = [], $page = 1, $perPage = 12) {
        try {
            $where = ["p.estado = 'activo'"];
            $params = [];
            $offset = ($page - 1) * $perPage;
            
            // Filtros básicos
            if (!empty($filters['departamento'])) {
                $where[] = "p.id_departamento = ?";
                $params[] = $filters['departamento'];
            }
            
            if (!empty($filters['tipo'])) {
                $where[] = "p.id_tipo = ?";
                $params[] = $filters['tipo'];
            }
            
            if (!empty($filters['marca'])) {
                $where[] = "p.id_marca = ?";
                $params[] = $filters['marca'];
            }
            
            if (isset($filters['kids'])) {
                $where[] = "p.es_kids = ?";
                $params[] = $filters['kids'];
            }
            
            if (!empty($filters['busqueda'])) {
                $where[] = "(p.nombre LIKE ? OR p.descripcion LIKE ? OR p.codigo_producto LIKE ?)";
                $searchTerm = '%' . $filters['busqueda'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            // Filtros de precio
            if (!empty($filters['precio_min'])) {
                $where[] = "COALESCE(p.precio_oferta, p.precio_venta) >= ?";
                $params[] = $filters['precio_min'];
            }
            
            if (!empty($filters['precio_max'])) {
                $where[] = "COALESCE(p.precio_oferta, p.precio_venta) <= ?";
                $params[] = $filters['precio_max'];
            }
            
            // Filtro de ofertas
            if (!empty($filters['oferta'])) {
                $where[] = "p.precio_oferta IS NOT NULL 
                           AND (p.fecha_inicio_oferta IS NULL OR p.fecha_inicio_oferta <= CURDATE())
                           AND (p.fecha_fin_oferta IS NULL OR p.fecha_fin_oferta >= CURDATE())";
            }
            
            // Filtro de nuevos
            if (!empty($filters['nuevo'])) {
                $where[] = "p.etiqueta = 'nuevo'";
            }
            
            // Ordenamiento
            $orderBy = "p.creado_en DESC";
            if (!empty($filters['orden'])) {
                switch ($filters['orden']) {
                    case 'precio_asc':
                        $orderBy = "COALESCE(p.precio_oferta, p.precio_venta) ASC";
                        break;
                    case 'precio_desc':
                        $orderBy = "COALESCE(p.precio_oferta, p.precio_venta) DESC";
                        break;
                    case 'nombre_asc':
                        $orderBy = "p.nombre ASC";
                        break;
                    case 'nombre_desc':
                        $orderBy = "p.nombre DESC";
                        break;
                    case 'mas_vendidos':
                        $orderBy = "(SELECT COUNT(*) FROM detalle_facturas df 
                                   INNER JOIN variantes_productos vp ON df.id_variante = vp.id_variante
                                   WHERE vp.id_producto_raiz = p.id_producto_raiz) DESC";
                        break;
                }
            }
            
            // Construir consulta
            $whereClause = implode(' AND ', $where);
            
            // Consulta principal
            $sql = "SELECT p.*, 
                           d.nombre as departamento_nombre,
                           t.nombre as tipo_nombre,
                           m.nombre as marca_nombre,
                           (SELECT nombre_archivo FROM fotos_productos fp 
                            WHERE fp.id_producto_raiz = p.id_producto_raiz 
                            AND fp.es_principal = 1 LIMIT 1) as foto_principal,
                           COALESCE(p.precio_oferta, p.precio_venta) as precio_final,
                           CASE 
                               WHEN p.precio_oferta IS NOT NULL 
                               AND (p.fecha_inicio_oferta IS NULL OR p.fecha_inicio_oferta <= CURDATE())
                               AND (p.fecha_fin_oferta IS NULL OR p.fecha_fin_oferta >= CURDATE())
                               THEN ROUND(((p.precio_venta - p.precio_oferta) / p.precio_venta) * 100)
                               ELSE 0
                           END as descuento_porcentaje
                   FROM productos_raiz p
                   LEFT JOIN departamentos d ON p.id_departamento = d.id_departamento
                   LEFT JOIN tipos_ropa t ON p.id_tipo = t.id_tipo
                   LEFT JOIN marcas m ON p.id_marca = m.id_marca
                   WHERE $whereClause
                   ORDER BY $orderBy
                   LIMIT ?, ?";
            
            $params[] = $offset;
            $params[] = $perPage;
            
            $products = $this->db->getAll($sql, $params);
            
            // Contar total para paginación
            $countSql = "SELECT COUNT(*) as total 
                        FROM productos_raiz p
                        WHERE $whereClause";
            
            $countResult = $this->db->getSingle($countSql, array_slice($params, 0, -2));
            $total = $countResult['total'];
            
            return [
                'products' => $products,
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ];
            
        } catch (Exception $e) {
            error_log("Error buscando productos: " . $e->getMessage());
            return [
                'products' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => 0
            ];
        }
    }
    
    /**
     * Obtener productos relacionados
     */
    public function getRelatedProducts($productId, $limit = 4) {
        try {
            // Obtener producto actual
            $product = $this->getById($productId);
            if (!$product) {
                return [];
            }
            
            $sql = "SELECT p.*, 
                           (SELECT nombre_archivo FROM fotos_productos fp 
                            WHERE fp.id_producto_raiz = p.id_producto_raiz 
                            AND fp.es_principal = 1 LIMIT 1) as foto_principal
                   FROM productos_raiz p
                   WHERE p.estado = 'activo'
                   AND p.id_producto_raiz != ?
                   AND (p.id_departamento = ? OR p.id_tipo = ?)
                   ORDER BY RAND()
                   LIMIT ?";
            
            return $this->db->getAll($sql, [
                $productId,
                $product['id_departamento'],
                $product['id_tipo'],
                $limit
            ]);
            
        } catch (Exception $e) {
            error_log("Error obteniendo productos relacionados: " . $e->getMessage());
            return [];
        }
    }
    
    // --------------------------------------------------------
    // Métodos para datos de soporte
    // --------------------------------------------------------
    
    /**
     * Obtener departamentos
     */
    public function getDepartamentos($activos = true) {
        try {
            $where = $activos ? "WHERE estado = 'activo'" : "";
            $sql = "SELECT * FROM departamentos $where ORDER BY orden, nombre";
            return $this->db->getAll($sql);
        } catch (Exception $e) {
            error_log("Error obteniendo departamentos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener tipos de ropa
     */
    public function getTiposRopa($activos = true) {
        try {
            $where = $activos ? "WHERE estado = 'activo'" : "";
            $sql = "SELECT * FROM tipos_ropa $where ORDER BY nombre";
            return $this->db->getAll($sql);
        } catch (Exception $e) {
            error_log("Error obteniendo tipos: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener marcas
     */
    public function getMarcas($activos = true) {
        try {
            $where = $activos ? "WHERE estado = 'activo'" : "";
            $sql = "SELECT * FROM marcas $where ORDER BY nombre";
            return $this->db->getAll($sql);
        } catch (Exception $e) {
            error_log("Error obteniendo marcas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener tallas disponibles
     */
    public function getTallas($categoria = null) {
        try {
            $where = "WHERE estado = 'activo'";
            $params = [];
            
            if ($categoria) {
                $where .= " AND categoria = ?";
                $params[] = $categoria;
            }
            
            $sql = "SELECT * FROM tallas $where ORDER BY orden";
            return $this->db->getAll($sql, $params);
        } catch (Exception $e) {
            error_log("Error obteniendo tallas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener colores disponibles
     */
    public function getColores() {
        try {
            $sql = "SELECT * FROM colores WHERE estado = 'activo' ORDER BY nombre";
            return $this->db->getAll($sql);
        } catch (Exception $e) {
            error_log("Error obteniendo colores: " . $e->getMessage());
            return [];
        }
    }
    
    // --------------------------------------------------------
    // Métodos para estadísticas
    // --------------------------------------------------------
    
    /**
     * Obtener stock total de producto
     */
    private function getProductTotalStock($productId) {
        try {
            $sql = "SELECT SUM(stock_tienda + stock_bodega) as total_stock 
                   FROM variantes_productos 
                   WHERE id_producto_raiz = ?";
            $result = $this->db->getSingle($sql, [$productId]);
            return $result['total_stock'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Obtener productos bajos en stock
     */
    public function getLowStockProducts($limit = 20) {
        try {
            $sql = "SELECT p.*, 
                           d.nombre as departamento_nombre,
                           SUM(v.stock_tienda + v.stock_bodega) as stock_total,
                           SUM(v.stock_minimo) as stock_minimo_total
                   FROM productos_raiz p
                   INNER JOIN departamentos d ON p.id_departamento = d.id_departamento
                   INNER JOIN variantes_productos v ON p.id_producto_raiz = v.id_producto_raiz
                   WHERE p.estado = 'activo'
                   GROUP BY p.id_producto_raiz
                   HAVING stock_total <= stock_minimo_total
                   ORDER BY stock_total ASC
                   LIMIT ?";
            
            return $this->db->getAll($sql, [$limit]);
            
        } catch (Exception $e) {
            error_log("Error obteniendo productos bajos en stock: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener productos más vendidos
     */
    public function getBestSellingProducts($limit = 10, $startDate = null, $endDate = null) {
        try {
            $where = ["mf.estado IN ('confirmada', 'enviada', 'entregada')"];
            $params = [];
            
            if ($startDate) {
                $where[] = "DATE(mf.fecha_factura) >= ?";
                $params[] = $startDate;
            }
            
            if ($endDate) {
                $where[] = "DATE(mf.fecha_factura) <= ?";
                $params[] = $endDate;
            }
            
            $whereClause = implode(' AND ', $where);
            
            $sql = "SELECT p.id_producto_raiz, p.nombre, p.codigo_producto,
                           d.nombre as departamento_nombre,
                           SUM(df.cantidad) as unidades_vendidas,
                           SUM(df.subtotal) as total_ventas
                   FROM productos_raiz p
                   INNER JOIN departamentos d ON p.id_departamento = d.id_departamento
                   INNER JOIN variantes_productos v ON p.id_producto_raiz = v.id_producto_raiz
                   INNER JOIN detalle_facturas df ON v.id_variante = df.id_variante
                   INNER JOIN maestro_facturas mf ON df.id_factura_maestro = mf.id_factura_maestro
                   WHERE $whereClause
                   GROUP BY p.id_producto_raiz
                   ORDER BY unidades_vendidas DESC
                   LIMIT ?";
            
            $params[] = $limit;
            
            return $this->db->getAll($sql, $params);
            
        } catch (Exception $e) {
            error_log("Error obteniendo productos más vendidos: " . $e->getMessage());
            return [];
        }
    }
}
?>