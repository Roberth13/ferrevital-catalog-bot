import urllib.request
import json

with open('.env', 'r', encoding='utf-8') as f:
    for line in f:
        if line.startswith('GEMINI_API_KEY='):
            api_key = line.split('=', 1)[1].strip().strip('"').strip("'")
            break

url = f'https://generativelanguage.googleapis.com/v1beta/models?key={api_key}'
try:
    with urllib.request.urlopen(url, timeout=15) as resp:
        data = json.loads(resp.read().decode('utf-8'))
        flash_models = [m['name'] for m in data.get('models', []) if 'flash' in m['name']]
        print("Available Flash Models:", flash_models)
except Exception as e:
    print(f"Error: {e}")

