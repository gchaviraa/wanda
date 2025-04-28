# modules/historial.py
import json
import os
from datetime import datetime

HISTORIAL_PATH = "command_history.json"

def load_history():
    if os.path.exists(HISTORIAL_PATH):
        with open(HISTORIAL_PATH, "r", encoding="utf-8") as f:
            return json.load(f)
    else:
        return []

def save_command(comando):
    history = load_history()
    new_registry = {
        "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
        "comando": comando
    }
    history.append(new_registry)
    with open(HISTORIAL_PATH, "w", encoding="utf-8") as f:
        json.dump(history, f, indent=4, ensure_ascii=False)

def listar_historial():
    history = load_history()
    for entry in history:
        print(f"[{entry['timestamp']}] {entry['comando']}")
