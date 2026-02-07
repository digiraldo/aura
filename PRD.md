# Product Requirements Document (PRD)
## Plataforma Aura - El WordPress de la Contabilidad

---

## 1. Resumen Ejecutivo

### 1.1 Visión del Producto
Aura es una plataforma ERP/CRM multi-tenant de próxima generación que redefine la gestión empresarial mediante un ecosistema extensible de grado bancario. Inspirada en el modelo de éxito de WordPress, Aura permite a las empresas gestionar sus operaciones críticas con total soberanía de datos, mientras ofrece a desarrolladores terceros un marco seguro para crear innovaciones verticales específicas por industria.

### 1.2 Propuesta de Valor Única
- **Confianza como Servicio (Trust as a Service)**: Aislamiento multi-tenant de grado bancario que convierte la seguridad técnica en ventaja competitiva
- **Extensibilidad Sin Límites**: Ecosistema de plugins inspirado en WordPress para verticalizaciones sin comprometer el núcleo
- **Costo-Eficiencia Operativa**: Arquitectura multi-tenant que optimiza recursos mientras mantiene aislamiento total de datos

### 1.3 Objetivos Estratégicos de Negocio
1. **Escalabilidad Masiva**: Servir a 10,000+ inquilinos en una sola instancia sin degradación de rendimiento
2. **Time-to-Market Reducido**: Ecosistema de plugins que permite verticalizaciones en semanas, no meses
3. **Retención Superior**: Soberanía de datos y backups individuales que generan confianza inquebrantable
4. **Monetización del Ecosistema**: Marketplace de plugins que genera ingresos recurrentes adicionales

---

## 2. Segmentos de Usuarios y Casos de Uso

### 2.1 Usuarios Primarios

#### A. Empresas Cliente (Inquilinos)
**Perfil**: PyMEs de 5-500 empleados en retail, manufactura, servicios profesionales

**Necesidades Críticas**:
- Integridad transaccional absoluta en operaciones financieras
- Personalización sin costos de desarrollo prohibitivos
- Migración de datos sin vendor lock-in
- Cumplimiento normativo (GDPR, SOX, regulaciones locales)

**Jobs-to-be-Done**:
- "Necesito procesar 500 ventas diarias sin riesgo de inconsistencias de inventario"
- "Quiero personalizar mi flujo de aprobaciones sin contratar un equipo de desarrollo"
- "Debo poder exportar todos mis datos en cualquier momento"

#### B. Desarrolladores de Plugins (Ecosistema)
**Perfil**: Estudios de software, freelancers especializados, SIs verticales

**Necesidades Críticas**:
- APIs estables y bien documentadas
- Garantía de compatibilidad backward entre versiones
- Sandbox de desarrollo aislado del core
- Monetización clara en marketplace

**Jobs-to-be-Done**:
- "Necesito crear un plugin de nómina sin tocar el código del núcleo"
- "Quiero publicar en el marketplace y generar ingresos pasivos"
- "Debo garantizar que mis actualizaciones no rompan instalaciones existentes"

#### C. Usuarios Finales (Operadores)
**Perfil**: Vendedores, contadores, gerentes, administradores

**Roles Definidos**:
- **Administrador**: Configuración total de la instancia, gestión de usuarios y maestros
- **Vendedor**: Operaciones POS, procesamiento de ventas y pagos
- **Especial**: Roles personalizados vía ExtensionPoints

---

## 3. Requisitos Funcionales de Fase I (MVP Blindado)

### 3.1 Módulo: Gestión Multi-Tenant

#### RF-001: Creación de Inquilino (Tenant)
**Prioridad**: P0 (Crítico)

**Descripción**: El sistema debe permitir la creación automática de un nuevo inquilino con su esquema de base de datos aislado.

**Criterios de Aceptación**:
- [ ] Crear esquema MySQL independiente con nomenclatura `tenant_{id}`
- [ ] Generar estructura de tablas base (usuarios, roles, permisos, configuración)
- [ ] Configurar usuario administrador inicial con credenciales seguras
- [ ] Ejecutar migraciones base sin afectar otros inquilinos
- [ ] Tiempo de aprovisionamiento < 30 segundos

