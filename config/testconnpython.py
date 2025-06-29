import pandas as pd
import mysql.connector # Library to connect to MySQL

# --- Database Configuration ---
# IMPORTANT: Replace these with your actual database credentials.
DB_CONFIG = {
    'user': 'root',
    'password': '',
    'host': '127.0.0.1', # or your db host, e.g., 'localhost'
    'database': 'ecommercedb',
    'port': 3307
}

def get_db_connection():
    """Establishes and returns a database connection."""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        print("✅ Database connection successful.")
        return conn
    except mysql.connector.Error as e:
        print(f"❌ Error connecting to MySQL database: {e}")
        return None

# (This code goes after the get_db_connection() function)

# This is the main part of the script that makes things happen.
# It tells Python to run the code inside when the file is executed.
if __name__ == '__main__':
    print("▶️ Script started.")
    
    # This is the line that "calls" or "runs" your function
    conn = get_db_connection()
    
    # If the connection was successful, we should close it.
    if conn:
        conn.close()
        print("⏹️ Connection closed.")
        
    print("▶️ Script finished.")