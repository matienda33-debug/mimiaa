<?php
// app/models/Reporte.php
class Reporte {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    // Reporte de ventas por período
    public function obtenerVentasPorPeriodo($fecha_inicio, $fecha_fin, $agrupar_por = 'dia') {
        try {
            $format = '';
            $group_by = '';
            
            switch ($agrupar_por) {
                case 'hora':
                    $format = '%Y-%m-%d %H:00';
                    $group_by = 'DATE_FORMAT(fecha_factura, "%Y-%m-%d %H")';
                    break;
                case 'dia':
                    $format = '%Y-%m-%d';
                    $group_by = 'DATE(fecha_factura)';
                    break;
                case 'semana':
                    $format = '%Y-%U';
                    $group_by = 'YEARWEEK(fecha_factura)';
                    break;
                case 'mes':
                    $format = '%Y-%m';
                    $group_by = 'DATE_FORMAT(fecha_factura, "%Y-%m")';
                    break;
                case 'anio':
                    $format = '%Y';
                    $group_by = 'YEAR(fecha_factura)';
                    break;
            }
            
            $query = "SELECT 
                        DATE_FORMAT(fecha_factura, :format) as periodo,
                        COUNT(*) as cantidad_facturas,
                        SUM(total) as total_ventas,
                        AVG(total) as promedio_venta,
                        SUM(puntos_generados) as puntos_generados,
                        COUNT(DISTINCT id_cliente) as clientes_unicos
                     FROM maestro_facturas 
                     WHERE estado IN ('confirmada', 'enviada', 'entregada')
                     AND fecha_factura BETWEEN :fecha_inicio AND :fecha_fin
                     GROUP BY $group_by
                     ORDER BY fecha_factura ASC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':format', $format);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error obtener ventas por periodo: " . $e->getMessage());
            return [];
        }
    }
    
    // Reporte de ventas por producto
    public function obtenerVentasPorProducto($fecha_inicio, $fecha_fin) {
        try {
            $query = "SELECT 
                        p.id_producto_raiz,
                        p.codigo_producto,
                        p.nombre as producto,
                        d.nombre as departamento,
                        COUNT(DISTINCT df.id_factura_maestro) as veces_vendido,
                        SUM(df.cantidad) as unidades_vendidas,
                        SUM(df.subtotal) as total_ventas,
                        AVG(df.precio_unitario) as precio_promedio
                     FROM detalle_facturas df
                     JOIN variantes_productos v ON df.id_variante = v.id_variante
                     JOIN productos_raiz p ON v.id_producto_raiz = p.id_producto_raiz
                     JOIN departamentos d ON p.id_departamento = d.id_departamento
                     JOIN maestro_facturas mf ON df.id_factura_maestro = mf.id_factura_maestro
                     WHERE mf.estado IN ('confirmada', 'enviada', 'entregada')
                     AND mf.fecha_factura BETWEEN :fecha_inicio AND :fecha_fin
                     GROUP BY p.id_producto_raiz
                     ORDER BY total_ventas DESC
                     LIMIT 50";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error obtener ventas por producto: " . $e->getMessage());
            return [];
        }
    }
    
    // Reporte de ventas por vendedor
    public function obtenerVentasPorVendedor($fecha_inicio, $fecha_fin) {
        try {
            $query = "SELECT 
                        u.id_usuario,
                        CONCAT(p.nombres, ' ', p.apellidos) as vendedor,
                        COUNT(*) as cantidad_facturas,
                        SUM(mf.total) as total_ventas,
                        AVG(mf.total) as promedio_venta,
                        SUM(mf.puntos_generados) as puntos_generados,
                        COUNT(DISTINCT mf.id_cliente) as clientes_atendidos
                     FROM maestro_facturas mf
                     JOIN usuarios u ON mf.id_vendedor = u.id_usuario
                     JOIN personas p ON u.id_persona = p.id_persona
                     WHERE mf.estado IN ('confirmada', 'enviada', 'entregada')
                     AND mf.fecha_factura BETWEEN :fecha_inicio AND :fecha_fin
                     GROUP BY u.id_usuario
                     ORDER BY total_ventas DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error obtener ventas por vendedor: " . $e->getMessage());
            return [];
        }
    }
    
    // Reporte de inventario
    public function obtenerReporteInventario() {
        try {
            $query = "SELECT 
                        p.id_producto_raiz,
                        p.codigo_producto,
                        p.nombre as producto,
                        d.nombre as departamento,
                        COUNT(DISTINCT v.id_variante) as variantes,
                        SUM(v.stock_tienda) as stock_tienda,
                        SUM(v.stock_bodega) as stock_bodega,
                        SUM(v.stock_tienda + v.stock_bodega) as stock_total,
                        SUM(v.stock_minimo) as stock_minimo_total,
                        CASE 
                            WHEN SUM(v.stock_tienda + v.stock_bodega) = 0 THEN 'agotado'
                            WHEN SUM(v.stock_tienda + v.stock_bodega) <= SUM(v.stock_minimo) THEN 'bajo'
                            ELSE 'normal'
                        END as estado_stock
                     FROM productos_raiz p
                     JOIN departamentos d ON p.id_departamento = d.id_departamento
                     JOIN variantes_productos v ON p.id_producto_raiz = v.id_producto_raiz
                     WHERE p.estado = 'activo'
                     GROUP BY p.id_producto_raiz
                     ORDER BY stock_total ASC";
            
            $stmt = $this->conn->query($query);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error obtener reporte inventario: " . $e->getMessage());
            return [];
        }
    }
    
    // Reporte de contabilidad
    public function obtenerEstadoContable($fecha_inicio, $fecha_fin) {
        try {
            $query = "SELECT 
                        tipo,
                        categoria,
                        SUM(monto) as total,
                        COUNT(*) as cantidad_movimientos
                     FROM contabilidad
                     WHERE fecha_contable BETWEEN :fecha_inicio AND :fecha_fin
                     AND estado = 'confirmado'
                     GROUP BY tipo, categoria
                     ORDER BY tipo, total DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error obtener estado contable: " . $e->getMessage());
            return [];
        }
    }
    
    // Reporte de clientes
    public function obtenerReporteClientes($limite = 100) {
        try {
            $query = "SELECT 
                        c.id_persona,
                        CONCAT(p.nombres, ' ', p.apellidos) as cliente,
                        p.dpi,
                        p.email,
                        p.telefono,
                        pc.puntos_acumulados,
                        pc.puntos_disponibles,
                        pc.total_gastado,
                        COUNT(DISTINCT mf.id_factura_maestro) as compras_realizadas,
                        MAX(mf.fecha_factura) as ultima_compra
                     FROM personas p
                     LEFT JOIN puntos_clientes pc ON p.id_persona = pc.id_persona
                     LEFT JOIN maestro_facturas mf ON p.id_persona = mf.id_cliente
                     WHERE mf.estado IN ('confirmada', 'enviada', 'entregada')
                     OR mf.id_cliente IS NULL
                     GROUP BY p.id_persona
                     ORDER BY pc.total_gastado DESC
                     LIMIT :limite";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error obtener reporte clientes: " . $e->getMessage());
            return [];
        }
    }
    
    // Métricas del dashboard
    public function obtenerMetricasDashboard($fecha_inicio, $fecha_fin) {
        try {
            $query = "SELECT 
                        -- Ventas
                        (SELECT COUNT(*) FROM maestro_facturas 
                         WHERE fecha_factura BETWEEN :fecha_inicio AND :fecha_fin
                         AND estado IN ('confirmada', 'enviada', 'entregada')) as total_ventas,
                        
                        (SELECT SUM(total) FROM maestro_facturas 
                         WHERE fecha_factura BETWEEN :fecha_inicio AND :fecha_fin
                         AND estado IN ('confirmada', 'enviada', 'entregada')) as total_ingresos,
                        
                        -- Clientes
                        (SELECT COUNT(DISTINCT id_cliente) FROM maestro_facturas 
                         WHERE fecha_factura BETWEEN :fecha_inicio AND :fecha_fin
                         AND estado IN ('confirmada', 'enviada', 'entregada')) as clientes_nuevos,
                        
                        -- Productos
                        (SELECT COUNT(*) FROM productos_raiz WHERE estado = 'activo') as productos_activos,
                        
                        (SELECT COUNT(*) FROM variantes_productos 
                         WHERE (stock_tienda + stock_bodega) <= stock_minimo) as productos_bajo_stock,
                        
                        -- Inventario
                        (SELECT SUM(stock_tienda + stock_bodega) FROM variantes_productos) as stock_total,
                        
                        -- Puntos
                        (SELECT SUM(puntos_generados) FROM maestro_facturas 
                         WHERE fecha_factura BETWEEN :fecha_inicio AND :fecha_fin) as puntos_generados,
                        
                        -- Valor promedio de venta
                        (SELECT AVG(total) FROM maestro_facturas 
                         WHERE fecha_factura BETWEEN :fecha_inicio AND :fecha_fin
                         AND estado IN ('confirmada', 'enviada', 'entregada')) as promedio_venta";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':fecha_inicio', $fecha_inicio);
            $stmt->bindParam(':fecha_fin', $fecha_fin);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error obtener metricas dashboard: " . $e->getMessage());
            return [];
        }
    }
    
    // Obtener comparativa con período anterior
    public function obtenerComparativaPeriodo($fecha_actual_inicio, $fecha_actual_fin, 
                                            $periodo_anterior_inicio, $periodo_anterior_fin) {
        try {
            $query = "SELECT 
                        'actual' as periodo,
                        COUNT(*) as ventas,
                        SUM(total) as ingresos,
                        AVG(total) as promedio
                     FROM maestro_facturas 
                     WHERE estado IN ('confirmada', 'enviada', 'entregada')
                     AND fecha_factura BETWEEN :actual_inicio AND :actual_fin
                     
                     UNION ALL
                     
                     SELECT 
                        'anterior' as periodo,
                        COUNT(*) as ventas,
                        SUM(total) as ingresos,
                        AVG(total) as promedio
                     FROM maestro_facturas 
                     WHERE estado IN ('confirmada', 'enviada', 'entregada')
                     AND fecha_factura BETWEEN :anterior_inicio AND :anterior_fin";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':actual_inicio', $fecha_actual_inicio);
            $stmt->bindParam(':actual_fin', $fecha_actual_fin);
            $stmt->bindParam(':anterior_inicio', $periodo_anterior_inicio);
            $stmt->bindParam(':anterior_fin', $periodo_anterior_fin);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            error_log("Error obtener comparativa: " . $e->getMessage());
            return [];
        }
    }
}
?>