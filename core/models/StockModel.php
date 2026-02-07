<?php
declare(strict_types=1);

namespace Aura\Core\Models;

use PDO;

/**
 * Modelo de Stock/Inventario
 * 
 * Gestiona las operaciones de inventario y movimientos de stock.
 * Garantiza integridad en las actualizaciones de stock.
 * 
 * @package Aura\Core\Models
 */
final class StockModel
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    /**
     * Verifica si hay stock disponible de un producto.
     * 
     * @param int $productoId ID del producto
     * @param int $cantidad Cantidad requerida
     * @return bool True si hay stock suficiente
     */
    public function hayStockDisponible(int $productoId, int $cantidad): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT stock 
            FROM productos 
            WHERE id = :id AND activo = TRUE
        ");

        $stmt->execute(['id' => $productoId]);
        $stockActual = $stmt->fetchColumn();

        return $stockActual !== false && $stockActual >= $cantidad;
    }

    /**
     * Decrementa el stock de un producto y registra el movimiento.
     * 
     * DEBE ejecutarse dentro de una transacción para garantizar atomicidad.
     * 
     * @param int $productoId ID del producto
     * @param int $cantidad Cantidad a decrementar
     * @param string $referencia Referencia del movimiento (ej: folio de venta)
     * @param int $usuarioId ID del usuario que ejecuta la operación
     */
    public function decrementar(
        int $productoId, 
        int $cantidad, 
        string $referencia = '', 
        int $usuarioId = 0
    ): void {
        // Obtener stock actual con bloqueo FOR UPDATE (evita race conditions)
        $stmt = $this->pdo->prepare("
            SELECT stock 
            FROM productos 
            WHERE id = :id 
            FOR UPDATE
        ");

        $stmt->execute(['id' => $productoId]);
        $stockAnterior = (int) $stmt->fetchColumn();

        if ($stockAnterior < $cantidad) {
            throw new \RuntimeException(
                "Stock insuficiente para producto ID {$productoId}. " .
                "Disponible: {$stockAnterior}, Requerido: {$cantidad}"
            );
        }

        $stockNuevo = $stockAnterior - $cantidad;

        // Actualizar stock
        $updateStmt = $this->pdo->prepare("
            UPDATE productos 
            SET stock = :stock_nuevo,
                updated_at = NOW()
            WHERE id = :id
        ");

        $updateStmt->execute([
            'stock_nuevo' => $stockNuevo,
            'id' => $productoId
        ]);

        // Registrar movimiento
        $this->registrarMovimiento(
            $productoId,
            'SALIDA',
            $cantidad,
            $stockAnterior,
            $stockNuevo,
            $referencia,
            $usuarioId
        );
    }

    /**
     * Incrementa el stock de un producto y registra el movimiento.
     * 
     * @param int $productoId ID del producto
     * @param int $cantidad Cantidad a incrementar
     * @param string $referencia Referencia del movimiento
     * @param int $usuarioId ID del usuario que ejecuta la operación
     */
    public function incrementar(
        int $productoId,
        int $cantidad,
        string $referencia = '',
        int $usuarioId = 0
    ): void {
        // Obtener stock actual
        $stmt = $this->pdo->prepare("
            SELECT stock 
            FROM productos 
            WHERE id = :id 
            FOR UPDATE
        ");

        $stmt->execute(['id' => $productoId]);
        $stockAnterior = (int) $stmt->fetchColumn();
        $stockNuevo = $stockAnterior + $cantidad;

        // Actualizar stock
        $updateStmt = $this->pdo->prepare("
            UPDATE productos 
            SET stock = :stock_nuevo,
                updated_at = NOW()
            WHERE id = :id
        ");

        $updateStmt->execute([
            'stock_nuevo' => $stockNuevo,
            'id' => $productoId
        ]);

        // Registrar movimiento
        $this->registrarMovimiento(
            $productoId,
            'ENTRADA',
            $cantidad,
            $stockAnterior,
            $stockNuevo,
            $referencia,
            $usuarioId
        );
    }

    /**
     * Registra un movimiento de stock en el log.
     * 
     * @param int $productoId ID del producto
     * @param string $tipo Tipo de movimiento (ENTRADA, SALIDA, AJUSTE)
     * @param int $cantidad Cantidad del movimiento
     * @param int $stockAnterior Stock antes del movimiento
     * @param int $stockNuevo Stock después del movimiento
     * @param string $referencia Referencia externa
     * @param int $usuarioId ID del usuario
     */
    private function registrarMovimiento(
        int $productoId,
        string $tipo,
        int $cantidad,
        int $stockAnterior,
        int $stockNuevo,
        string $referencia,
        int $usuarioId
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO stock_movimientos (
                producto_id,
                tipo,
                cantidad,
                stock_anterior,
                stock_nuevo,
                referencia,
                usuario_id,
                created_at
            ) VALUES (
                :producto_id,
                :tipo,
                :cantidad,
                :stock_anterior,
                :stock_nuevo,
                :referencia,
                :usuario_id,
                NOW()
            )
        ");

        $stmt->execute([
            'producto_id' => $productoId,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'stock_anterior' => $stockAnterior,
            'stock_nuevo' => $stockNuevo,
            'referencia' => $referencia,
            'usuario_id' => $usuarioId
        ]);
    }

    /**
     * Obtiene el historial de movimientos de un producto.
     * 
     * @param int $productoId ID del producto
     * @param int $limite Cantidad de registros a retornar
     * @return array Array de movimientos
     */
    public function obtenerHistorial(int $productoId, int $limite = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                sm.id,
                sm.tipo,
                sm.cantidad,
                sm.stock_anterior,
                sm.stock_nuevo,
                sm.referencia,
                sm.created_at,
                u.nombre_completo AS usuario_nombre
            FROM stock_movimientos sm
            LEFT JOIN usuarios u ON sm.usuario_id = u.id
            WHERE sm.producto_id = :producto_id
            ORDER BY sm.created_at DESC
            LIMIT :limite
        ");

        $stmt->execute([
            'producto_id' => $productoId,
            'limite' => $limite
        ]);

        return $stmt->fetchAll();
    }

    /**
     * Obtiene productos con stock bajo (menos que stock_minimo).
     * 
     * @return array Array de productos con stock bajo
     */
    public function obtenerProductosConStockBajo(): array
    {
        $stmt = $this->pdo->query("
            SELECT 
                id,
                codigo,
                nombre,
                stock,
                stock_minimo
            FROM productos
            WHERE activo = TRUE
              AND stock <= stock_minimo
            ORDER BY stock ASC
        ");

        return $stmt->fetchAll();
    }
}
