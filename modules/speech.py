import speech_recognition as sr
from gtts import gTTS
import os
import tempfile

class SpeechModule:
    def __init__(self):
        """Initialize the speech recognizer."""
        self.recognizer = sr.Recognizer()

    def listen(self):
        """Recognizes speech from the microphone and returns the text."""
        with sr.Microphone() as source:
            print("🎤 Escuchando...")
            self.recognizer.adjust_for_ambient_noise(source)  # Reduce background noise
            
            try:
                audio = self.recognizer.listen(source, timeout=5)  # Listen with a 5-second timeout
                text = self.recognizer.recognize_google(audio, language="es-ES")  # Convert speech to text
                print(f"🗣️ Dijiste: {text}")
                return text
            except sr.WaitTimeoutError:
                print("⏳ Tiempo de espera agotado. No se detectó voz.")
            except sr.UnknownValueError:
                print("🤷 No entendí lo que dijiste.")
            except sr.RequestError:
                print("⚠️ Error al conectar con el servicio de reconocimiento de voz.")

        return ""  # Return empty string if recognition fails

    def speak(self, text):
        """Converts text to speech using gTTS and plays it."""
        try:
            tts = gTTS(text, lang="es")  # Convert text to Spanish speech
            temp_file = tempfile.NamedTemporaryFile(delete=True, suffix=".mp3")  # Create a temp file
            tts.save(temp_file.name)  # Save speech to MP3 file
            os.system(f"mpg321 {temp_file.name} -q")  # Play the MP3 file quietly
        except Exception as e:
            print(f"❌ Error en la síntesis de voz: {e}")
