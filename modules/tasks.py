from modules.db import get_db_connection

def add_task(name, due_date=None, priority="normal", reminder_time=None):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        query = "INSERT INTO tasks (name, due_date, priority, reminder_time) VALUES (%s, %s, %s, %s)"
        cursor.execute(query, (name, due_date, priority, reminder_time))
        conn.commit()
        return f"Tarea '{name}' agregada."
    except Exception as e:
        return f"Error al agregar la tarea: {e}"
    finally:
        cursor.close()
        conn.close()

def list_tasks():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT * FROM tasks ORDER BY due_date ASC")
        tasks = cursor.fetchall()
        return tasks
    except Exception as e:
        return f"Error al listar tareas: {e}"
    finally:
        cursor.close()
        conn.close()

def update_task(task_id, name=None, due_date=None, priority=None, completed=None, reminder_time=None):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()

        updates = []
        params = []
        if name:
            updates.append("name = %s")
            params.append(name)
        if due_date:
            updates.append("due_date = %s")
            params.append(due_date)
        if priority:
            updates.append("priority = %s")
            params.append(priority)
        if completed is not None:
            updates.append("completed = %s")
            params.append(completed)
        if reminder_time:
            updates.append("reminder_time = %s")
            params.append(reminder_time)

        if not updates:
            return "No se proporcionaron campos para actualizar."

        query = f"UPDATE tasks SET {', '.join(updates)} WHERE id = %s"
        params.append(task_id)
        cursor.execute(query, params)
        conn.commit()
        return f"Tarea {task_id} actualizada."
    except Exception as e:
        return f"Error al actualizar la tarea {task_id}: {e}"
    finally:
        cursor.close()
        conn.close()

def delete_task(task_id):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("DELETE FROM tasks WHERE id = %s", (task_id,))
        conn.commit()
        return f"Tarea {task_id} eliminada."
    except Exception as e:
        return f"Error al eliminar la tarea {task_id}: {e}"
    finally:
        cursor.close()
        conn.close()

# Ejemplo de uso
if __name__ == "__main__":
    print(add_task("Terminar módulo de IA", "2025-03-10 15:00:00", "alta", "2025-03-10 14:00:00"))
    print(list_tasks())
    print(update_task(1, completed=True))
    print(delete_task(1))
