import paho.mqtt.client as mqtt
import mysql.connector
import ssl
from getpass import getpass

BROKER_URL = "broker.hivemq.com"
BROKER_PORT = 8884
ENCODER_TOPIC = "testtopic/temp/outTopic/ssu7c41"
LED_TOPIC = "testtopic/temp/outTopic/ssu7c41-led"

# Use the same remote hostname or IPv4 address as in mysql_test.py.
HOST = "YOUR_REMOTE_MYSQL_HOST"
PORT = 3306
USER = "YOUR_DATABASE_USERNAME"
DATABASE = "YOUR_DATABASE_NAME"
PASSWORD = getpass("Database password: ")


def push_value_to_db(topic, value):
    connection = None
    cursor = None
    try:
        connection = mysql.connector.connect(
            host=HOST, port=PORT, user=USER, password=PASSWORD,
            database=DATABASE, connection_timeout=10
        )
        cursor = connection.cursor()
        if topic == ENCODER_TOPIC:
            query = "INSERT INTO sensor_value (value) VALUES (%s)"
        elif topic == LED_TOPIC:
            query = "INSERT INTO led_state (state) VALUES (%s)"
        else:
            return
        cursor.execute(query, (value,))
        connection.commit()
        print(f"Database insertion successful: {value}")
    except mysql.connector.Error as err:
        print(f"Database error: {err}")
    finally:
        if cursor is not None:
            cursor.close()
        if connection is not None:
            connection.close()


def on_connect(client, userdata, flags, reason_code, properties):
    if reason_code == 0:
        print("Connected to HiveMQ!")
        client.subscribe(ENCODER_TOPIC)
        client.subscribe(LED_TOPIC)
        print(f"Subscribed to: {ENCODER_TOPIC}")
        print(f"Subscribed to: {LED_TOPIC}")
    else:
        print(f"Connection failed: {reason_code}")


def on_message(client, userdata, msg):
    try:
        value = int(msg.payload.decode())
        print("-----------------------------")
        if msg.topic == ENCODER_TOPIC:
            if not 0 <= value <= 1000:
                return
            print(f"Encoder value: {value}")
        elif msg.topic == LED_TOPIC:
            if value not in (0, 1):
                return
            if msg.retain:
                print("Last retained LED state:", value)
                return
            print(f"LED state: {value}")
        else:
            return
        push_value_to_db(msg.topic, value)
    except (ValueError, UnicodeDecodeError):
        print("Invalid MQTT message")


client = mqtt.Client(
    mqtt.CallbackAPIVersion.VERSION2,
    client_id="ssu7c41-python",
    transport="websockets"
)
client.ws_set_options(path="/mqtt")
client.tls_set_context(ssl.create_default_context())
client.on_connect = on_connect
client.on_message = on_message
print("Connecting to HiveMQ...")
client.connect(BROKER_URL, BROKER_PORT, 60)
client.loop_forever()
