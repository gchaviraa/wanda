from modules.speech import SpeechModule
from modules.nlp import NLPModule
from modules.weather import get_weather
from modules.email_reader import fetch_unread_emails
from modules.email_sender import send_email
from modules.contacts import get_email_from_name, add_contact, list_contacts  # Import contact functions

def handle_weather(assistant):
    """Handles weather requests."""
    assistant.speak("¿De qué ciudad deseas saber el clima?")
    city = assistant.listen()
    weather_info = get_weather(city) if city else "No escuché ninguna ciudad. Intenta de nuevo."
    assistant.speak(weather_info)

def handle_email(assistant):
    """Handles email reading and sending."""
    assistant.speak("¿Quieres leer o enviar un correo?")
    email_action = assistant.listen()

    if "leer" in email_action:
        assistant.speak("Voy a revisar tu bandeja de entrada.")
        assistant.speak(fetch_unread_emails())

    elif "enviar" in email_action:
        send_email_flow(assistant)

def send_email_flow(assistant):
    """Handles the email sending process."""
    assistant.speak("¿A quién quieres enviar el correo?")
    recipient_name = assistant.listen()

    # Check contacts or ask for an email
    recipient_email = get_email_from_name(recipient_name)
    if not recipient_email:
        assistant.speak(f"No tengo registrado a {recipient_name}. ¿Puedes decirme su correo electrónico?")
        recipient_email = assistant.listen()
        assistant.speak(f"¿Quieres guardar {recipient_email} como contacto para {recipient_name}?")
        if "sí" in assistant.listen().lower():
            add_contact(recipient_name, recipient_email)
            assistant.speak(f"Contacto {recipient_name} guardado.")

    # Get subject and message
    assistant.speak("¿Cuál es el asunto del correo?")
    subject = assistant.listen()

    assistant.speak("Dime el mensaje del correo.")
    message = assistant.listen()

    # Confirm before sending
    assistant.speak(f"Vas a enviar un correo a {recipient_name} con el asunto {subject}. El mensaje dice: {message}. ¿Quieres enviarlo ahora?")
    if "sí" in assistant.listen().lower():
        assistant.speak(send_email(recipient_email, subject, message))
    else:
        assistant.speak("Correo cancelado.")

def handle_contacts(assistant):
    """Handles contact listing."""
    assistant.speak("Estos son tus contactos guardados.")
    assistant.speak(list_contacts())

def main():
    assistant = SpeechModule()
    nlp = NLPModule()

    assistant.speak("Hola, soy Wanda. ¿En qué puedo ayudarte?")
    command = assistant.listen()
    
    intent, entities = nlp.get_intent(command)
    print(f"Comando recibido: {command} \n🎯 Intento detectado: {intent} \n📌 Entidades detectadas: {entities}")

    # Route intent to the correct function
    handlers = {
        "weather": handle_weather,
        "email": handle_email,
        "contacts": handle_contacts,
    }

    handler = handlers.get(intent, lambda a: assistant.speak("Lo siento, no entendí eso."))
    handler(assistant)

if __name__ == "__main__":
    main()
