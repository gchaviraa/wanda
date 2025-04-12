import spacy

class NLPModule:
    def __init__(self):
        """Carga el modelo de NLP en español y define intenciones como conjuntos."""
        self.nlp = spacy.load("es_core_news_sm")

        # Palabras clave agrupadas como sets para búsquedas más eficientes
        self.intents = {
            "weather": {"clima", "tiempo", "temperatura", "frío", "calor", "lluvia", "soleado"},
            "tasks": {"tarea", "recordatorio", "anotar", "recuérdame", "apunta", "pendiente", "hacer", "comprar"},
            "email": {"correo", "email", "mensaje", "bandeja", "enviar"},
            "calendar": {"evento", "reunión", "calendario", "cita", "agenda", "programar"},
            "contacts": {"contacto", "contactos", "agenda", "guardar", "lista"}
        }

    def analyze(self, text):
        """Procesa texto y extrae tokens limpios y entidades nombradas."""
        doc = self.nlp(text)

        tokens = [
            token.lemma_.lower()
            for token in doc
            if not token.is_punct and not token.is_space and not token.is_stop
        ]

        entities = {}
        for ent in doc.ents:
            entities[ent.label_] = ent.text
            if ent.label_ in ("PER", "PERSON"):
                entities["CONTACT_NAME"] = ent.text

        return tokens, entities

    def get_intent(self, text):
        """Detecta la intención del usuario comparando tokens con palabras clave."""
        tokens, entities = self.analyze(text)
        token_set = set(tokens)

        for intent, keywords in self.intents.items():
            if token_set & keywords:  # intersección no vacía
                return intent, entities

        return "unknown", entities
