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

# Helper method for send_email_flow
def get_valid_input(assistant, prompt):
    """Keeps asking the user until a valid input is provided."""
    response = None
    while not response:
        assistant.speak(prompt)
        response = assistant.listen().strip()
        if not response:
            assistant.speak("No te escuché bien. Inténtalo de nuevo.")
    return response

# Helper method for send_email_flow
def get_recipient_emails(assistant):
    """Handles multiple recipients and ensures all emails are retrieved or entered."""
    recipient_names = get_valid_input(assistant, "¿A quién o a quiénes quieres enviar el correo?")
    recipient_names = [name.strip() for name in recipient_names.split(" y ")]

    recipient_emails = []
    for name in recipient_names:
        email = get_email_from_name(name)
        if not email:
            email = get_valid_input(assistant, f"No tengo registrado a {name}. ¿Puedes decirme su correo electrónico?")
            if "sí" in get_valid_input(assistant, f"¿Quieres guardar {email} como contacto para {name}?").lower():
                add_contact(name, email)
                assistant.speak(f"Contacto {name} guardado.")
        recipient_emails.append(email)

    return recipient_emails

# Helper method for send_email_flow
def edit_email_content(assistant, subject, message):
    """Allows user to edit the subject or message before sending."""
    while True:
        edit_choice = get_valid_input(assistant, f"Vas a enviar un correo con el asunto '{subject}'. El mensaje dice: {message}. ¿Quieres editar algo antes de enviarlo?")

        if "sí" in edit_choice:
            edit_option = get_valid_input(assistant, "¿Quieres editar el asunto o el mensaje?").lower()
            if "asunto" in edit_option:
                subject = get_valid_input(assistant, "Dime el nuevo asunto del correo.")
            elif "mensaje" in edit_option:
                message = get_valid_input(assistant, "Dime el nuevo contenido del correo.")
            else:
                assistant.speak("No entendí qué quieres editar. Intenta de nuevo.")
        elif "no" in edit_choice:
            break  # Proceed with sending
    return subject, message

def send_email_flow(assistant):
    """Handles the email sending process with multiple recipients, re-asking for names, and editing before sending."""
    recipient_emails = get_recipient_emails(assistant)
    subject = get_valid_input(assistant, "¿Cuál es el asunto del correo?")
    message = get_valid_input(assistant, "Dime el mensaje del correo.")

    # Allow editing before sending
    subject, message = edit_email_content(assistant, subject, message)

    # Confirm and send email
    assistant.speak(f"Enviando correo a {', '.join(recipient_emails)} con el asunto '{subject}'.")
    result = send_email(recipient_emails, subject, message)
    assistant.speak(result)

    # Log email sending action
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
