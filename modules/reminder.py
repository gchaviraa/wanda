import schedule
import time
from db import get_db_connection
from datetime import datetime

def check_reminders():
    """Verifica y muestra tareas con recordatorios activos y vencidos."""
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)

        query = """
        SELECT * FROM tasks
        WHERE reminder_time IS NOT NULL
        AND reminder_time <= NOW()
        AND completed = 0
        """
        cursor.execute(query)
        tasks = cursor.fetchall()

        for task in tasks:
            print(f"Recordatorio: La tarea '{task['name']}' vence a las {task['due_date']}.")

        cursor.close()
        conn.close()
    except Exception as e:
        print(f"Error al verificar recordatorios: {e}")

# Ejecutar la verificación cada minuto
schedule.every(1).minutes.do(check_reminders)

if __name__ == "__main__":
    print("Sistema de recordatorios iniciado. Presiona Ctrl+C para detener.")
    try:
        while True:
            schedule.run_pending()
            time.sleep(30)
    except KeyboardInterrupt:
        print("\nRecordatorios detenidos por el usuario.")
