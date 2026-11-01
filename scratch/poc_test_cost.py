import os
import json
import urllib.request
import fitz

# Extraer texto de paginas 10 a 14 (5 paginas reales con productos)
pdf_path = r'C:\Proyectos\Personales\PDFs\CATALOGO DE INGCO 27-5-2026..pdf'
doc = fitz.open(pdf_path)

pages_text = []
for pno in range(10, 15):
    txt = doc[pno].get_text()
    pages_text.append(f"--- PAGINA {pno+1} ---\n{txt}")

combined_text = "\n\n".join(pages_text)

api_key = None
with open('.env', 'r', encoding='utf-8') as f:
    for line in f:
        if line.startswith('GEMINI_API_KEY='):
            api_key = line.split('=', 1)[1].strip().strip('"').strip("'")
            break

if not api_key:
    print("No API Key found")
    exit(1)

prompt = f"""Extrae todos los productos de este catalogo.
Devuelve unicamente un objeto JSON valido con la clave "productos", donde cada elemento tenga:
- sku (codigo de producto)
- nombre (descripcion o titulo)
- ref_precio (precio de referencia numerico si existe, null si no)
- unidad_empaque (cantidad o null)

Texto del catalogo:
{combined_text}
"""

payload = {
    "contents": [{
        "parts": [{"text": prompt}]
    }],
    "generationConfig": {
        "responseMimeType": "application/json",
        "temperature": 0.0
    }
}

# Probamos con gemini-2.5-flash / gemini-1.5-flash
url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent?key={api_key}"

req = urllib.request.Request(
    url,
    data=json.dumps(payload).encode('utf-8'),
    headers={"Content-Type": "application/json"},
    method="POST"
)

try:
    with urllib.request.urlopen(req, timeout=30) as resp:
        data = json.loads(resp.read().decode('utf-8'))
        usage = data.get('usageMetadata', {})
        print("SUCCESS")
        print("Usage Metadata:", json.dumps(usage, indent=2))
        
        # Texto de salida
        text = data['candidates'][0]['content']['parts'][0]['text']
        parsed = json.loads(text)
        prods = parsed.get('productos', [])
        print(f"Total productos extraidos en 5 paginas: {len(prods)}")
        print("Muestra (primeros 3):", json.dumps(prods[:3], indent=2, ensure_ascii=False))
except urllib.error.HTTPError as e:
    print(f"HTTP Error {e.code}: {e.read().decode('utf-8')}")
except Exception as e:
    print(f"Error: {e}")