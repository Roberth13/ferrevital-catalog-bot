import json
import urllib.request
import fitz

pdf_path = r'C:\Proyectos\Personales\PDFs\CATALOGO DE INGCO 27-5-2026..pdf'
doc = fitz.open(pdf_path)

# Extraer texto de 2 paginas (11 y 12)
sample_text = doc[10].get_text() + "\n" + doc[11].get_text()

with open('.env', 'r', encoding='utf-8') as f:
    for line in f:
        if line.startswith('GEMINI_API_KEY='):
            api_key = line.split('=', 1)[1].strip().strip('"').strip("'")
            break

prompt = f"""Extrae los productos como JSON:
{sample_text}
"""

payload = {
    "contents": [{"parts": [{"text": prompt}]}],
    "generationConfig": {
        "responseMimeType": "application/json",
        "temperature": 0.0,
        "thinkingConfig": {"thinkingBudget": 0}
    }
}

url = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={api_key}"

req = urllib.request.Request(
    url,
    data=json.dumps(payload).encode('utf-8'),
    headers={"Content-Type": "application/json"},
    method="POST"
)

try:
    with urllib.request.urlopen(req, timeout=30) as resp:
        data = json.loads(resp.read().decode('utf-8'))
        print("Usage Metadata:", json.dumps(data.get('usageMetadata', {}), indent=2))
        print("Response sample:", data['candidates'][0]['content']['parts'][0]['text'][:400])
except Exception as e:
    print(f"Error: {e}")
