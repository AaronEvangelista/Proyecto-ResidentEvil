from flask import Flask, jsonify, request
from flask_cors import CORS
import requests
import random

app = Flask(__name__)
CORS(app)  # Permite que tu PHP/JS haga peticiones a este script

# --- CONFIGURACIÓN ---
API_KEY = "HHGN5fT1LJTygJOPW4K7VLEdI8iPPGXEwgXW7msa"
BASE_URL = "https://freesound.org/apiv2"

def buscar_sonido(query, duration_max=60):
    """Busca un sonido en Freesound y devuelve la URL del MP3"""
    params = {
        "query": query,
        "token": API_KEY,
        "filter": f"duration:[5 TO {duration_max}]",
        "fields": "id,name,previews",
        "sort": "rating_desc"
    }
    
    response = requests.get(f"{BASE_URL}/search/text/", params=params)
    data = response.json()
    
    if data.get('results'):
        # Elegimos uno al azar de los primeros 5 para que no sea siempre el mismo
        choice = random.choice(data['results'][:5])
        return choice['previews']['preview-hq-mp3']
    return None

@app.route('/api/sonido', methods=['GET'])
def obtener_sonido():
    # Podemos pasar el tipo de tensión por la URL: /api/sonido?tipo=boss
    tipo = request.args.get('tipo', 'ambient')
    
    queries = {
        "baja": "horror ambience drone dark",
        "media": "suspense tension strings creepy",
        "alta": "chase horror action fast",
        "boss": "monster roar industrial horror loud",
        "item": "metallic pickup click"
    }
    
    query = queries.get(tipo, queries["baja"])
    url_audio = buscar_sonido(query)
    
    if url_audio:
        return jsonify({"status": "success", "url": url_audio})
    return jsonify({"status": "error", "message": "No se encontró sonido"}), 404

if __name__ == '__main__':
    # El servidor correrá en http://127.0.0.1:5000
    app.run(debug=True, port=5000)