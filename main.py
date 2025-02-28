from modules.speech import SpeechModule
from modules.nlp import NLPModule
from modules.weather import get_weather
from modules.email_handler import get_unread_emails

def main():
    assistant = SpeechModule()
    nlp = NLPModule()

    assistant.speak("Hola, soy Wanda. ¿En qué puedo ayudarte?")
    command = assistant.listen()

    intent, entities = nlp.get_intent(command)
    print(f"Comando recibido: {command} \n🎯 Intento detectado: {intent} \n📌 Entidades detectadas: {entities}")

    # Respond based on detected intent
    if intent == "weather":
        assistant.speak("¿De qué ciudad deseas saber el clima?")
        city = assistant.listen()

        if city:
            weather_info = get_weather(city)
            assistant.speak(weather_info)
        else:
            assistant.speak("No escuché ninguna ciudad. Intenta de nuevo.")

    elif intent == "email":
        assistant.speak("Voy a revisar tu bandeja de entrada.")
        emails = get_unread_emails()
        assistant.speak(emails)

    else:
        assistant.speak("Lo siento, no entendí eso.")

if __name__ == "__main__":
    main()

