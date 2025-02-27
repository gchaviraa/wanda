import requests
from config import WEATHER_API_KEY

def get_weather(city):
    """Fetches current weather for a given city in Spanish."""
    url = f"http://api.weatherapi.com/v1/current.json?key={WEATHER_API_KEY}&q={city}&lang=es"

    try:
        response = requests.get(url)
        response.raise_for_status()
        data = response.json()

        if "current" in data:
            return f"El clima en {city} es de {data['current']['temp_c']}°C con {data['current']['condition']['text']}."
        else:
            return "No se encontró información del clima de la ciudad especificada."

    except requests.exceptions.HTTPError as http_err:
        return f"Error HTTP: {http_err}"
    except requests.exceptions.ConnectionError as conn_err:
        return f"Error de conexión: {conn_err}"
    except requests.exceptions.Timeout as time_err:
        return f"Error de tiempo de espera: {time_err}"
    except requests.exceptions.RequestException as req_err:
        return f"Error en la solicitud: {req_err}"
    except KeyError:
        return "Error en la respuesta del API."

