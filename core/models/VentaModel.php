<?php
declare(strict_types=1);

namespace Aura\Core\Models;

use PDO;

/**
 * Modelo de Ventas
 * 
 * Gestiona las operaciones de ventas en el sistema POS.
 * Implementa RF-005: Procesamiento Atómico de Venta.
 * 
 * @package Aura\Core\Models
 */
final class VentaModel
{
    public function __construct(
        private readonly PDO $pdo
    ) {}

    /**
     * Crea una nueva venta con folio autogenerado.
     * 
     * @param array $data Datos de la venta (cliente_id, usuario_id, subtotal, impuestos, descuento, total)
     * @return int ID de la venta creada
     */
    public function crear(array $data): int
    {
        // Generar folio único
        $folio = $this->generarFolio();

        $stmt = $this->pdo->prepare("
            INSERT INTO ventas (
                folio,
                cliente_id,
                usuario_id,
                subtotal,
                impuestos,
                descuento,
                total,
                estado,
                notas,
                created_at
            ) VALUES (
                :folio,
                :cliente_id,
                :usuario_id,
                :subtotal,
                :impuestos,
                :descuento,
                :total,
                'COMPLETADA',
                :notas,
                NOW()
            )
        ");

        $stmt->execute([
            'folio' => $folio,
            'cliente_id' => $data['cliente_id'] ?? null,
            'usuario_id' => $data['usuario_id'],
            'subtotal' => $data['subtotal'],
            'impuestos' => $data['impuestos'] ?? 0,
            'descuento' => $data['descuento'] ?? 0,
            'total' => $data['total'],
            'notas' => $data['notas'] ?? null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Agrega un item a una venta existente.
     * 
     * @param int $ventaId ID de la venta
     * @param array $item Datos del item (producto_id, cantidad, precio_unitario)
     */
    public function agregarItem(int $ventaId, array $item): void
    {
        $subtotal = $item['cantidad'] * $item['precio_unitario'];

        $stmt = $this->pdo->prepare("
            INSERT INTO venta_items (
                venta_id,
                producto_id,
                cantidad,
                precio_unitario,
                subtotal
            ) VALUES (
                :venta_id,
                :producto_id,
                :cantidad,
                :precio_unitario,
                :subtotal
            )
        ");

        $stmt->execute([
            'venta_id' => $ventaId,
            'producto_id' => $item['producto_id'],
            'cantidad' => $item['cantidad'],
            'precio_unitario' => $item['precio_unitario'],
            'subtotal' => $subtotal
        ]);
    }

    /**
     * Registra un pago asociado a una venta.
     * 
     * Implementa RF-006: Gestión de Métodos de Pago.
     * 
     * @param int $ventaId ID de la venta
     * @param array $pago Datos del pago (metodo, monto, referencia)
     */
    public function registrarPago(int $ventaId, array $pago): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO venta_pagos (
                venta_id,
                metodo,
                monto,
                referencia,
                created_at
            ) VALUES (
                :venta_id,
                :metodo,
                :monto,
                :referencia,
                NOW()
            )
        ");

        $stmt->execute([
            'venta_id' => $ventaId,
            'metodo' => $pago['metodo'],
            'monto' => $pago['monto'],
            'referencia' => $pago['referencia'] ?? null
        ]);
    }

    /**
     * Obtiene una venta por su ID (selección explícita de columnas).
     * 
     * @param int $id ID de la venta
     * @return array|null Datos de la venta o null si no existe
     */
    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                v.id,
                v.folio,
                v.cliente_id,
                v.usuario_id,
                v.subtotal,
                v.impuestos,
                v.descuento,
                v.total,
                v.estado,
                v.notas,
                v.created_at,
                v.updated_at,
                c.nombre AS cliente_nombre,
                u.nombre_completo AS vendedor_nombre
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            WHERE v.id = :id
        ");

        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Obtiene los items de una venta.
     * 
     * @param int $ventaId ID de la venta
     * @return array Array de items
     */
    public function obtenerItems(int $ventaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                vi.id,
                vi.producto_id,
                vi.cantidad,
                vi.precio_unitario,
                vi.subtotal,
                p.codigo AS producto_codigo,
                p.nombre AS producto_nombre
            FROM venta_items vi
            INNER JOIN productos p ON vi.producto_id = p.id
            WHERE vi.venta_id = :venta_id
        ");

        $stmt->execute(['venta_id' => $ventaId]);

        return $stmt->fetchAll();
    }

    /**
     * Obtiene los pagos de una venta.
     * 
     * @param int $ventaId ID de la venta
     * @return array Array de pagos
     */
    public function obtenerPagos(int $ventaId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                metodo,
                monto,
                referencia,
                created_at
            FROM venta_pagos
            WHERE venta_id = :venta_id
            ORDER BY created_at ASC
        ");

        $stmt->execute(['venta_id' => $ventaId]);

        return $stmt->fetchAll();
    }

    /**
     * Genera un folio único para la venta.
     * 
     * Formato: VTA-YYYYMMDD-NNNN
     * 
     * @return string Folio generado
     */
    private function generarFolio(): string
    {
        $fecha = date('Ymd');
        
        // Obtener el último número secuencial del día
        $stmt = $this->pdo->prepare("
            SELECT folio 
            FROM ventas 
            WHERE folio LIKE :pattern 
            ORDER BY id DESC 
            LIMIT 1
        ");
        
        $stmt->execute(['pattern' => "VTA-{$fecha}-%"]);
        $ultimoFolio = $stmt->fetchColumn();

        if ($ultimoFolio) {
            // Extraer número secuencial y incrementar
            $numero = (int) substr($ultimoFolio, -4) + 1;
        } else {
            // Primer folio del día
            $numero = 1;
        }

        return sprintf('VTA-%s-%04d', $fecha, $numero);
    }

    /**
     * Lista ventas con paginación y filtros.
     * 
     * @param array $filtros Filtros opcionales (fecha_desde, fecha_hasta, estado, usuario_id)
     * @param int $pagina Página actual (1-indexed)
     * @param int $porPagina Cantidad de registros por página
     * @return array Array con 'data' y 'total'
     */
    public function listar(array $filtros = [], int $pagina = 1, int $porPagina = 50): array
    {
        $where = [];
        $params = [];

        // Aplicar filtros
        if (!empty($filtros['fecha_desde'])) {
            $where[] = "v.created_at >= :fecha_desde";
            $params['fecha_desde'] = $filtros['fecha_desde'] . ' 00:00:00';
        }

        if (!empty($filtros['fecha_hasta'])) {
            $where[] = "v.created_at <= :fecha_hasta";
            $params['fecha_hasta'] = $filtros['fecha_hasta'] . ' 23:59:59';
        }

        if (!empty($filtros['estado'])) {
            $where[] = "v.estado = :estado";
            $params['estado'] = $filtros['estado'];
        }

        if (!empty($filtros['usuario_id'])) {
            $where[] = "v.usuario_id = :usuario_id";
            $params['usuario_id'] = $filtros['usuario_id'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Contar total de registros
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) 
            FROM ventas v 
            {$whereClause}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Calcular offset
        $offset = ($pagina - 1) * $porPagina;
        $params['limit'] = $porPagina;
        $params['offset'] = $offset;

        // Obtener datos
        $stmt = $this->pdo->prepare("
            SELECT 
                v.id,
                v.folio,
                v.total,
                v.estado,
                v.created_at,
                c.nombre AS cliente_nombre,
                u.nombre_completo AS vendedor_nombre
            FROM ventas v
            LEFT JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            {$whereClause}
            ORDER BY v.created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        $stmt->execute($params);
        $data = $stmt->fetchAll();

        return [
            'data' => $data,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => ceil($total / $porPagina)
        ];
    }
}
