-- Agregar campo correo_envio a la tabla factura_cabecera
-- Ejecutar esta migración si no existe el campo

ALTER TABLE `factura_cabecera` 
ADD COLUMN `correo_envio` VARCHAR(255) DEFAULT NULL 
AFTER `correo_enviado`;

-- Crear índice para búsqueda rápida
ALTER TABLE `factura_cabecera` 
ADD INDEX `IDX_correo_envio` (`correo_envio`);
