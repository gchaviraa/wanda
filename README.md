# 🤖 Wanda - Asistente Virtual en Español

Wanda es una asistente virtual personal desarrollada en Python que entiende tus comandos por voz y ejecuta acciones como:

- 📬 Leer y enviar correos electrónicos
- 🌦️ Consultar el clima en tiempo real
- 📝 Agregar, listar y recordar tareas
- 📇 Gestionar contactos personales
- 🔊 Hablarte y escucharte en español

---

## 🖥️ Requisitos

- Python 3.12
- Micrófono funcional
- Conexión a Internet
- Cuenta de Gmail para enviar/leer correos
- Acceso a la API de WeatherAPI

---

## 🔧 Instalación

### 1. Clona o descarga el proyecto

```bash
git clone https://github.com/tuusuario/wanda.git
cd wanda
```

### 2. Crea un entorno virtual

- **Windows:**

```bash
python -m venv venv
venv\Scripts\activate
```

- **Linux/macOS:**

```bash
python3 -m venv venv
source venv/bin/activate
```

### 3. Instala las dependencias

```bash
pip install -r requirements.txt
```

### 4. Descarga el modelo de spaCy en español

```bash
python -m spacy download es_core_news_sm
```

---

## ⚙️ Configuración

Crea un archivo `config.py` en la raíz del proyecto:

```python
# config.py

DB_CONFIG = {
    "host": "localhost",
    "user": "tu_usuario",
    "password": "tu_contraseña",
    "database": "wanda_db"
}

WEATHER_API_KEY = "TU_API_KEY_DE_WEATHERAPI"
```

También necesitas tener un archivo `credentials.json` generado desde [Google Cloud Console](https://console.cloud.google.com/) para habilitar el acceso a la API de Gmail.

---

## ▶️ Uso

Inicia el asistente con:

```bash
python main.py
```

### 📣 Ejemplos de comandos por voz

- "¿Cuál es el clima en Guadalajara?"
- "Envía un correo a Roberto"
- "¿Tengo correos sin leer?"
- "Guarda a Karla como contacto con el correo karla@ejemplo.com"
- "Lista mis contactos"
- "Anota que tengo cita médica mañana a las 3"
- "Recuérdame comprar leche"

---

## 📁 Estructura del proyecto

```
wanda/
├── main.py
├── modules/
│   ├── speech.py
│   ├── nlp.py
│   ├── email_reader.py
│   ├── email_sender.py
│   ├── contacts.py
│   ├── weather.py
│   ├── tasks.py
│   └── db.py
├── config.py
├── requirements.txt
└── README.md
```

---

## 🛠 Consejos

- Usa auriculares con micrófono para mejor reconocimiento.
- Revisa el archivo `wanda.log` si algo falla.
- Agrega más intents a `nlp.py` para expandir funcionalidades.

---

## 📄 Licencia

Este es un proyecto experimental para automatizar tareas por voz usando Python.  
No está pensado para distribución o uso comercial.