**Restricciones Técnicas**:
- Debe usar `SchemaManager` con PDO para switch de contexto
- Prohibido compartir tablas entre esquemas (tenant_id forbidden)

---

#### RF-002: Switch Dinámico de Esquema
**Prioridad**: P0 (Crítico)

**Descripción**: El sistema debe cambiar el contexto de base de datos según el inquilino autenticado en cada request.

**Criterios de Aceptación**:
- [ ] Identificar inquilino mediante dominio/subdominio o token
- [ ] Ejecutar `USE tenant_{id}` antes de cualquier query
- [ ] Validar que el esquema existe antes del switch
- [ ] Manejar errores de esquema inexistente con código HTTP 404
- [ ] Overhead de switch < 5ms por request

**Casos de Borde**:
- Request sin autenticación válida → Redirigir a login
- Esquema corrupto → Modo mantenimiento con notificación a superadmin

---

### 3.2 Módulo: Control de Acceso RBAC

#### RF-003: Definición de Roles Jerárquicos
**Prioridad**: P0 (Crítico)

**Descripción**: Implementar sistema RBAC con jerarquía de herencia de permisos.

**Criterios de Aceptación**:
- [ ] Roles base: ADMIN, SELLER, SPECIAL (usando PHP 8.2 Enums)
- [ ] ADMIN hereda automáticamente permisos de SELLER
- [ ] SPECIAL permite definición dinámica de permisos vía UI
- [ ] Validación obligatoria de rol activo en sesión
- [ ] Logs de auditoría para cambios de permisos

**Estructura de Permisos** (Ejemplos):
```
- ventas.crear
- ventas.editar
- ventas.eliminar
- productos.ver
- usuarios.administrar
- reportes.financieros.ver
```

---

#### RF-004: Verificación de Permisos en Tiempo de Ejecución
**Prioridad**: P0 (Crítico)

**Descripción**: Middleware de autorización que valide permisos antes de ejecutar acciones sensibles.

**Criterios de Aceptación**:
- [ ] Método `Auth::checkPermission(string $slug): bool`
- [ ] Bloqueo automático con HTTP 403 si falla validación
- [ ] Cache de permisos por sesión (invalidar al cambiar rol)
- [ ] Rate limiting para prevenir brute force de permisos
- [ ] Compatibilidad con "Least Privilege Principle"

---

### 3.3 Módulo: Sistema POS Transaccional

#### RF-005: Procesamiento Atómico de Venta
**Prioridad**: P0 (Crítico)

**Descripción**: Registrar venta con actualización de inventario en transacción ACID.

**Criterios de Aceptación**:
- [ ] Operaciones en bloque: INSERT venta, UPDATE stock, INSERT log
- [ ] Rollback automático si cualquier operación falla
- [ ] Validación de stock disponible antes de commit
- [ ] Generación de comprobante con número secuencial único
- [ ] Tiempo de respuesta < 200ms para venta con 10 ítems

**Flujo Técnico**:
```php
try {
    $pdo->beginTransaction();
    // 1. Registrar venta
    // 2. Decrementar stock
    // 3. Registrar pago
    // 4. Generar comprobante
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    // Log + notificación
}
```

---

#### RF-006: Gestión de Métodos de Pago
**Prioridad**: P1 (Alto)

**Descripción**: Soportar múltiples formas de pago en una sola transacción (efectivo, tarjeta, transferencia).

**Criterios de Aceptación**:
- [ ] Permitir split payment (ej: 50% efectivo + 50% tarjeta)
- [ ] Validar que suma de pagos = total de venta
- [ ] Integración con pasarelas de pago (Stripe, PayPal) vía plugins
- [ ] Reconciliación automática de pagos al cierre de caja

---

### 3.4 Módulo: Sistema de Plugins

#### RF-007: Carga Prioritaria de Archivos de Plugins
**Prioridad**: P0 (Crítico)

**Descripción**: Si un plugin activo contiene un archivo con ruta idéntica al core, cargarlo preferencialmente.

