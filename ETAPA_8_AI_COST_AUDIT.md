# ETAPA 8 — AUDITORÍA DE TOKENS, CONSUMO Y COSTOS DE IA
## Proyecto: Ferrevital Catalog Bot

---

## 1. Estado
**APROBADO**

---

## 2. Configuración IA Detectada en Producción
A partir de la auditoría estática del código en `config/services.php`, `GeminiAiDriver.php`, `AiCatalogParser.php` y `ParseAiCatalogChunkJob.php`:

| Parámetro | Valor Configurado | Ubicación |
| :--- | :--- | :--- |
| **Proveedor** | `gemini` (Google AI) | `config/services.php:47` |
| **Modelo** | `gemini-3.6-flash` (Alias / gemini-1.5-flash / 2.0-flash) | `config/services.php:53` |
| **Prompt Version** | `v1` | `config/services.php:48` |
| **Parser Version** | `v1` | `config/services.php:50` |
| **Temperatura** | `0.0` (Determinístico, sin creatividad) | `GeminiAiDriver.php:57` |
| **Response Format** | `application/json` con `responseSchema` estructurado (8 campos) | `GeminiAiDriver.php:58-79` |
| **Max Chunk Size** | `50,000` caracteres (cada página individual se envía como 1 chunk) | `AiCatalogParser.php:20` |
| **Timeout HTTP** | `120` segundos | `GeminiAiDriver.php:83` |
| **Intentos / Retries** | `6` intentos con backoff exponencial `[5, 10, 20, 40, 80, 160]` s | `ParseAiCatalogChunkJob.php:27,46` |
| **Triggers de Retry** | Errores 429 (Cuotas/Rate limit), 500–599 (Server) y fallos de red | `GeminiAiDriver.php:88-105` |

---

## 3. Precios de Referencia (Google Gemini Flash)
*Fuente oficial: Google AI Studio / Google Cloud Vertex AI Pricing (Precios vigentes para texto $\le 128\text{k}$ tokens):*
- **Costo de Entrada (Input):** **$0.075 USD por 1 millón de tokens** ($0.000075 USD / 1k tokens).
- **Costo de Salida (Output):** **$0.300 USD por 1 millón de tokens** ($0.000300 USD / 1k tokens).
- **Supuesto de Tokenización (ESTIMADO):** $1\text{ token} \approx 3.8\text{ caracteres}$ (promedio empírico para texto técnico mixto en español con JSON template).

---

## 4. Resultados Dong Cheng (36 Páginas Reales OCR)
- **Total páginas:** 36
- **Páginas Determinísticas (`SUFFICIENT`):** 32 (88.89%)
- **Páginas Informativas / Portada (`NO_PRODUCT_PAGE`):** 1 (2.78%)
- **Páginas Fallback IA (`INSUFFICIENT`):** 3 (8.33%) $\rightarrow$ Páginas 8, 14 y 15.
- **Chunks de IA generados:** 3 chunks individuales.

### Detalle por Página IA:
| Página | Chars Raw | Chars Prompt | Tokens Input (Est.) | Tokens Output (Est.) | Costo Input | Costo Output | Costo Total (USD) |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Pág 8** | 868 | 1,770 | 466 | 240 (2 prods) | $0.000035 | $0.000072 | **$0.000107** |
| **Pág 14** | 820 | 1,722 | 454 | 240 (2 prods) | $0.000034 | $0.000072 | **$0.000106** |
| **Pág 15** | 1,294 | 2,196 | 578 | 240 (2 prods) | $0.000043 | $0.000072 | **$0.000115** |
| **TOTAL** | **2,982** | **5,688** | **1,498** | **720** | **$0.000112** | **$0.000216** | **$0.000328 USD** |

- **Costo Promedio por Página IA:** **$0.000109 USD**
- **Costo Total para Dong Cheng (36 páginas):** **$0.000328 USD** (tercera parte de un centavo de dólar).

---

