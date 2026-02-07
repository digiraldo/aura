<?php
declare(strict_types=1);

namespace Aura\Core\Controllers;

use Aura\Core\Auth\Auth;
use Aura\Core\Auth\UnauthorizedException;
use Aura\Core\Models\VentaModel;
use Aura\Core\Models\StockModel;
use PDO;
use PDOException;
use Exception;

/**
 * Controlador de Ventas POS
 * 
 * Implementa RF-005: Procesamiento Atómico de Venta.
 * Garantiza cumplimiento ACID en todas las transacciones.
 * 
 * @package Aura\Core\Controllers
 */
final class SalesController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Auth $auth,
        private readonly VentaModel $ventaModel,
        private readonly StockModel $stockModel
    ) {}

    /**
     * Procesa una venta con actualización atómica de inventario.
     * 
     * Implementa RF-005 con propiedades ACID:
     * - Atomicidad: Venta + Stock + Pago en una sola transacción
     * - Consistencia: Validaciones de stock y suma de pagos antes de commit
     * - Aislamiento: REPEATABLE READ para evitar condiciones de carrera
     * - Durabilidad: Commit garantiza persistencia ante fallos
     * 
     * @param array $ventaData Datos de la venta (cliente_id, subtotal, impuestos, descuento, total, notas)
     * @param array $items Productos con cantidades [['producto_id' => int, 'cantidad' => int, 'precio_unitario' => float]]
     * @param array $pagos Métodos de pago [['metodo' => string, 'monto' => float, 'referencia' => string]]
     * @return array Datos de la venta creada con ID y folio
     * @throws UnauthorizedException Si el usuario no tiene permisos
     * @throws VentaException Si falla validación o transacción
     */
    public function procesarVenta(
        array $ventaData,
        array $items,
        array $pagos
    ): array {
        // RF-004: Verificar permiso (Regla #3 del RBAC)
        $this->auth->requirePermission('ventas.crear');

        // Validaciones previas
        $this->validarDatosVenta($ventaData, $items, $pagos);

        try {
            // Nivel de aislamiento para evitar lecturas inconsistentes (ACID: Isolation)
            $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            
            // Iniciar transacción (ACID: Atomicity)
            $this->pdo->beginTransaction();

            // 1. Validar stock disponible ANTES de cualquier modificación (ACID: Consistency)
            foreach ($items as $item) {
                if (!$this->stockModel->hayStockDisponible($item['producto_id'], $item['cantidad'])) {
                    throw new VentaException(
                        "Stock insuficiente para producto ID {$item['producto_id']}"
                    );
                }
            }

            // 2. Registrar venta
            $ventaData['usuario_id'] = $this->auth->getUserId();
            $ventaId = $this->ventaModel->crear($ventaData);

            // Obtener folio generado
            $venta = $this->ventaModel->obtenerPorId($ventaId);
            $folio = $venta['folio'];

            // 3. Registrar detalle de items
            foreach ($items as $item) {
                $this->ventaModel->agregarItem($ventaId, $item);
            }

            // 4. Actualizar stock (decrementar) con trazabilidad
            foreach ($items as $item) {
                $this->stockModel->decrementar(
                    $item['producto_id'],
                    $item['cantidad'],
                    $folio, // Referencia: folio de la venta
                    $this->auth->getUserId()
                );
            }

            // 5. Registrar pagos (RF-006: Múltiples métodos de pago)
            foreach ($pagos as $pago) {
                $this->ventaModel->registrarPago($ventaId, $pago);
            }

            // 6. Validar que suma de pagos = total venta (ACID: Consistency)
            $totalPagos = array_sum(array_column($pagos, 'monto'));
            $totalVenta = (float) $ventaData['total'];
            
            // Tolerancia de 0.01 para redondeos de centavos
            if (abs($totalPagos - $totalVenta) > 0.01) {
                throw new VentaException(
                    "Total de pagos ({$totalPagos}) no coincide con total venta ({$totalVenta})"
                );
            }

            // 7. Registrar en auditoría
            $this->registrarAuditoria($ventaId, 'CREADA', $ventaData, $items, $pagos);

            // 8. Confirmar transacción (ACID: Durability)
            $this->pdo->commit();

            // Retornar datos de la venta creada
            return [
                'success' => true,
                'venta_id' => $ventaId,
                'folio' => $folio,
                'total' => $totalVenta,
                'message' => "Venta {$folio} procesada exitosamente"
            ];

        } catch (PDOException $e) {
            // Rollback automático en caso de error de base de datos
            $this->pdo->rollBack();
            
            // Log del error para análisis forense
            error_log(sprintf(
                "Error PDO en venta - Usuario %d: %s",
                $this->auth->getUserId(),
                $e->getMessage()
            ));
            
            throw new VentaException(
                "Error al procesar venta: Error de base de datos",
                previous: $e
            );

        } catch (VentaException $e) {
            // Rollback en errores de negocio
            $this->pdo->rollBack();
            throw $e;

        } catch (Exception $e) {
            // Rollback en cualquier otro error inesperado
            $this->pdo->rollBack();
            
            error_log(sprintf(
                "Error inesperado en venta - Usuario %d: %s",
                $this->auth->getUserId(),
                $e->getMessage()
            ));
            
            throw new VentaException(
                "Error inesperado al procesar venta",
                previous: $e
            );
        }
    }

    /**
     * Valida los datos de entrada para una venta.
     * 
     * @param array $ventaData Datos de la venta
     * @param array $items Items de la venta
     * @param array $pagos Pagos de la venta
     * @throws VentaException Si los datos no son válidos
     */
    private function validarDatosVenta(array $ventaData, array $items, array $pagos): void
    {
        // Validar que haya al menos un item
        if (empty($items)) {
            throw new VentaException("La venta debe tener al menos un producto");
        }

        // Validar que haya al menos un pago
        if (empty($pagos)) {
            throw new VentaException("La venta debe tener al menos un método de pago");
        }

        // Validar campos requeridos en ventaData
        $requeridos = ['subtotal', 'total'];
        foreach ($requeridos as $campo) {
            if (!isset($ventaData[$campo])) {
                throw new VentaException("Campo requerido faltante: {$campo}");
            }
        }

        // Validar que el total sea positivo
        if ($ventaData['total'] <= 0) {
            throw new VentaException("El total de la venta debe ser mayor a cero");
        }

        // Validar estructura de items
        foreach ($items as $index => $item) {
            if (empty($item['producto_id']) || empty($item['cantidad']) || !isset($item['precio_unitario'])) {
                throw new VentaException("Item #{$index} tiene datos incompletos");
            }

            if ($item['cantidad'] <= 0) {
                throw new VentaException("Item #{$index}: La cantidad debe ser mayor a cero");
            }

            if ($item['precio_unitario'] < 0) {
                throw new VentaException("Item #{$index}: El precio no puede ser negativo");
            }
        }

        // Validar estructura de pagos
        foreach ($pagos as $index => $pago) {
            if (empty($pago['metodo']) || !isset($pago['monto'])) {
                throw new VentaException("Pago #{$index} tiene datos incompletos");
            }

            if ($pago['monto'] <= 0) {
                throw new VentaException("Pago #{$index}: El monto debe ser mayor a cero");
            }

            // Validar que el método de pago sea válido
            $metodosValidos = ['efectivo', 'tarjeta', 'transferencia', 'cheque'];
            if (!in_array($pago['metodo'], $metodosValidos, true)) {
                throw new VentaException("Pago #{$index}: Método de pago inválido");
            }
        }
    }

    /**
     * Registra la venta en la tabla de auditoría.
     * 
     * @param int $ventaId ID de la venta
     * @param string $accion Acción realizada (CREADA, MODIFICADA, CANCELADA)
     * @param array $ventaData Datos de la venta
     * @param array $items Items de la venta
     * @param array $pagos Pagos de la venta
     */
    private function registrarAuditoria(
        int $ventaId,
        string $accion,
        array $ventaData,
        array $items,
        array $pagos
    ): void {
        $datosNuevos = [
            'venta' => $ventaData,
            'items' => $items,
            'pagos' => $pagos
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO auditoria_ventas (
                venta_id,
                usuario_id,
                accion,
                datos_nuevos,
                ip_address,
                user_agent,
                timestamp
            ) VALUES (
                :venta_id,
                :usuario_id,
                :accion,
                :datos_nuevos,
                :ip_address,
                :user_agent,
                NOW()
            )
        ");

        $stmt->execute([
            'venta_id' => $ventaId,
            'usuario_id' => $this->auth->getUserId(),
            'accion' => $accion,
            'datos_nuevos' => json_encode($datosNuevos),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }

    /**
     * Obtiene el detalle completo de una venta.
     * 
     * @param int $ventaId ID de la venta
     * @return array Datos completos de la venta
     */
    public function obtenerDetalle(int $ventaId): array
    {
        // Verificar permiso
        $this->auth->requirePermission('ventas.listar');

        $venta = $this->ventaModel->obtenerPorId($ventaId);

        if (!$venta) {
            throw new VentaException("Venta no encontrada", 404);
        }

        // Obtener items y pagos
        $venta['items'] = $this->ventaModel->obtenerItems($ventaId);
        $venta['pagos'] = $this->ventaModel->obtenerPagos($ventaId);

        return $venta;
    }

    /**
     * Lista ventas con filtros y paginación.
     * 
     * @param array $filtros Filtros opcionales
     * @param int $pagina Página actual
     * @param int $porPagina Registros por página
     * @return array Resultado con datos y metadata de paginación
     */
    public function listar(array $filtros = [], int $pagina = 1, int $porPagina = 50): array
    {
        // Verificar permiso
        $this->auth->requirePermission('ventas.listar');

        return $this->ventaModel->listar($filtros, $pagina, $porPagina);
    }
}

/**
 * Excepción personalizada para errores de venta.
 */
class VentaException extends Exception
{
    public function __construct(string $message, int $code = 400, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
