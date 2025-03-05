from modules.speech import SpeechModule
from modules.nlp import NLPModule
from modules.weather import get_weather
from modules.email_reader import fetch_unread_emails
from modules.email_sender import send_email
from modules.contacts import get_email_from_name, add_contact, list_contacts  # Import contact functions
import logging

# Configure basic logging
logging.basicConfig(
    filename="wanda.log",  # Save logs to wanda.log
    level=logging.INFO,  # Log important actions
    format="%(asctime)s - %(levelname)s - %(message)s",  # Timestamp + level + message
)

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
    """Handles the email sending process with multiple recipients, re-asking for names, and editing before sending."""
    assistant.speak("¿A quién o a quiénes quieres enviar el correo?")
    
    recipient_names = None
    while not recipient_names:
        recipient_input = assistant.listen().strip()
        if recipient_input:
            recipient_names = [name.strip() for name in recipient_input.split(" y ")]
        else:
            assistant.speak("No te escuché bien. ¿A quién quieres enviar el correo?")

    recipient_emails = []
    for name in recipient_names:
        email = get_email_from_name(name)
        if not email:
            assistant.speak(f"No tengo registrado a {name}. ¿Puedes decirme su correo electrónico?")
            
            email = None
            while not email:
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

    # ✅ Ask if the user wants to edit before sending
    while True:
        assistant.speak(f"Vas a enviar un correo a {', '.join(recipient_emails)} con el asunto {subject}. El mensaje dice: {message}. ¿Quieres editar algo antes de enviarlo?")
        edit_choice = assistant.listen().lower()

        if "sí" in edit_choice:
            assistant.speak("¿Quieres editar el asunto o el mensaje?")
            edit_option = assistant.listen().lower()

            if "asunto" in edit_option:
                assistant.speak("Dime el nuevo asunto del correo.")
                subject = assistant.listen().strip()
            elif "mensaje" in edit_option:
                assistant.speak("Dime el nuevo contenido del correo.")
                message = assistant.listen().strip()
            else:
                assistant.speak("No entendí qué quieres editar. Intenta de nuevo.")

        elif "no" in edit_choice:
            break  # Exit the edit loop and proceed to sending

    # Confirm before sending
    assistant.speak(f"Enviando correo a {', '.join(recipient_emails)} con el asunto '{subject}'.")
    result = send_email(recipient_emails, subject, message)
    assistant.speak(result)

    # ✅ Log email sending action
    logging.info(f"Email sent to {', '.join(recipient_emails)} | Subject: {subject} | Message: {message}")

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
