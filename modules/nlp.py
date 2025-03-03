import spacy

class NLPModule:
    def __init__(self):
        """Load the Spanish NLP model."""
        self.nlp = spacy.load("es_core_news_sm")

        # Intent keywords and synonyms
        self.intents = {
            "weather": ["clima", "tiempo", "temperatura", "frío", "calor", "lluvia", "soleado"],
            "tasks": ["tarea", "recordatorio", "anotar", "recuérdame", "apunta", "pendiente", "hacer", "comprar"],
            "email": ["correo", "email", "mensaje", "bandeja", "enviar"],
            "calendar": ["evento", "reunión", "calendario", "cita", "agenda", "programar"],
            "contacts": ["contacto", "contactos", "agenda", "guardar", "lista"]
        }

    def analyze(self, text):
        """Process text and extract key words and named entities."""
        doc = self.nlp(text)
        tokens = [token.lemma_.lower() for token in doc]  # Lemmatized words
        entities = {ent.label_: ent.text for ent in doc.ents}  # Named entities

        # Detect proper names (PERSON entities) to use as CONTACT_NAME
        for ent in doc.ents:
            if ent.label_ == "PER":  # 'PER' is the spaCy label for Person Names in Spanish
                entities["CONTACT_NAME"] = ent.text

        return tokens, entities

    def get_intent(self, text):
        """Determine user intent based on improved keyword detection."""
        tokens, entities = self.analyze(text)

        for intent, keywords in self.intents.items():
            if any(word in tokens for word in keywords):  # Match any synonym
                return intent, entities

        return "unknown", entities
