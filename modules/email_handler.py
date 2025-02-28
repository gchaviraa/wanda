import os
import requests
import msal
from config import CLIENT_ID, CLIENT_SECRET, TENANT_ID

# Microsoft OAuth Endpoints
AUTHORITY = f"https://login.microsoftonline.com/{TENANT_ID}"
SCOPES = ["https://graph.microsoft.com/.default"]

# Directory for saving attachments
ATTACHMENT_DIR = "attachments"
os.makedirs(ATTACHMENT_DIR, exist_ok=True)

def get_access_token():
    """Fetches OAuth2 access token from Microsoft Graph API."""
    app = msal.ConfidentialClientApplication(CLIENT_ID, CLIENT_SECRET, authority=AUTHORITY)
    result = app.acquire_token_for_client(scopes=SCOPES)
    
    if "access_token" in result:
        return result["access_token"]
    else:
        raise Exception("Failed to get access token:", result)
    
def get_unread_emails():
    """Fetch unread emails from Outlook using Microsoft Graph API."""
    access_token = get_access_token()
    headers = {"Authorization": f"Bearer {access_token}"}
    
    response = requests.get(
        "https://graph.microsoft.com/v1.0/me/messages?$filter=isRead eq false",
        headers=headers
    )
    
    if response.status_code == 200:
        emails = response.json().get("value", [])
        return emails
    else:
        print("Error fetching emails:", response.json())
        return []
    
def mark_email_as_read(email_id):
    """Marks an email as read."""
    access_token = get_access_token()
    headers = {"Authorization": f"Bearer {access_token}", "Content-Type": "application/json"}
    
    response = requests.patch(
        f"https://graph.microsoft.com/v1.0/me/messages/{email_id}",
        headers=headers,
        json={"isRead": True}
    )
    
    return response.status_code == 200

def get_email_attachments(email_id):
    """Fetches attachments from an email and filters only PDFs."""
    access_token = get_access_token()
    headers = {"Authorization": f"Bearer {access_token}"}
    
    response = requests.get(
        f"https://graph.microsoft.com/v1.0/me/messages/{email_id}/attachments",
        headers=headers
    )
    
    if response.status_code == 200:
        attachments = response.json().get("value", [])
        pdf_attachments = [att for att in attachments if att["name"].endswith(".pdf")]
        return pdf_attachments
    else:
        print("Error fetching attachments:", response.json())
        return []
    
def save_attachment(file_name, content_bytes):
    """Saves a PDF attachment to the attachments folder."""
    file_path = os.path.join(ATTACHMENT_DIR, file_name)
    with open(file_path, "wb") as file:
        file.write(content_bytes)
    print(f"✅ Saved PDF attachment: {file_name}")

def process_unread_emails():
    """Fetches unread emails, downloads PDFs, and marks emails as read."""
    emails = get_unread_emails()

    if not emails:
        print("📭 No unread emails.")
        return

    for email in emails:
        print(f"📩 From: {email['from']['emailAddress']['address']}")
        print(f"📌 Subject: {email['subject']}")

        attachments = get_email_attachments(email["id"])
        
        if attachments:
            print(f"📂 Found {len(attachments)} PDF attachment(s).")
            for attachment in attachments:
                file_name = attachment["name"]
                content_bytes = bytes(attachment["contentBytes"], encoding="utf-8")
                save_attachment(file_name, content_bytes)
        else:
            print("⚠️ No PDF attachments found.")

        # Mark email as read after processing
        if mark_email_as_read(email["id"]):
            print("✅ Email marked as read.\n")
        else:
            print("❌ Failed to mark email as read.\n")

# Test fetching emails and saving PDFs
if __name__ == "__main__":
    process_unread_emails()