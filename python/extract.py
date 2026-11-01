#!/usr/bin/env python3
import argparse
import json
import os
import re
import sys
import tempfile
import time
from typing import List, Optional, Tuple, Dict, Any

from pydantic import BaseModel, Field

# ---------------------------------------------------------------- esquema ---
class Producto(BaseModel):
    codigo: Optional[str] = Field(None, description="Codigo/modelo exacto impreso, ej. DCPL208, DZJ02-13. null si no aparece.")
    variante: Optional[str] = Field(None, description="SOLO la variante/tipo que el catalogo imprime como 'TIPO X', ej. 'TIPO EM', 'TIPO ADM', 'TIPO E'. No es voltaje, potencia ni medida (eso va en nombre o descripcion). null si no hay.")
    nombre: str = Field(..., description="Nombre comercial del producto, sin repetir el codigo/modelo (que va en 'codigo'), ej. 'TALADRO DE IMPACTO ELECTRICO 500W (110 V)'.")
    precio_divisa: Optional[float] = Field(None, description="Precio principal en dolares (numero tras 'Ref.', 'REF:', '$' o 'USD'). Coma decimal -> punto. null si no aparece.")
    otros_precios: Optional[str] = Field(None, description="Cualquier otra columna de precio (BCV, mayor, detal, PVP, con IVA, etc.) como texto 'etiqueta: valor; etiqueta: valor', conservando la etiqueta tal como aparece. null si no hay.")
    descripcion_tecnica: Optional[str] = Field(None, description="Especificaciones tecnicas y caracteristicas, una por linea, en el texto original. No inventes ni resumas valores.")
    incluye: Optional[str] = Field(None, description="Contenido de la seccion 'QUE INCLUYE', tal cual. null si no hay.")
    garantia: Optional[str] = Field(None, description="Garantia impresa en la pagina o catalogo, ej. '1 Año de Garantia'. null si no aparece.")
    pagina: int = Field(..., description="Numero de pagina del PDF original donde aparece el producto.")
    notas: Optional[str] = Field(None, description="Ambiguedades o inconsistencias visibles (texto ilegible, datos contradictorios). null si nada.")


class Resultado(BaseModel):
    productos: List[Producto]


PROMPT_GENERICO = """Eres un extractor de datos de catálogos y listas de precios de herramientas y ferretería. Recibes páginas de un documento, cada una como texto o como imagen, etiquetadas con su número de página.

Extrae CADA producto que tenga precio o ficha técnica. Reglas:
- Un registro por cada combinación código + variante. Si el mismo modelo aparece con variantes o presentaciones distintas (tipo, color, voltaje, contenido del kit) y cada una tiene su propio precio, devuelve un registro por cada una.
- Copia los valores tal como están impresos; no corrijas ni conviertas unidades.
- precio_divisa es el precio principal en dólares (suele venir como Ref., REF:, $ o USD). Cualquier otra columna de precio (BCV, mayor, detal, PVP, con IVA, etc.) va en otros_precios como texto 'etiqueta: valor; etiqueta: valor', conservando la etiqueta tal como aparece. No calcules ni conviertas precios.
- Si un campo NO está impreso, devuelve null. Nunca lo deduzcas ni lo inventes.
- La garantía puede ser un aviso general de la página o del catálogo; aplícala a los productos a los que corresponda.
- "incluye" es solo lo que el documento lista como contenido o accesorios incluidos. Las especificaciones van en descripcion_tecnica.
- Une en una sola línea las frases que el diseño parte en varios renglones; usa salto de línea solo entre viñetas.
- Si ves datos contradictorios o texto ilegible, descríbelo brevemente en notas.
- Ignora portadas, índices, políticas y páginas sin productos.
- Devuelve solo el JSON del esquema."""


def render_page_image(page, out_path: str, long_edge: int = 1568):
    try:
        import pymupdf as fitz
    except ImportError:
        import fitz

    zoom = long_edge / max(page.rect.width, page.rect.height)
    pix = page.get_pixmap(matrix=fitz.Matrix(zoom, zoom), alpha=False)
    try:
        pix.save(out_path, jpg_quality=85)
    except TypeError:
        pix.save(out_path)


def with_retry(fn, *args, tries=6):
    """Reintenta errores transitorios con espera creciente."""
    delay = 5
    for i in range(tries):
        try:
            return fn(*args)
        except Exception as e:
            msg = str(e)
            desc = f"{type(e).__name__} {msg}"
            transient = any(k in desc for k in (
                "503", "429", "500", "502", "504", "529", "UNAVAILABLE", "RESOURCE_EXHAUSTED",
                "overloaded", "RemoteProtocolError", "Server disconnected", "ConnectError",
                "ReadError", "Timeout", "timed out", "Connection"))
            if not transient or i == tries - 1:
                sys.stderr.write(f"Detalle del error ({type(e).__name__}): {msg[:700]}\n")
                raise
            if "PerDay" in msg or "per day" in msg.lower():
                sys.stderr.write("CUOTA DIARIA agotada. Reintentar no ayuda.\n")
                raise
            wait = delay
            m = re.search(r"retry\w*\D{0,12}(\d+(?:\.\d+)?)s", msg, re.I)
            if m:
                wait = max(delay, min(float(m.group(1)) + 1, 120))
            sys.stderr.write(f"Error transitorio -> reintento {i + 1}/{tries - 1} en {wait:.0f}s\n")
            time.sleep(wait)
            delay = min(delay * 2, 60)