**Criterios de Aceptación**:
- [ ] Escanear `/plugins/{nombre}/` antes de `/core/`
- [ ] Respetar estructura MVC: controller/, model/, vistas/
- [ ] Cache de rutas de archivos para rendimiento
- [ ] Deshabilitar plugin defectuoso sin afectar al core
- [ ] Logs de carga: qué archivo se cargó de dónde

**Ejemplo**:
```
Core: /core/vistas/Venta.php
Plugin: /plugins/pos_avanzado/vistas/Venta.php
→ Sistema carga el del plugin
```

---

#### RF-008: Sistema de Hooks y Eventos
**Prioridad**: P1 (Alto)

**Descripción**: Puntos de extensión donde plugins pueden inyectar lógica sin modificar el core.

**Criterios de Aceptación**:
- [ ] Hooks en eventos clave: `before_sale`, `after_sale`, `before_stock_update`
- [ ] Registro de callbacks vía `registerHook(string $event, callable $callback)`
- [ ] Ejecución secuencial de hooks con manejo de errores
- [ ] Posibilidad de cancelar operación desde hook (ej: validaciones custom)

**Hooks Iniciales**:
```
- init_app
- before_authentication
- after_authentication
- before_sale_creation
- after_sale_creation
- before_stock_update
- after_stock_update
```

---

## 4. Requisitos No Funcionales

### 4.1 Seguridad (NFR-SEC)

#### NFR-SEC-001: Inmunidad a SQL Injection
- **Especificación**: Uso obligatorio de PDO con prepared statements
- **Validación**: Penetration testing con SQLMap
- **KPI**: 0 vulnerabilidades de inyección SQL en auditoría

#### NFR-SEC-002: Aislamiento de Datos
- **Especificación**: Imposibilidad de acceso cross-tenant
- **Validación**: Pruebas de fuga de datos con usuarios maliciosos simulados
- **KPI**: 0 casos de acceso no autorizado a esquema de otro tenant

#### NFR-SEC-003: Encriptación de Datos Sensibles
- **Especificación**: Contraseñas con bcrypt (cost ≥ 12), datos PII encriptados en reposo
- **Estándar**: OWASP Password Storage Cheat Sheet

---

### 4.2 Rendimiento (NFR-PERF)

#### NFR-PERF-001: Tiempo de Respuesta
- **Objetivo**: 
  - P95 < 300ms para operaciones CRUD
  - P95 < 500ms para reportes complejos
- **Herramienta**: New Relic / Blackfire.io

#### NFR-PERF-002: Concurrencia
- **Objetivo**: Soportar 100 usuarios concurrentes por tenant sin degradación
- **Prueba**: Load testing con JMeter (escenario: 50 ventas simultáneas)

#### NFR-PERF-003: Escalabilidad Horizontal
- **Objetivo**: Soportar 10,000 tenants en cluster de 5 servidores
- **Arquitectura**: Load balancer + MySQL read replicas

---

### 4.3 Mantenibilidad (NFR-MAINT)

#### NFR-MAINT-001: Cobertura de Código
- **Objetivo**: ≥ 80% code coverage en core con PHPUnit
- **CI/CD**: Bloqueo de merge si coverage < 75%

#### NFR-MAINT-002: Documentación de APIs
- **Estándar**: OpenAPI 3.0 para todas las APIs REST
- **Generación**: Automática desde anotaciones PHPDoc

#### NFR-MAINT-003: Inmutabilidad del Core
- **Regla**: Prohibido modificar `/core/` en instalaciones de producción
- **Validación**: Hash checking en cada deploy + alertas si cambia

---

### 4.4 Disponibilidad (NFR-AVAIL)

#### NFR-AVAIL-001: Uptime
- **SLA**: 99.9% uptime (máximo 43 minutos downtime/mes)
- **Monitoreo**: Pingdom + alertas a equipo DevOps

#### NFR-AVAIL-002: Recuperación de Desastres
- **RTO**: < 4 horas (Recovery Time Objective)
- **RPO**: < 1 hora (Recovery Point Objective - pérdida de datos)
- **Backup**: Automatizado diario + point-in-time recovery

