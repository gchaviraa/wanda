from modules.speech import SpeechModule
from modules.nlp import NLPModule
from modules.weather import get_weather
from modules.email_reader import fetch_unread_emails
from modules.email_sender import send_email
from modules.contacts import get_email_from_name, add_contact, list_contacts  # Import contact functions

def main():
    assistant = SpeechModule()
    nlp = NLPModule()

    assistant.speak("Hola, soy Wanda. ¿En qué puedo ayudarte?")
    command = assistant.listen()

    intent, entities = nlp.get_intent(command)
    print(f"Comando recibido: {command} \n🎯 Intento detectado: {intent} \n📌 Entidades detectadas: {entities}")

    if intent == "weather":
        assistant.speak("¿De qué ciudad deseas saber el clima?")
        city = assistant.listen()
        if city:
            weather_info = get_weather(city)
            assistant.speak(weather_info)
        else:
            assistant.speak("No escuché ninguna ciudad. Intenta de nuevo.")

    elif intent == "email":
        assistant.speak("¿Quieres leer o enviar un correo?")
        email_action = assistant.listen()

        if "leer" in email_action:
            assistant.speak("Voy a revisar tu bandeja de entrada.")
            emails = fetch_unread_emails()
            assistant.speak(emails)

        elif "enviar" in email_action:
            assistant.speak("¿A quién quieres enviar el correo?")
            recipient_name = assistant.listen()

            # Check if the name exists in contacts
            recipient_email = get_email_from_name(recipient_name)

            if not recipient_email:
                assistant.speak(f"No tengo registrado a {recipient_name}. ¿Puedes decirme su correo electrónico?")
                recipient_email = assistant.listen()
                assistant.speak(f"¿Quieres guardar {recipient_email} como contacto para {recipient_name}?")
                confirmation = assistant.listen()
                if "sí" in confirmation.lower():
                    add_contact(recipient_name, recipient_email)
                    assistant.speak(f"Contacto {recipient_name} guardado.")

            assistant.speak("¿Cuál es el asunto del correo?")
            subject = assistant.listen()

            assistant.speak("Dime el mensaje del correo.")
            message = assistant.listen()

            result = send_email(recipient_email, subject, message)
            assistant.speak(result)

    elif intent == "contacts":
        assistant.speak("Estos son tus contactos guardados.")
        contacts_list = list_contacts()
        assistant.speak(contacts_list)

    else:
        assistant.speak("Lo siento, no entendí eso.")

if __name__ == "__main__":
    main()
