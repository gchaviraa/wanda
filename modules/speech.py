import speech_recognition as sr
from gtts import gTTS
import tempfile
import os
import pygame

class SpeechModule:
    def __init__(self):
        """Inicializa el reconocedor de voz y el sistema de audio."""
        self.recognizer = sr.Recognizer()
        pygame.mixer.init()

    def listen(self):
        """Reconoce voz desde el micrófono y devuelve el texto."""
        with sr.Microphone() as source:
            print("Escuchando...")
            self.recognizer.adjust_for_ambient_noise(source)

            try:
                audio = self.recognizer.listen(source, timeout=5)
                text = self.recognizer.recognize_google(audio, language="es-ES")
                print(f"Dijiste: {text}")
                return text
            except sr.WaitTimeoutError:
                print("Tiempo de espera agotado. No se detectó voz.")
            except sr.UnknownValueError:
                print("No entendí lo que dijiste.")
            except sr.RequestError:
                print("Error al conectar con el servicio de reconocimiento de voz.")
        return ""

    def speak(self, text):
        """Convierte texto a voz usando gTTS y reproduce el audio con pygame."""
        try:
            tts = gTTS(text, lang="es")
            with tempfile.NamedTemporaryFile(delete=False, suffix=".mp3") as temp_file:
                tts.save(temp_file.name)

            pygame.mixer.music.load(temp_file.name)
            pygame.mixer.music.play()

            # Esperar hasta que termine de reproducirse
            while pygame.mixer.music.get_busy():
                pygame.time.Clock().tick(10)

            os.remove(temp_file.name)
        except Exception as e:
            print(f"Error en la síntesis de voz: {e}")
