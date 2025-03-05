import os
import pickle
from google.auth.transport.requests import Request
from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build

# Define the scope for reading emails (readonly access)
SCOPES = ["https://www.googleapis.com/auth/gmail.modify"]

# Paths for credentials and token files
TOKEN_FILE = "token.pickle"  # Stores authentication token
CREDENTIALS_FILE = "credentials.json"  # OAuth2 credentials file

def authenticate_gmail():
    """Authenticate with Gmail API and return a service instance."""
    creds = None
    if os.path.exists(TOKEN_FILE):
        with open(TOKEN_FILE, "rb") as token:
            creds = pickle.load(token)

    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
        else:
            flow = InstalledAppFlow.from_client_secrets_file(CREDENTIALS_FILE, SCOPES)
            creds = flow.run_local_server(port=0)  # Opens browser for login

        with open(TOKEN_FILE, "wb") as token:
            pickle.dump(creds, token)

    return build("gmail", "v1", credentials=creds)

def fetch_unread_emails():
    """Fetches unread emails and marks them as read after fetching."""
    service = authenticate_gmail()
    results = service.users().messages().list(userId="me", labelIds=["INBOX"], q="is:unread").execute()
    messages = results.get("messages", [])

    if not messages:
        return "No tienes correos electrónicos sin leer."

    email_list = []
    for msg in messages[:3]:  # Get latest 3 unread emails
        msg_data = service.users().messages().get(userId="me", id=msg["id"]).execute()
        payload = msg_data.get("payload", {})
        headers = payload.get("headers", [])

        subject = next((h["value"] for h in headers if h["name"] == "Subject"), "No Subject")
        sender = next((h["value"] for h in headers if h["name"] == "From"), "Unknown Sender")

        email_text = f"📩 De: {sender}\nAsunto: {subject}\n"
        email_list.append(email_text)

        # ✅ Mark email as read
        mark_email_as_read(service, msg["id"])

    return "\n\n".join(email_list)

def mark_email_as_read(service, msg_id):
    """Marks an email as read in Gmail."""
    service.users().messages().modify(userId="me", id=msg_id, body={"removeLabelIds": ["UNREAD"]}).execute()


# Test the function
if __name__ == "__main__":
    print(fetch_unread_emails())
