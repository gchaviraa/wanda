contacts = {
    "roberto": "rc@eptechservices.com",
    "karla": "kn@eptechservices.com",
    "tavo": "gchavirav@gmail.com",
    "gus": "gchaviraa@outlook.com"
}

def get_email_from_name(name):
    # Returns the email adress associated with a contact name
    return contacts.get(name.lower(), None)

def add_contact(name, email):
    # Adds a new contact to the contacts dictionary
    contacts[name.lower()] = email
    return f"✅ Contacto {name} guardado con el correo {email}."

def list_contacts():
    # Returns a list of all contacts
    if not contacts:
        return "No hay contactos guardados."
    return "\n".join([f"{name.capitalize()}: {email}" for name, email in contacts.items()])