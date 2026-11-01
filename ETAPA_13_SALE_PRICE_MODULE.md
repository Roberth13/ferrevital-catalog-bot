# ETAPA 13 — MÓDULO "PRECIO DE VENTA"

## 1. Objetivo

Implementar una primera versión funcional y desacoplada del módulo **Precio de Venta** en `ferrevital-catalog-bot`, conectada directamente con los productos extraídos del catálogo, permitiendo al usuario seleccionar productos, formular precios comerciales mediante fórmulas matemáticas parametrizables, elegir la base de cálculo (**BS** o **DIVISA**), persistir los resultados con trazabilidad completa sin alterar los precios originales del proveedor y visualizarlos en el Explorador de Inventario y en las exportaciones a Excel.

---

## 2. Arquitectura del Módulo

El flujo opera siguiendo una separación estricta entre el costo bruto del proveedor y el precio comercial de venta:

```text
Catálogo PDF / Extracción
            ↓
Productos en BD (precio_bs / precio_divisa originales)
            ↓
Módulo "Precio de Venta" (/sale-prices)
            ↓
Selección de Fórmula (SalePriceFormulaRegistry)
            ↓
Selección de Base: BS / DIVISA
            ↓
Cálculo Transaccional (SalePriceCalculatorService)
            ↓
Persistencia:
    precio_venta_bs / precio_venta_divisa
    sale_price_formula
    sale_price_base
    sale_price_applied_at
            ↓
Explorador de Inventario (/products) y Exportación Excel
```

---

## 3. Modelo de Datos (Tabla `products`)

Se agregaron las siguientes columnas a la tabla `products` mediante la migración `2026_09_13_140000_add_sale_prices_to_products_table`:

| Campo | Tipo | Nullable | Descripción |
| :--- | :--- | :--- | :--- |
| `precio_bs` | `decimal(15, 2)` | SÍ | **Costo bruto original del proveedor en Bolívares (INMUTABLE)** |
| `precio_divisa` | `decimal(15, 2)` | SÍ | **Costo bruto original del proveedor en Dólares (INMUTABLE)** |
| `precio_venta_bs` | `decimal(15, 2)` | SÍ | **Precio de venta calculado sobre base BS** |
| `precio_venta_divisa` | `decimal(15, 2)` | SÍ | **Precio de venta calculado sobre base DIVISA** |
| `sale_price_formula` | `string` | SÍ | Identificador de la fórmula aplicada (ej. `percentage_margin`) |
| `sale_price_base` | `string(20)` | SÍ | Base monetaria utilizada (`bs` o `divisa`) |
| `sale_price_applied_at` | `timestamp` | SÍ | Fecha y hora de la última operación de cálculo |

---

## 4. Abstracción y Catálogo de Fórmulas

Se diseñó la interfaz `SalePriceFormulaInterface` y el registro extensible `SalePriceFormulaRegistry`.

### Fórmulas Implementadas (Técnicas / Provisionales)

1. **Recargo Porcentual sobre Costo (Markup)** — `percentage_markup`
   - *Fórmula:* $\text{precio\_venta} = \text{base} \times (1 + \frac{\text{porcentaje}}{100})$
   - *Validación:* $\text{porcentaje} \ge 0$.
2. **Margen de Ganancia Comercial** — `percentage_margin`
   - *Fórmula:* $\text{precio\_venta} = \frac{\text{base}}{1 - \frac{\text{margen}}{100}}$
   - *Validación:* $0 \le \text{margen} < 100$.
3. **Incremento Fijo sobre Costo** — `fixed_markup`
   - *Fórmula:* $\text{precio\_venta} = \text{base} + \text{monto}$
   - *Validación:* $\text{monto} \ge 0$.
4. **Factor Multiplicador Directo** — `direct_multiplier`
   - *Fórmula:* $\text{precio\_venta} = \text{base} \times \text{multiplicador}$
   - *Validación:* $\text{multiplicador} > 0$.

> [!NOTE]
> **Fórmulas Comerciales Definitivas:** Pendientes de especificación oficial por el negocio. La arquitectura permite registrar nuevas fórmulas comerciales implementando `SalePriceFormulaInterface` y registrándolas en `SalePriceFormulaRegistry` sin modificar el resto del sistema.

---

## 5. Bases de Cálculo y Aislamiento

- **Base DIVISA:** Toma el costo `precio_divisa`, calcula y guarda exclusivamente en `precio_venta_divisa`. `precio_divisa` y `precio_bs` permanecen intactos.
- **Base BS:** Toma el costo `precio_bs`, calcula y guarda exclusivamente en `precio_venta_bs`. `precio_bs` y `precio_divisa` permanecen intactos.
- **Protección contra nulos:** Si un producto no tiene precio en la base seleccionada, el servicio lo omite de forma segura sin abortar la transacción de los demás ítems y reporta el conteo de omitidos.

---

## 6. Integración con Explorador de BD y Excel

- **Explorador de Inventario (`/products`):** Visualiza 4 columnas diferenciadas:
  - `P. Prov. Divisa`
  - `P. Prov. Bs`
  - `P. Venta Divisa`
  - `P. Venta Bs`
  - Productos sin precio de venta muestran `-` sin arrojar errores.
- **Exportación Excel (`/products/export`):** Incluye `Precio Costo Divisa`, `Precio Costo Bs`, `Precio Venta Divisa`, `Precio Venta Bs`, `Fórmula Venta`, `Base Venta`.

---

## 7. Pruebas Automatizadas y Métricas

- **Tests unitarios:** `tests/Unit/SalePriceFormulaTest.php`
- **Tests de feature:** `tests/Feature/SalePriceCalculationTest.php` y `tests/Feature/ProductExplorerSalePriceTest.php`
- **PHPUnit Suite:**
  - **145 tests**
  - **2.242 assertions**
  - **0 failures, 0 errors (100% GREEN)**

---

## 8. Limitaciones Actuales y Próximos Pasos

1. **Fórmulas comerciales:** Actualmente se utilizan fórmulas matemáticas parametrizables; se adaptarán una vez que el negocio defina los márgenes o reglas impositivas oficiales.
2. **Conversión de Moneda:** No se realiza conversión automática BS $\leftrightarrow$ USD (ambas bases se formulan de forma independiente según el precio de costo existente).
3. **Historial:** Se conserva la trazabilidad de la última operación aplicada (`sale_price_formula`, `sale_price_base`, `sale_price_applied_at`). Si se requiere auditoría multi-versión histórica, podrá implementarse una tabla `sale_price_logs`.
