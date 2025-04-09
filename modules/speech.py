import speech_recognition as sr
from gtts import gTTS
from playsound import playsound
import tempfile
import os

class SpeechModule:
    def __init__(self):
        """Inicializa el reconocedor de voz."""
        self.recognizer = sr.Recognizer()

    def listen(self):
        """Reconoce voz desde el micrófono y devuelve el texto."""
        with sr.Microphone() as source:
            print("Escuchando...")
            self.recognizer.adjust_for_ambient_noise(source)  # Reducir ruido de fondo

            try:
                audio = self.recognizer.listen(source, timeout=5)  # Tiempo máximo de espera: 5 segundos
                text = self.recognizer.recognize_google(audio, language="es-ES")  # Voz a texto
                print(f"Dijiste: {text}")
                return text
            except sr.WaitTimeoutError:
                print("Tiempo de espera agotado. No se detectó voz.")
            except sr.UnknownValueError:
                print("No entendí lo que dijiste.")
            except sr.RequestError:
                print("Error al conectar con el servicio de reconocimiento de voz.")

        return ""  # Devuelve texto vacío si falla

    def speak(self, text):
        """Convierte texto a voz usando gTTS y reproduce el audio."""
        try:
            tts = gTTS(text, lang="es")
            with tempfile.NamedTemporaryFile(delete=False, suffix=".mp3") as temp_file:
                tts.save(temp_file.name)
                playsound(temp_file.name)
            os.remove(temp_file.name)
        except Exception as e:
            print(f"Error en la síntesis de voz: {e}")
