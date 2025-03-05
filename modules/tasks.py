from db import get_db_connection

def add_task(name, due_date=None, priority="normal", reminder_time=None):
    conn = get_db_connection()
    cursor = conn.cursor()
    query = "INSERT INTO tasks (name, due_date, priority, reminder_time) VALUES (%s, %s, %s, %s)"
    cursor.execute(query, (name, due_date, priority, reminder_time))
    conn.commit()
    cursor.close()
    conn.close()
    return f"Task '{name}' added!"

def list_tasks():
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM tasks ORDER BY due_date ASC")
    tasks = cursor.fetchall()
    cursor.close()
    conn.close()
    return tasks

def update_task(task_id, name=None, due_date=None, priority=None, completed=None, reminder_time=None):
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

    if updates:
        query = f"UPDATE tasks SET {', '.join(updates)} WHERE id = %s"
        params.append(task_id)
        cursor.execute(query, params)
        conn.commit()

    cursor.close()
    conn.close()
    return f"Task {task_id} updated!"

def delete_task(task_id):
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("DELETE FROM tasks WHERE id = %s", (task_id,))
    conn.commit()
    cursor.close()
    conn.close()
    return f"Task {task_id} deleted!"

# Example usage
if __name__ == "__main__":
    print(add_task("Finish AI module", "2025-03-10 15:00:00", "high", "2025-03-10 14:00:00"))
    print(list_tasks())
    print(update_task(1, completed=True))
    print(delete_task(1))
