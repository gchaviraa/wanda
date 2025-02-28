import sys
import os
sys.path.append(os.path.abspath(os.path.join(os.path.dirname(__file__), "..")))
import imapclient
import email
from email.header import decode_header
from config import EMAIL_ADDRESS, EMAIL_PASSWORD

# Defines email providers and their IMAP server
IMAP_SERVERS = {
    'gmail.com': 'imap.gmail.com',
    'outlook.com': 'outlook.office365.com',
    'hotmail.com': 'outlook.office365.com',
    'yahoo.com': 'imap.mail.yahoo.com'
}

def get_email_provider(email_address):
    # Extract email provider from email address
    domain = email_address.split("@")[-1]
    return IMAP_SERVERS.get(domain, None)

def get_unread_emails():
    """Fetch unread emails from the inbox based on the email provider."""
    email_provider = get_email_provider(EMAIL_ADDRESS)
    if not email_provider:
        return "Proveedor de correo no soportado."

    print(f"📧 Conectando a: {email_provider}")  # Debugging step

    try:
        with imapclient.IMAPClient(email_provider) as client:
            client.login(EMAIL_ADDRESS, EMAIL_PASSWORD)
            client.select_folder("INBOX")

            unread_messages = client.search(["UNSEEN"])  # Fetch unread emails
            if not unread_messages:
                return "No tienes correos nuevos."

            messages = client.fetch(unread_messages, ["RFC822"])
            email_list = []
            for msg_id, data in messages.items():
                raw_email = data[b'RFC822']
                msg = email.message_from_bytes(raw_email)

                # Decode email subject
                subject, encoding = decode_header(msg["Subject"])[0]
                if isinstance(subject, bytes):
                    subject = subject.decode(encoding if encoding else "utf-8")

                sender = msg.get("From")
                email_list.append(f"De: {sender} | Asunto: {subject}")

            return "\n".join(email_list)

    except Exception as e:
        return f"Error al obtener correos: {e}"
    
# Test fetching emails
if __name__ == "__main__":
    print(get_unread_emails())