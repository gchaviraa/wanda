from email.mime.text import MIMEText
import base64
import logging
from modules.email_reader import authenticate_gmail  # Reuse authentication

def send_email(to, subject, message_body):
    """Sends an email using Gmail API. Supports multiple recipients."""
    service = authenticate_gmail()

    if isinstance(to, list):  # Convert list to comma-separated string if multiple recipients
        to = ", ".join(to)

    # Create email message
    message = MIMEText(message_body)
    message["to"] = to
    message["subject"] = subject
    raw_message = base64.urlsafe_b64encode(message.as_bytes()).decode()

    try:
        send_message = service.users().messages().send(userId="me", body={"raw": raw_message}).execute()
        logging.info(f"✅ Email sent to {to} | Subject: {subject}")  # ✅ Log success
        return f"✅ Email enviado a {to}"
    except Exception as e:
        logging.error(f"❌ Error sending email to {to}: {str(e)}")  # ❌ Log failure
        return f"❌ Error al enviar correo: {str(e)}"

# Test sending an email
if __name__ == "__main__":
    print(send_email("recipient@example.com", "Prueba desde Wanda", "Hola, este es un correo de prueba enviado desde Wanda."))