---

## 5. Especificaciones Técnicas

### 5.1 Stack Tecnológico (Obligatorio)

| Capa | Tecnología | Versión Mínima | Justificación |
|------|------------|----------------|---------------|
| **Backend** | PHP | 8.2+ | Enums, readonly properties, tipos estrictos |
| **Base de Datos** | MySQL/MariaDB | 8.0+/10.5+ | Schemas independientes, ACID compliance |
| **Capa de Acceso** | PHP PDO | Nativa | Security firewall anti-injection |
| **Frontend** | Bootstrap | 5.3.3+ | Responsividad y ecosistema coherente |
| **Visualización** | Chart.js | 4.0+ | Dashboards interactivos |
| **Servidor Web** | Nginx/Apache | Latest | Soporte PHP-FPM |

### 5.2 Arquitectura de Directorios

```
/aura
├── /core                    # Núcleo inmutable
│   ├── /controllers         # Lógica de negocio
│   ├── /models             # Capa de datos (PDO)
│   ├── /vistas             # Plantillas Bootstrap
│   ├── /lib                # Utilidades (SchemaManager, Auth, PluginLoader)
│   └── /config             # Configuración base
├── /plugins                # Extensiones de terceros
│   └── /{nombre_plugin}
│       ├── /controllers
│       ├── /models
│       └── /vistas
├── /public                 # Punto de entrada web
│   ├── index.php
│   ├── /assets             # CSS, JS, imágenes
│   └── .htaccess
├── /storage                # Datos persistentes
│   ├── /logs
│   ├── /uploads
│   └── /cache
└── /tests                  # Suite de pruebas PHPUnit
```

### 5.3 Convenciones de Código

#### Estándar PSR-12
- Indentación: 4 espacios (no tabs)
- Encoding: UTF-8 sin BOM
- Apertura de llaves: Misma línea para métodos y clases
- Nomenclatura: PascalCase para clases, camelCase para métodos

#### Tipado Estricto
```php
declare(strict_types=1);

class VentaModel {
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $tenantId
    ) {}
    
    public function registrarVenta(array $items): int {
        // Typed return y parámetros
    }
}
```

---

## 6. Roadmap de Desarrollo

### Fase I: Núcleo Blindado (12 semanas) - **EN DESARROLLO**

#### Semanas 1-4: Fundamentos
- [x] Diseño arquitectónico completado
- [ ] Estructura de directorios /core y /plugins
- [ ] SchemaManager con switch dinámico
- [ ] Sistema de autenticación base

#### Semanas 5-8: Control de Acceso
- [ ] RBAC con Enums (ADMIN, SELLER, SPECIAL)
- [ ] Middleware de autorización
- [ ] Dashboard de administración de usuarios
- [ ] Logs de auditoría

#### Semanas 9-12: Transacciones POS
- [ ] SalesController con ACID compliance
- [ ] Gestión de inventario transaccional
- [ ] Múltiples métodos de pago
- [ ] Testing de carga (100 usuarios concurrentes)

---

### Fase II: Ecosistema de Plugins (16 semanas)

#### Semanas 13-16: Sistema de Hooks
- [ ] Event dispatcher con prioridades
- [ ] 20 hooks estratégicos en core
- [ ] Documentación de API de hooks
- [ ] Plugin de ejemplo: "Dashboard Personalizado"

#### Semanas 17-24: Marketplace
- [ ] Portal de descubrimiento de plugins
- [ ] Sistema de versionado semántico
- [ ] Sandbox de pruebas para desarrolladores
- [ ] Proceso de aprobación de plugins

#### Semanas 25-28: Herramientas de Desarrollo
- [ ] CLI de scaffolding de plugins
- [ ] Generador de boilerplate MVC
- [ ] Debugger para hooks
- [ ] SDK con ejemplos completos

---

### Fase III: Optimización y Escala (12 semanas)