def call_gemini_batch(model: str, items: List[Dict[str, Any]]) -> Tuple[dict, int, int]:
    from google import genai
    from google.genai import types

    api_key = os.environ.get("GEMINI_API_KEY")
    if not api_key:
        raise ValueError("GEMINI_API_KEY no configurada en las variables de entorno.")

    client = genai.Client(api_key=api_key)
    parts = []

    for item in items:
        pnum = item["num"]
        parts.append(f"--- Página {pnum} ---")
        if item["type"] == "text":
            parts.append(f"[Texto extraído de la Página {pnum}]:\n{item['content']}")
        else:
            with open(item["image_path"], "rb") as f:
                img_bytes = f.read()
            parts.append(types.Part.from_bytes(data=img_bytes, mime_type="image/jpeg"))

    parts.append(PROMPT_GENERICO)

    cfg = dict(
        response_mime_type="application/json",
        response_schema=Resultado,
        temperature=0
    )

    try:
        cfg["thinking_config"] = types.ThinkingConfig(thinking_budget=0)
        resp = client.models.generate_content(
            model=model,
            contents=parts,
            config=types.GenerateContentConfig(**cfg)
        )
    except Exception as e:
        s = str(e)
        if "thinking" not in s.lower() and "INVALID_ARGUMENT" not in s:
            raise
        cfg.pop("thinking_config", None)
        resp = client.models.generate_content(
            model=model,
            contents=parts,
            config=types.GenerateContentConfig(**cfg)
        )

    # Detectar corte de tokens
    finish_reason = None
    try:
        if resp.candidates and len(resp.candidates) > 0:
            finish_reason = getattr(resp.candidates[0], "finish_reason", None)
            if finish_reason:
                fr_str = str(finish_reason).upper()
                if "MAX_TOKENS" in fr_str or "LENGTH" in fr_str:
                    raise OverflowError("MAX_TOKENS_EXCEEDED")
    except OverflowError:
        raise
    except Exception:
        pass

    u = resp.usage_metadata
    tin = (u.prompt_token_count or 0) if u else 0
    thoughts = (getattr(u, "thoughts_token_count", 0) or 0) if u else 0
    tout = ((u.candidates_token_count or 0) + thoughts) if u else 0

    text_resp = resp.text or "{}"
    data = json.loads(text_resp)
    return data, tin, tout


def process_batch(model: str, batch: List[Dict[str, Any]]) -> Tuple[List[dict], int, int]:
    try:
        data, tin, tout = with_retry(call_gemini_batch, model, batch)
        return data.get("productos", []), tin, tout
    except OverflowError:
        if len(batch) > 1:
            mid = len(batch) // 2
            sys.stderr.write(f"Lote de {len(batch)} páginas cortado por MAX_TOKENS. Dividiendo a la mitad ({len(batch[:mid])} y {len(batch[mid:])})...\n")
            prods1, tin1, tout1 = process_batch(model, batch[:mid])
            time.sleep(2)
            prods2, tin2, tout2 = process_batch(model, batch[mid:])
            return prods1 + prods2, tin1 + tin2, tout1 + tout2
        else:
            raise


