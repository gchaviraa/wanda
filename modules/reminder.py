import schedule
import time
from db import get_db_connection

def check_reminders():
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    
    query = "SELECT * FROM tasks WHERE reminder_time IS NOT NULL AND reminder_time <= NOW() AND completed = 0"
    cursor.execute(query)
    tasks = cursor.fetchall()
    
    for task in tasks:
        print(f"🔔 Reminder: Task '{task['name']}' is due soon at {task['due_date']}!")
        # You can add text-to-speech or notifications here

    cursor.close()
    conn.close()

# Run the reminder check every minute
schedule.every(1).minutes.do(check_reminders)

if __name__ == "__main__":
    while True:
        schedule.run_pending()
        time.sleep(30)
