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
    """Handles the email sending process with multiple recipients and re-asking if Wanda doesn't hear a name."""
    assistant.speak("¿A quién o a quiénes quieres enviar el correo?")
    
    recipient_names = None
    while not recipient_names:  # Keep asking until Wanda hears something
        recipient_input = assistant.listen().strip()
        if recipient_input:
            recipient_names = [name.strip() for name in recipient_input.split(" y ")]  # Support "Juan y María"
        else:
            assistant.speak("No te escuché bien. ¿A quién quieres enviar el correo?")

    recipient_emails = []
    for name in recipient_names:
        email = get_email_from_name(name)
        if not email:
            assistant.speak(f"No tengo registrado a {name}. ¿Puedes decirme su correo electrónico?")
            
            email = None
            while not email:  # Keep asking until a valid email is provided
                email = assistant.listen().strip()
                if not email:
                    assistant.speak("No escuché el correo, por favor repítelo.")

            assistant.speak(f"¿Quieres guardar {email} como contacto para {name}?")
            if "sí" in assistant.listen().lower():
                add_contact(name, email)
                assistant.speak(f"Contacto {name} guardado.")

        recipient_emails.append(email)

    # Get subject and message
    assistant.speak("¿Cuál es el asunto del correo?")
    
    subject = None
    while not subject:
        subject = assistant.listen().strip()
        if not subject:
            assistant.speak("No escuché el asunto. ¿Cuál es el asunto del correo?")

    assistant.speak("Dime el mensaje del correo.")
    
    message = None
    while not message:
        message = assistant.listen().strip()
        if not message:
            assistant.speak("No escuché el mensaje. ¿Cuál es el contenido del correo?")

    # Confirm before sending
    email_list_str = ", ".join(recipient_emails)
    assistant.speak(f"Vas a enviar un correo a {email_list_str} con el asunto {subject}. El mensaje dice: {message}. ¿Quieres enviarlo ahora?")
    
    if "sí" in assistant.listen().lower():
        assistant.speak(send_email(recipient_emails, subject, message))
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