def main():
    parser = argparse.ArgumentParser(description="Extractor genérico de catálogos.")
    parser.add_argument("--pdf", required=True, help="Ruta del archivo PDF")
    parser.add_argument("--out", required=True, help="Ruta de salida JSON")
    parser.add_argument("--first", type=int, default=None, help="Página inicial (1-indexed)")
    parser.add_argument("--last", type=int, default=None, help="Página final (1-indexed)")
    parser.add_argument("--input", choices=["auto", "text", "image"], default="auto", help="Modo de entrada por página")

    args = parser.parse_args()

    if not os.path.exists(args.pdf):
        sys.stderr.write(f"Error: El archivo PDF '{args.pdf}' no existe.\n")
        sys.exit(1)

    model = os.environ.get("GEMINI_MODEL", "gemini-2.5-flash-lite")
    price_in = float(os.environ.get("GEMINI_PRICE_IN", "0.30"))
    price_out = float(os.environ.get("GEMINI_PRICE_OUT", "2.50"))

    try:
        import pymupdf as fitz
    except ImportError:
        import fitz

    try:
        doc = fitz.open(args.pdf)
    except Exception as e:
        sys.stderr.write(f"Error al abrir el PDF: {e}\n")
        sys.exit(1)

    total_doc_pages = len(doc)
    first_page = max(1, args.first) if args.first else 1
    last_page = min(total_doc_pages, args.last) if args.last else total_doc_pages

    if first_page > last_page:
        sys.stderr.write(f"Rango de páginas inválido: first ({first_page}) > last ({last_page})\n")
        sys.exit(1)

    tot_in = 0
    tot_out = 0
    all_raw_products = []
    advertencias = []
    processed_pages_count = last_page - first_page + 1

    count_image = 0
    count_text = 0

    with tempfile.TemporaryDirectory() as tmp_dir:
        prepared_pages = []
        for pno in range(first_page, last_page + 1):
            page = doc[pno - 1]
            extracted_text = (page.get_text() or "").strip()
            use_mode = args.input

            if use_mode == "auto":
                if len(extracted_text) >= 150:
                    use_mode = "text"
                else:
                    use_mode = "image"

            if use_mode == "text":
                count_text += 1
                prepared_pages.append({
                    "num": pno,
                    "type": "text",
                    "content": extracted_text,
                    "image_path": None
                })
            else:
                count_image += 1
                img_path = os.path.join(tmp_dir, f"page_{pno:04d}.jpg")
                render_page_image(page, img_path)
                prepared_pages.append({
                    "num": pno,
                    "type": "image",
                    "content": None,
                    "image_path": img_path
                })

        doc.close()

        # Lotes de 4 páginas
        batch_size = 4
        for i in range(0, len(prepared_pages), batch_size):
            batch = prepared_pages[i:i + batch_size]
            nums = [p["num"] for p in batch]
            sys.stderr.write(f"Procesando lote de páginas {nums}...\n")
            try:
                batch_products, tin, tout = process_batch(model, batch)
                tot_in += tin
                tot_out += tout
                all_raw_products.extend(batch_products)
            except Exception as e:
                sys.stderr.write(f"Error procesando lote {nums}: {e}\n")
                advertencias.append(f"Error en lote de páginas {nums}: {str(e)[:200]}")
                raise e

            if i + batch_size < len(prepared_pages):
                time.sleep(2)

    # Post-procesamiento
    # Regla 5: Si (codigo, variante) aparece mas de una vez, conserva el primero y marca revisar con "duplicado en el catálogo"
    # Marca revisar si falta codigo, si falta precio_divisa, o si el modelo devolvió notas.
    seen_keys = set()
    deduped_products = []

    for item in all_raw_products:
        if isinstance(item, BaseModel):
            item_dict = item.model_dump()
        else:
            item_dict = dict(item)

        codigo = item_dict.get("codigo")
        variante = item_dict.get("variante") or ""
        codigo_norm = (codigo or "").strip().upper()
        variante_norm = variante.strip().upper()

        revisar_motivos = []

        if not codigo:
            revisar_motivos.append("falta código")
        if item_dict.get("precio_divisa") is None:
            revisar_motivos.append("falta precio divisa")
        if item_dict.get("notas"):
            revisar_motivos.append(f"nota del modelo: {item_dict['notas']}")

        key = (codigo_norm, variante_norm)
        if codigo_norm and key in seen_keys:
            revisar_motivos.append("duplicado en el catálogo")
            # conservar el primero (no agregamos el duplicado como nuevo registro, o si ya se vio se ignora o se descarta según especificación: "conserva el primero")
            continue
        elif codigo_norm:
            seen_keys.add(key)

        clean_prod = {
            "codigo": codigo or "",
            "variante": variante,
            "nombre": item_dict.get("nombre") or "",
            "precio_divisa": item_dict.get("precio_divisa"),
            "otros_precios": item_dict.get("otros_precios"),
            "descripcion_tecnica": item_dict.get("descripcion_tecnica"),
            "incluye": item_dict.get("incluye"),
            "garantia": item_dict.get("garantia"),
            "pagina": item_dict.get("pagina"),
            "revisar": revisar_motivos
        }
        deduped_products.append(clean_prod)

    cost_usd = round((tot_in / 1e6 * price_in) + (tot_out / 1e6 * price_out), 4)

    output_data = {
        "productos": deduped_products,
        "paginas": processed_pages_count,
        "costo_usd": cost_usd,
        "tokens_in": tot_in,
        "tokens_out": tot_out,
        "advertencias": advertencias,
        "modo_paginas": {
            "texto": count_text,
            "imagen": count_image
        }
    }

    with open(args.out, "w", encoding="utf-8") as f:
        json.dump(output_data, f, ensure_ascii=False, indent=2)

    sys.stderr.write(f"Completado exitosamente: {len(deduped_products)} productos extraídos de {processed_pages_count} páginas. Costo: ${cost_usd:.4f}. Modo: {count_text} texto, {count_image} imagen.\n")
    sys.exit(0)


if __name__ == "__main__":
    main()