#### Semanas 29-36: Performance
- [ ] Cache de queries con Redis
- [ ] Optimización de índices MySQL
- [ ] CDN para assets estáticos
- [ ] Load balancing multi-región

#### Semanas 37-40: Plugins Verticales
- [ ] Plugin de facturación electrónica (LATAM)
- [ ] Plugin de nómina
- [ ] Plugin de CRM avanzado
- [ ] Plugin de BI con Chart.js

---

## 7. Criterios de Éxito (KPIs)

### Métricas de Negocio
- **Adopción**: 100 tenants activos en mes 6
- **Retención**: Churn rate < 5% mensual
- **Ecosistema**: 10 plugins publicados en marketplace en mes 12
- **Revenue**: $50K ARR (Annual Recurring Revenue) en año 1

### Métricas Técnicas
- **Uptime**: 99.9% en producción
- **Performance**: P95 response time < 300ms
- **Seguridad**: 0 vulnerabilidades críticas en auditoría anual
- **Code Quality**: Maintainability Index > 75 (CodeClimate)

### Métricas de Producto
- **NPS**: > 50 (promotores netos)
- **Time to First Sale**: < 15 minutos desde signup
- **Plugin Activation Rate**: > 60% de tenants usan ≥ 1 plugin

---

## 8. Riesgos y Mitigaciones

### Riesgo Técnico 1: Complejidad del Multi-Tenancy
**Probabilidad**: Media | **Impacto**: Alto

**Mitigación**:
- PoC (Proof of Concept) de SchemaManager en semana 1
- Load testing continuo desde semana 6
- Consultoría con DBA experto en multi-tenancy

---

### Riesgo Técnico 2: Incompatibilidad de Plugins
**Probabilidad**: Alta | **Impacto**: Medio

**Mitigación**:
- Sandbox aislado para pruebas de plugins
- Proceso de aprobación con checklist de seguridad
- Versionado semántico estricto en APIs

---

### Riesgo de Negocio 1: Adopción Lenta del Marketplace
**Probabilidad**: Media | **Impacto**: Alto

**Mitigación**:
- Desarrollo interno de 3 plugins "killer" en Fase II
- Programa de incentivos para early adopters (desarrolladores)
- Documentación exhaustiva + tutoriales en video

---

## 9. Dependencias y Supuestos

### Dependencias Externas
- **Hosting**: Servidor VPS con ≥ 8GB RAM, 4 vCPUs (Estimado: $100/mes)
- **SSL**: Certificado wildcard para subdominios (Let's Encrypt gratuito)
- **Email**: SendGrid/Mailgun para transaccionales (500 emails/día gratis)

### Supuestos de Negocio
- Mercado objetivo dispuesto a migrar de ERPs legacy
- Desarrolladores interesados en crear plugins (validar con encuestas)
- Pricing competitivo vs. SAP, Odoo, Zoho (benchmark en progreso)

---

## 10. Apéndices

### A. Glosario de Términos

- **Tenant (Inquilino)**: Cliente de la plataforma con esquema de base de datos aislado
- **Schema**: Namespace de MySQL que actúa como base de datos lógica
- **Hook**: Punto de extensión donde plugins pueden inyectar código
- **ACID**: Atomicidad, Consistencia, Aislamiento, Durabilidad (propiedades transaccionales)
- **RBAC**: Role-Based Access Control (control de acceso basado en roles)

### B. Referencias

- [Informe de Diseño Arquitectónico](Informe%20de%20Diseño%20Arquitectónico.md)
- [Informe de Implementación](Informe%20de%20Implementación.md)
- [Mandato de Arquitectura](Mandato%20de%20Arquitectura.md)
- OWASP Top 10: https://owasp.org/www-project-top-ten/
- PSR-12 Coding Standard: https://www.php-fig.org/psr/psr-12/

---

**Aprobación del PRD**:
- [ ] Product Owner: ___________________ Fecha: __________
- [ ] Solutions Architect: _______________ Fecha: __________
- [ ] Lead Developer: ___________________ Fecha: __________

---

*Última actualización: 2 de febrero de 2026*
*Versión del Documento: 1.0*
