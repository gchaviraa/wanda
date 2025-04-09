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
        temp_file = None
        try:
            tts = gTTS(text, lang="es")
            temp_file = tempfile.NamedTemporaryFile(delete=False, suffix=".mp3")
            tts.save(temp_file.name)
            temp_file.close()

            pygame.mixer.music.load(temp_file.name)
            pygame.mixer.music.play()

            while pygame.mixer.music.get_busy():
                pygame.time.Clock().tick(10)

            pygame.mixer.music.stop()

        except Exception as e:
            print(f"Error en la síntesis de voz: {e}")
        finally:
            if temp_file:
                try:
                    os.remove(temp_file.name)
                except Exception as remove_error:
                    print(f"No se pudo borrar el archivo temporal: {remove_error}")
