#include <ESP8266WiFi.h>
#include <WiFiClientSecure.h>
#include <PubSubClient.h>

#define CLK_PIN D1
#define DT_PIN  D2
#define SW_PIN  D5
#define LED_PIN D6

const char* ssid = "YOUR_WIFI_NAME";
const char* password = "YOUR_WIFI_PASSWORD";

const char* mqtt_server = "broker.hivemq.com";
const int mqtt_port = 8883;
const char* publishTopic = "testtopic/temp/outTopic/ssu7c41";
const char* subscribeTopic = "testtopic/temp/inTopic";
const char* switchTopic = "testtopic/temp/outTopic/ssu7c41-switch";
const char* ledStateTopic = "testtopic/temp/outTopic/ssu7c41-led";

WiFiClientSecure espClient;
PubSubClient client(espClient);

#define ENCODER_MIN 0
#define ENCODER_MAX 1000

int counter = ENCODER_MIN;
int lastCLK;
int lastSW;
bool ledState = false;
bool ledStatePending = true;
unsigned long previousMillis = 0;
const unsigned long interval = 15000;
unsigned long switchMillis = 0;
bool switchActive = false;
bool buttonPressed = false;

void setup_wifi()
{
    WiFi.mode(WIFI_STA);
    WiFi.begin(ssid, password);
    Serial.print("Connecting to WiFi");
    while (WiFi.status() != WL_CONNECTED)
    {
        delay(500);
        Serial.print(".");
    }
    Serial.println("\nWiFi connected");
    Serial.print("IP address: ");
    Serial.println(WiFi.localIP());
}

void publishLEDState()
{
    if (client.connected())
    {
        if (client.publish(ledStateTopic, ledState ? "1" : "0", true))
        {
            ledStatePending = false;
            Serial.print("LED state published: ");
            Serial.println(ledState ? 1 : 0);
        }
    }
}

void setLED(bool state)
{
    ledState = state;
    digitalWrite(LED_PIN, ledState ? HIGH : LOW);
    Serial.print("LED: ");
    Serial.println(ledState ? "ON" : "OFF");
    ledStatePending = true;
}

void callback(char* topic, byte* payload, unsigned int length)
{
    Serial.print("Message received [");
    Serial.print(topic);
    Serial.print("]: ");
    for (unsigned int i = 0; i < length; i++)
        Serial.print((char)payload[i]);
    Serial.println();

    if (strcmp(topic, subscribeTopic) == 0 && length == 1)
    {
        if (payload[0] == '1')
            setLED(true);
        else if (payload[0] == '0')
            setLED(false);
    }
}

void reconnect()
{
    while (!client.connected())
    {
        Serial.println("Connecting to MQTT...");
        String clientId = "ESP8266-";
        clientId += String(ESP.getChipId(), HEX);
        if (client.connect(clientId.c_str()))
        {
            Serial.println("MQTT connected!");
            client.subscribe(subscribeTopic);
            Serial.print("Subscribed to: ");
            Serial.println(subscribeTopic);
            ledStatePending = true;
            publishLEDState();
        }
        else
        {
            Serial.print("Failed, rc=");
            Serial.println(client.state());
            delay(5000);
        }
    }
}

void setup()
{
    Serial.begin(9600);
    pinMode(CLK_PIN, INPUT_PULLUP);
    pinMode(DT_PIN, INPUT_PULLUP);
    pinMode(SW_PIN, INPUT_PULLUP);
    pinMode(LED_PIN, OUTPUT);
    digitalWrite(LED_PIN, LOW);
    lastCLK = digitalRead(CLK_PIN);
    lastSW = digitalRead(SW_PIN);
    setup_wifi();
    // Lab only: TLS server certificate verification is disabled.
    espClient.setInsecure();
    client.setServer(mqtt_server, mqtt_port);
    client.setCallback(callback);
    Serial.println("EE470 MQTT Final Firmware");
    Serial.println("Encoder range: 0 - 1000");
    Serial.println("Clockwise only");
}

void loop()
{
    if (!client.connected())
        reconnect();
    client.loop();

    // Rotary encoder: clockwise only, saturates at 1000.
    int currentCLK = digitalRead(CLK_PIN);
    if (currentCLK != lastCLK && currentCLK == LOW)
    {
        if (digitalRead(DT_PIN) != currentCLK)
        {
            if (counter < ENCODER_MAX)
            {
                counter++;
                Serial.print("Encoder: ");
                Serial.println(counter);
            }
        }
    }
    lastCLK = currentCLK;

    // Push button: publish 1 on release, then 0 after 5 seconds.
    int currentSW = digitalRead(SW_PIN);
    if (currentSW != lastSW)
    {
        delay(20);
        currentSW = digitalRead(SW_PIN);
        if (currentSW != lastSW)
        {
            if (currentSW == LOW)
            {
                buttonPressed = true;
                Serial.println("Switch pressed");
            }
            else if (buttonPressed)
            {
                buttonPressed = false;
                Serial.println("Switch released");
                if (client.publish(switchTopic, "1"))
                {
                    switchMillis = millis();
                    switchActive = true;
                    Serial.println("Switch published: 1");
                }
            }
            lastSW = currentSW;
        }
    }
    if (switchActive && millis() - switchMillis >= 5000)
    {
        if (client.publish(switchTopic, "0"))
        {
            switchActive = false;
            Serial.println("Switch published: 0");
        }
    }

    // Local LED control.
    if (Serial.available() > 0)
    {
        char command = Serial.read();
        if (command == '1')
            setLED(true);
        else if (command == '0')
            setLED(false);
    }

    if (ledStatePending)
        publishLEDState();

    // Encoder data every 15 seconds.
    if (millis() - previousMillis >= interval)
    {
        previousMillis = millis();
        char message[16];
        snprintf(message, sizeof(message), "%d", counter);
        if (client.publish(publishTopic, message))
        {
            Serial.print("Encoder published: ");
            Serial.println(message);
        }
        else
            Serial.println("Publishing failed");
    }
}
