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
            "calendar": ["evento", "reunión", "calendario", "cita", "agenda", "programar"]
        }

    def analyze(self, text):
        """Process text and extract key words and named entities."""
        doc = self.nlp(text)
        tokens = [token.lemma_.lower() for token in doc]  # Lemmatized words
        entities = {ent.label_: ent.text for ent in doc.ents}  # Named entities

        # Custom entity extraction for objects (like "comprar leche")
        for i, token in enumerate(doc):
            if token.lemma_ in ["comprar", "recordar", "anotar"]:  # If it's a task-related verb
                if i + 1 < len(doc):  # Check if there’s a next word
                    entities["TASK_ITEM"] = doc[i + 1].text  # Capture the next word as an entity

        return tokens, entities

    def get_intent(self, text):
        """Determine user intent based on improved keyword detection."""
        tokens, entities = self.analyze(text)

        for intent, keywords in self.intents.items():
            if any(word in tokens for word in keywords):  # Match any synonym
                return intent, entities

        return "unknown", entities

# Testing
if __name__ == "__main__":
    nlp = NLPModule()
    
    test_sentences = [
        "¿Cómo está el clima hoy?",  # Should detect "weather"
        "Recuérdame comprar leche.",  # Should detect "tasks" + recognize "leche"
        "¿Tengo correos nuevos?",  # Should detect "email"
        "¿Cuándo es mi próxima reunión?",  # Should detect "calendar"
        "Cántame una canción."  # Should return "unknown"
    ]

    for sentence in test_sentences:
        intent, entities = nlp.get_intent(sentence)
        print(f"📝 Entrada: {sentence} \n🎯 Intento: {intent} \n📌 Entidades: {entities}\n")
