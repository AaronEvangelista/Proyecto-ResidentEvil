import requests
import os

# CONFIGURACIÓN
API_KEY = "HHGN5fT1LJTygJOPW4K7VLEdI8iPPGXEwgXW7msa"
BASE_URL = "https://freesound.org/apiv2"

# Usamos la ruta absoluta para evitar confusiones de dónde se guarda el archivo
# Esto busca la carpeta 'sounds' un nivel arriba de donde esté este script
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CARPETA_DESTINO = os.path.join(BASE_DIR, "..", "sounds")

# Crear la carpeta si no existe
if not os.path.exists(CARPETA_DESTINO):
    os.makedirs(CARPETA_DESTINO)
    print(f"Carpeta creada en: {CARPETA_DESTINO}")

def buscar_y_descargar(termino_busqueda, nombre_archivo):
    print(f"\n--- Iniciando búsqueda para: {termino_busqueda} ---")
    
    params = {
        "query": termino_busqueda,
        "token": API_KEY,
        "fields": "id,name,previews",
        "filter": "duration:[1 TO 15]", # Ampliado a 15 seg por si acaso
        "sort": "rating_desc"
    }

    try:
        response = requests.get(f"{BASE_URL}/search/text/", params=params)
        
        if response.status_code == 200:
            resultados = response.json().get('results', [])
            
            if resultados:
                sonido = resultados[0]
                # IMPORTANTE: Asegúrate de que el campo coincida con el nombre de la API (preview-hq-mp3)
                url_audio = sonido['previews']['preview-hq-mp3']
                
                print(f"Encontrado: '{sonido['name']}'")
                print(f"Descargando de: {url_audio}")

                archivo_response = requests.get(url_audio)
                
                # Forzamos extensión .mp3 y normalizamos la ruta
                nombre_final = f"{nombre_archivo}.mp3"
                ruta_final = os.path.normpath(os.path.join(CARPETA_DESTINO, nombre_final))
                
                with open(ruta_final, 'wb') as f:
                    f.write(archivo_response.content)
                
                print(f"¡Éxito! Archivo guardado físicamente en:\n{ruta_final}")
            else:
                print("No se encontraron resultados para esa búsqueda.")
        else:
            print(f"Error en la API (Status {response.status_code}): {response.text}")
            
    except Exception as e:
        print(f"Ocurrió un error inesperado: {e}")

# --- EJECUCIÓN ---
if __name__ == "__main__":
    buscar_y_descargar("creepy ambience horror", "ambiente_principal")
    buscar_y_descargar("zombie groan", "voz_zombie")
    buscar_y_descargar("door creak", "abrir_puerta")