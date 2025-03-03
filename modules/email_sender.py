from email.mime.text import MIMEText
import base64
from modules.email_reader import authenticate_gmail  # Reuse authentication

def send_email(to, subject, message_body):
    """Sends an email using Gmail API."""
    service = authenticate_gmail()

    # Create email message
    message = MIMEText(message_body)
    message["to"] = to
    message["subject"] = subject
    raw_message = base64.urlsafe_b64encode(message.as_bytes()).decode()

    try:
        send_message = service.users().messages().send(userId="me", body={"raw": raw_message}).execute()
        return f"✅ Email enviado a {to} con ID: {send_message['id']}"
    except Exception as e:
        return f"❌ Error al enviar correo: {str(e)}"

# Test sending an email
if __name__ == "__main__":
    print(send_email("recipient@example.com", "Prueba desde Wanda", "Hola, este es un correo de prueba enviado desde Wanda."))