## 5. Resultados Jadever (295 Páginas Reales Texto)
- **Total páginas:** 295
- **Páginas Determinísticas (`SUFFICIENT`):** 284 (96.27%)
- **Páginas Informativas / Portada (`NO_PRODUCT_PAGE`):** 3 (1.02%)
- **Páginas Fallback IA (`INSUFFICIENT`):** 8 (2.71%) $\rightarrow$ Páginas 44, 53, 63, 196, 205, 214, 215 y 294.
- **Chunks de IA generados:** 8 chunks individuales.

### Detalle por Página IA:
| Página | Chars Raw | Chars Prompt | Tokens Input (Est.) | Tokens Output (Est.) | Costo Input | Costo Output | Costo Total (USD) |
| :---: | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| **Pág 44** | 3,862 | 4,764 | 1,254 | 720 (6 prods) | $0.000094 | $0.000216 | **$0.000310** |
| **Pág 53** | 3,432 | 4,334 | 1,141 | 720 (6 prods) | $0.000086 | $0.000216 | **$0.000302** |
| **Pág 63** | 888 | 1,790 | 472 | 720 (6 prods) | $0.000035 | $0.000216 | **$0.000251** |
| **Pág 196** | 936 | 1,838 | 484 | 720 (6 prods) | $0.000036 | $0.000216 | **$0.000252** |
| **Pág 205** | 2,438 | 3,340 | 879 | 720 (6 prods) | $0.000066 | $0.000216 | **$0.000282** |
| **Pág 214** | 2,998 | 3,900 | 1,027 | 720 (6 prods) | $0.000077 | $0.000216 | **$0.000293** |
| **Pág 215** | 3,513 | 4,415 | 1,162 | 720 (6 prods) | $0.000087 | $0.000216 | **$0.000303** |
| **Pág 294** | 180 | 1,082 | 285 | 720 (6 prods) | $0.000021 | $0.000216 | **$0.000237** |
| **TOTAL** | **18,247** | **25,463** | **6,704** | **5,760** | **$0.000503** | **$0.001728** | **$0.002231 USD** |

- **Costo Promedio por Página IA:** **$0.000279 USD**
- **Costo Total para Jadever (295 páginas):** **$0.002231 USD** (aprox. 1/5 de centavo de dólar).

---

## 6. Comparación Consolidada

| Métrica | Dong Cheng (OCR) | Jadever (Texto Nativo) |
| :--- | :---: | :---: |
| **Total Páginas** | 36 | 295 |
| **Páginas Determinísticas (`SUFFICIENT`)** | 32 (88.89%) | 284 (96.27%) |
| **Páginas Informativas (`NO_PRODUCT_PAGE`)** | 1 (2.78%) | 3 (1.02%) |
| **Páginas Fallback IA (`INSUFFICIENT`)** | 3 (8.33%) | 8 (2.71%) |
| **Chunks IA Despachados** | 3 | 8 |
| **Input Tokens (Estimados)** | 1,498 | 6,704 |
| **Output Tokens (Estimados)** | 720 | 5,760 |
| **Costo Input (USD)** | $0.000112 | $0.000503 |
| **Costo Output (USD)** | $0.000216 | $0.001728 |
| **Costo Total Catálogo (USD)** | **$0.000328** | **$0.002231** |
| **Costo si NO hubiese Estrategia Híbrida (100% IA)** | ~$0.0042 USD | ~$0.0764 USD |
| **Reducción de llamadas a IA** | **91.67%** | **97.29%** |

---

## 7. Proyecciones de Escalabilidad
Basado en el costo promedio mixto por página IA ($0.000194 USD / pág IA, con 678 tokens input y 480 tokens output por página):

