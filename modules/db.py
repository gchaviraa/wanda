import mysql.connector
from mysql.connector import Error
from config import DB_CONFIG

def get_db_connection():
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        if connection.is_connected():
            return connection
    except Error as e:
        print(f"Error al conectar con la base de datos: {e}")
        return None