| Volumen de Catálogo | Escenario Optimista (Ratio IA 2.71%) | Escenario Conservador (Ratio IA 8.33%) | Escenario Sin Híbrido (100% IA) |
| :---: | :---: | :---: | :---: |
| **100 Páginas** | 3 págs IA $\rightarrow$ **$0.0006 USD** | 9 págs IA $\rightarrow$ **$0.0018 USD** | 100 págs IA $\rightarrow$ $0.0195 USD |
| **300 Páginas** | 9 págs IA $\rightarrow$ **$0.0018 USD** | 25 págs IA $\rightarrow$ **$0.0049 USD** | 300 págs IA $\rightarrow$ $0.0585 USD |
| **500 Páginas** | 14 págs IA $\rightarrow$ **$0.0027 USD** | 42 págs IA $\rightarrow$ **$0.0082 USD** | 500 págs IA $\rightarrow$ $0.0975 USD |
| **1,000 Páginas** | 28 págs IA $\rightarrow$ **$0.0055 USD** | 84 págs IA $\rightarrow$ **$0.0164 USD** | 1,000 págs IA $\rightarrow$ $0.1950 USD |

---

## 8. Impacto de Retries

| Escenario de Retries | Dong Cheng (3 págs IA) | Jadever (8 págs IA) |
| :--- | :---: | :---: |
| **0 Retries (Nominal - Éxito en 1er intento)** | 3 llamadas $\rightarrow$ **$0.000328 USD** | 8 llamadas $\rightarrow$ **$0.002231 USD** |
| **1 Retry Promedio (2 intentos por página)** | 6 llamadas $\rightarrow$ **$0.000657 USD** | 16 llamadas $\rightarrow$ **$0.004462 USD** |
| **Peor Caso Configurado (6 intentos por página)** | 18 llamadas $\rightarrow$ **$0.001970 USD** | 48 llamadas $\rightarrow$ **$0.013386 USD** |

*Incluso en el peor caso teórico donde todas las páginas agotan sus 6 reintentos, el costo total de un catálogo de 295 páginas no supera $0.014 USD.*

---

## 9. Análisis de Riesgos

| Factor de Control | Estado | Evaluación / Riesgo |
| :--- | :---: | :--- |
| **Límite de Chunk por Página** | `IMPLEMENTADO` | Límite de 50,000 caracteres garantiza 1 chunk por página. |
| **Bifurcación Mutuamente Excluyente** | `IMPLEMENTADO` | Evaluador impide que una página sea procesada por determinístico e IA a la vez. |
| **Idempotencia de Persistencia** | `IMPLEMENTADO` | `upsert` por `(supplier_id, codigo)` impide duplicaciones en reintentos. |
| **Límite de Retries** | `IMPLEMENTADO` | Máximo 6 intentos con backoff exponencial. |
| **Circuit Breaker / Cost Limit** | `NO IMPLEMENTADO` | Actualmente no hay corte de emergencia si se suben 10,000 páginas de golpe. Se puede configurar en Google Cloud Quotas. |
| **Límite de Requests / Minuto (RPM)** | `NO IMPLEMENTADO` | Mitigado por el backoff de Laravel Queue ante errores 429. |

---

## 10. Conclusión
> **¿El fallback IA de esta arquitectura es económicamente viable?**
>
> **SÍ, TOTALMENTE VIABLE.**
> 
> Los datos demuestran que:
> 1. Gracias a la estrategia híbrida, el **96.3%** de Jadever y el **88.9%** de Dong Cheng se resuelven a costo **$0.00**.
> 2. Procesar el catálogo completo de 295 páginas de Jadever cuesta solo **$0.0022 USD** (menos de medio centavo de dólar).
> 3. Procesar el catálogo de Dong Cheng de 36 páginas OCR cuesta solo **$0.00033 USD**.
> 4. Un catálogo masivo de 1,000 páginas tendría un costo estimado entre **$0.005 USD y $0.016 USD**.

---

## 11. Datos Estructurados Generados
Se generó el artefacto JSON completo de auditoría en:
`storage/app/private/audits/etapa8_ai_cost_audit.json`
*(Sin credenciales, API keys ni información confidencial).*
