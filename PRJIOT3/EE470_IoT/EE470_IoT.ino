
/*
 * EE470 ESP8266 IoT Monitoring
 * Version: 1.7
 *
 * Inputs:
 * DHT11         D5
 * HW-506        D2
 * Pushbutton    D6
 * Tilt Switch   D7
 *
 * Node_1: DHT11 temperature and humidity only
 * Node_2: HW-506 temperature only
 *
 * Outputs:
 * Serial Monitor and Hostinger MariaDB
 *
 * Features:
 * WiFi diagnostics
 * Fixed Pacific time zone (America/Los_Angeles)
 * TimeAPI synchronization
 * Debounced switch detection
 * Node-specific sensor measurements
 * HTTPS transmission
 * JSON acknowledgment
 * 30-second message retransmission
 *
 * Libraries:
 * ESP8266 Arduino Core
 * DHT Sensor Library
 * Adafruit Unified Sensor
 * OneWire
 * DallasTemperature
 * ArduinoJson 6.x
 */

#include <Arduino.h>
#include <ESP8266WiFi.h>
#include <ESP8266HTTPClient.h>
#include <WiFiClientSecureBearSSL.h>
#include <ArduinoJson.h>
#include <DHT.h>
#include <OneWire.h>
#include <DallasTemperature.h>
#include <time.h>
#include <math.h>

// 1. USER CONFIGURATION

const char* WIFI_SSID = "saiga";
const char* WIFI_PASSWORD = "sighail1234";

const char* SERVER_URL =
    "https://meka.blog/insert.php";

const char* API_TOKEN =
    "EE470_MyESP8266_2026_SecureKey_8742";

// 2. GPIO CONFIGURATION

#define DHT_PIN     D5
#define HW506_PIN   D2
#define BUTTON_PIN  D6
#define TILT_PIN    D7

// 3. TIMING CONFIGURATION

const uint32_t DEBOUNCE_MS = 80;
const uint32_t WIFI_RETRY_MS = 10000;
const uint32_t MESSAGE_RETRY_MS = 30000;

const uint32_t CLOCK_RESYNC_MS = 1800000;
const uint32_t CLOCK_FAILED_RETRY_MS = 300000;

const uint8_t QUEUE_CAPACITY = 12;

// 4. SENSOR OBJECTS

DHT dht(DHT_PIN, DHT11);

OneWire oneWire(HW506_PIN);
DallasTemperature hw506(&oneWire);

// 5. FIXED TIME ZONE - CALIFORNIA

const char* TIME_ZONE = "America/Los_Angeles";

// 6. STRUCTURES

struct InputSwitch {
    uint8_t pin;
    bool raw;
    bool stable;
    uint32_t changedAt;
};

struct ClockData {
    bool valid;
    int64_t localSeconds;
    uint16_t milliseconds;
    uint32_t syncedAt;
    uint32_t attemptedAt;
    bool attempted;
};

struct Measurement {
    char node[8];
    char timestamp[28];
    char zone[32];

    float temperature;
    float humidity;
    float temperature2;

    bool hasDHT;
    bool attempted;
    uint32_t lastAttempt;
};

// 7. GLOBAL VARIABLES

InputSwitch button = {
    BUTTON_PIN, HIGH, HIGH, 0
};

InputSwitch tilt = {
    TILT_PIN, HIGH, HIGH, 0
};

ClockData clockData = {
    false, 0, 0, 0, 0, false
};

Measurement queueData[QUEUE_CAPACITY];

uint8_t queueCount = 0;

uint32_t wifiLastAttempt = 0;
bool wifiWasConnected = false;

// 8. URL ENCODING

String urlEncode(const String& input) {

    String result;

    const char hex[] = "0123456789ABCDEF";

    for (size_t i = 0; i < input.length(); ++i) {

        uint8_t c = (uint8_t)input[i];

        if ((c >= 'a' && c <= 'z') ||
            (c >= 'A' && c <= 'Z') ||
            (c >= '0' && c <= '9') ||
            c == '-' || c == '_' || c == '.') {

            result += (char)c;

        } else {

            result += '%';
            result += hex[c >> 4];
            result += hex[c & 15];
        }
    }

    return result;
}

// 9. DATE CONVERSION

int64_t daysFromCivil(
    int y,
    unsigned m,
    unsigned d
) {

    y -= m <= 2;

    const int era =
        (y >= 0 ? y : y - 399) / 400;

    const unsigned yoe =
        (unsigned)(y - era * 400);

    const unsigned doy =
        (153 * (m + (m > 2 ? -3 : 9)) + 2) / 5
        + d - 1;

    const unsigned doe =
        yoe * 365 + yoe / 4 - yoe / 100 + doy;

    return (int64_t)era * 146097
           + doe - 719468;
}

// 11. NETWORK INFORMATION

void printNetwork() {

    Serial.println();
    Serial.println("===== NETWORK STATUS =====");

    if (WiFi.status() != WL_CONNECTED) {

        Serial.println("WiFi: DISCONNECTED");
        return;
    }

    Serial.print("SSID: ");
    Serial.println(WiFi.SSID());

    Serial.print("IP: ");
    Serial.println(WiFi.localIP());

    Serial.print("MAC: ");
    Serial.println(WiFi.macAddress());

    Serial.print("Gateway: ");
    Serial.println(WiFi.gatewayIP());

    Serial.print("DNS: ");
    Serial.println(WiFi.dnsIP());

    Serial.print("Channel: ");
    Serial.println(WiFi.channel());

    Serial.print("RSSI: ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");

    Serial.print("Pending messages: ");
    Serial.println(queueCount);

    Serial.print("Free RAM: ");
    Serial.print(ESP.getFreeHeap());
    Serial.println(" bytes");

    Serial.print("FLASH used: ");
    Serial.print(ESP.getSketchSize());
    Serial.println(" bytes");

    Serial.print("FLASH total: ");
    Serial.print(ESP.getFlashChipRealSize());
    Serial.println(" bytes");
}

// 12. WIFI SERVICE

void serviceWiFi() {

    if (WiFi.status() == WL_CONNECTED) {

        if (!wifiWasConnected) {

            wifiWasConnected = true;

            Serial.println("WiFi connected!");

            printNetwork();
        }

        return;
    }

    if (wifiWasConnected) {

        wifiWasConnected = false;

        Serial.println(
            "WiFi disconnected; reconnecting..."
        );
    }

    if ((uint32_t)(millis() - wifiLastAttempt)
        >= WIFI_RETRY_MS) {

        wifiLastAttempt = millis();

        WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

        Serial.println("WiFi reconnect attempt");
    }
}

// 13. TIME SYNCHRONIZATION

bool synchronizeClock() {

    clockData.attempted = true;
    clockData.attemptedAt = millis();

    if (WiFi.status() != WL_CONNECTED) {
        return false;
    }

    String url =
        "https://timeapi.io/api/Time/current/zone?timeZone=";

    url += urlEncode(TIME_ZONE);

    BearSSL::WiFiClientSecure client;

    // Classroom testing only
    client.setInsecure();

    HTTPClient http;

    http.setTimeout(8000);
    http.setReuse(false);

    if (!http.begin(client, url)) {
        return false;
    }

    int code = http.GET();

    if (code != 200) {

        Serial.print("TimeAPI failure: ");
        Serial.println(code);

        http.end();
        return false;
    }

    String body = http.getString();

    http.end();

    StaticJsonDocument<768> data;

    if (deserializeJson(data, body)) {

        Serial.println("Invalid TimeAPI JSON");
        return false;
    }

    int y = data["year"] | 0;
    int mo = data["month"] | 0;
    int d = data["day"] | 0;

    int hh = data["hour"] | -1;
    int mm = data["minute"] | -1;
    int ss = data["seconds"] | -1;
    int ms = data["milliSeconds"] | 0;

    if (y < 2020 ||
        mo < 1 || mo > 12 ||
        d < 1 || d > 31 ||
        hh < 0 || hh > 23 ||
        mm < 0 || mm > 59 ||
        ss < 0 || ss > 59 ||
        ms < 0 || ms > 999) {

        return false;
    }

    clockData.localSeconds =
        daysFromCivil(y, mo, d) * 86400LL
        + hh * 3600
        + mm * 60
        + ss;

    clockData.milliseconds = ms;
    clockData.syncedAt = millis();
    clockData.valid = true;

    Serial.printf(
        "Time synchronized: %04d-%02d-%02d "
        "%02d:%02d:%02d.%03d\n",
        y, mo, d, hh, mm, ss, ms
    );

    Serial.print("Zone: ");
    Serial.println(TIME_ZONE);

    return true;
}

// 14. PERIODIC CLOCK SERVICE

void serviceClock() {

    if (WiFi.status() != WL_CONNECTED) {
        return;
    }

    uint32_t now = millis();

    if (clockData.valid &&
        (uint32_t)(now - clockData.syncedAt)
        < CLOCK_RESYNC_MS) {

        return;
    }

    if (clockData.attempted &&
        (uint32_t)(now - clockData.attemptedAt)
        < CLOCK_FAILED_RETRY_MS) {

        return;
    }

    synchronizeClock();
}

// 15. GENERATE TIMESTAMP

bool getTimestamp(
    char* out,
    size_t length
) {

    if (!clockData.valid) {
        return false;
    }

    uint64_t elapsed =
        (uint32_t)(millis() - clockData.syncedAt)
        + (uint64_t)clockData.milliseconds;

    time_t seconds =
        (time_t)(
            clockData.localSeconds
            + elapsed / 1000ULL
        );

    struct tm parts;

    if (gmtime_r(&seconds, &parts) == nullptr) {
        return false;
    }

    snprintf(
        out,
        length,
        "%04d-%02d-%02d %02d:%02d:%02d.%03u",

        parts.tm_year + 1900,
        parts.tm_mon + 1,
        parts.tm_mday,

        parts.tm_hour,
        parts.tm_min,
        parts.tm_sec,

        (unsigned)(elapsed % 1000ULL)
    );

    return true;
}

// 16. SERIAL INPUT

void processSerial() {

    if (!Serial.available()) {
        return;
    }

    String cmd =
        Serial.readStringUntil('\n');

    cmd.trim();

    if (cmd == "s" || cmd == "S") {

        printNetwork();
        return;
    }

    if (cmd == "r" || cmd == "R") {

        synchronizeClock();
        return;
    }

    if (cmd == "t" || cmd == "T") {

        char stamp[28];

        if (getTimestamp(stamp, sizeof(stamp))) {
            Serial.println(stamp);
        } else {
            Serial.println("Clock not synchronized");
        }

        return;
    }

    Serial.println("Unknown serial command");
}

// 17. INITIALIZE SWITCH

void initSwitch(InputSwitch& sw) {

    pinMode(sw.pin, INPUT_PULLUP);

    sw.raw = digitalRead(sw.pin);
    sw.stable = sw.raw;
    sw.changedAt = millis();
}

// 18. DEBOUNCE AND DETECT ACTIVATION

bool newActivation(InputSwitch& sw) {

    bool raw = digitalRead(sw.pin);

    uint32_t now = millis();

    if (raw != sw.raw) {

        sw.raw = raw;
        sw.changedAt = now;
    }

    if (raw != sw.stable &&
        (uint32_t)(now - sw.changedAt)
        >= DEBOUNCE_MS) {

        sw.stable = raw;

        // Only HIGH -> LOW
        return sw.stable == LOW;
    }

    return false;
}

// 19. READ THE SELECTED NODE'S SENSOR

bool sampleForNode(Measurement& msg) {

    if (msg.hasDHT) {

        // NODE_1: DHT11 ONLY

        msg.temperature = dht.readTemperature();
        msg.humidity = dht.readHumidity();

        if (isnan(msg.temperature) ||
            isnan(msg.humidity)) {

            Serial.println(
                "ERROR: DHT11 read failed"
            );

            return false;
        }

        Serial.printf(
            "DHT11: %.2f C, %.2f %%RH\n",
            msg.temperature,
            msg.humidity
        );

    } else {

        // NODE_2: HW-506 ONLY

        hw506.requestTemperatures();

        msg.temperature2 =
            hw506.getTempCByIndex(0);

        if (isnan(msg.temperature2) ||
            msg.temperature2 ==
                DEVICE_DISCONNECTED_C) {

            Serial.println(
                "ERROR: HW-506 read failed"
            );

            return false;
        }

        Serial.printf(
            "HW-506: %.2f C\n",
            msg.temperature2
        );
    }

    return true;
}

// 20. QUEUE MEASUREMENT

void queueMeasurement(
    const char* node,
    bool hasDHT
) {

    if (queueCount >= QUEUE_CAPACITY) {

        Serial.println(
            "Queue full: message not captured"
        );

        return;
    }

    if (!clockData.valid) {

        Serial.println(
            "Clock not synchronized"
        );

        return;
    }

    Measurement msg = {};

    snprintf(
        msg.node,
        sizeof(msg.node),
        "%s",
        node
    );

    snprintf(
        msg.zone,
        sizeof(msg.zone),
        "%s",
        TIME_ZONE
    );

    msg.hasDHT = hasDHT;

    if (!sampleForNode(msg)) {
        return;
    }

    if (!getTimestamp(
            msg.timestamp,
            sizeof(msg.timestamp))) {

        return;
    }

    msg.attempted = false;
    msg.lastAttempt = 0;

    queueData[queueCount++] = msg;

    Serial.printf(
        "Queued %s at %s (%u pending)\n",
        msg.node,
        msg.timestamp,
        queueCount
    );
}

// 21. NODE_1 PUSHBUTTON

void checkButton() {

    if (newActivation(button)) {

        Serial.println();
        Serial.println(
            "NODE_1: Pushbutton -> DHT11"
        );

        queueMeasurement("node_1", true);
    }
}

// 22. NODE_2 TILT SWITCH

void checkTilt() {

    if (newActivation(tilt)) {

        Serial.println();
        Serial.println(
            "NODE_2: Tilt -> HW-506"
        );

        queueMeasurement("node_2", false);
    }
}

// 23. HTTP TRANSMISSION

bool transmit(const Measurement& msg) {

    String url =
        String(SERVER_URL)
        + "?nodeId=" + urlEncode(msg.node)
        + "&timeReceived=" + urlEncode(msg.timestamp)
        + "&timeZone=" + urlEncode(msg.zone);

    if (msg.hasDHT) {

        // Node_1 sends DHT11 readings only

        url += "&nodeTemp=";
        url += String(msg.temperature, 2);

        url += "&nodeHum=";
        url += String(msg.humidity, 2);

    } else {

        // Node_2 sends HW-506 reading only

        url += "&nodeTemp2=";
        url += String(msg.temperature2, 2);
    }

    BearSSL::WiFiClientSecure client;

    // Classroom testing only
    client.setInsecure();

    HTTPClient http;

    http.setTimeout(8000);
    http.setReuse(false);

    if (!http.begin(client, url)) {
        return false;
    }

    http.addHeader(
        "X-IoT-Key",
        API_TOKEN
    );

    int status = http.GET();

    Serial.print("HTTP status: ");
    Serial.println(status);

    if (status != 200 && status != 201) {

        if (status < 0) {

            Serial.println(
                HTTPClient::errorToString(status)
            );

        } else {

            Serial.println(
                "Server returned an error"
            );
        }

        http.end();
        return false;
    }

    String body = http.getString();

    http.end();

    Serial.print("Response: ");
    Serial.println(body);

    StaticJsonDocument<256> json;

    if (deserializeJson(json, body)) {
        return false;
    }

    const char* result =
        json["status"] | "";

    bool accepted =
        json["accepted"] | false;

    return accepted &&
        (
            strcmp(result, "inserted") == 0 ||
            strcmp(result, "duplicate") == 0
        );
}

// 24. REMOVE ACKNOWLEDGED MESSAGE

void removeMessage(uint8_t index) {

    for (uint8_t i = index + 1;
         i < queueCount;
         ++i) {

        queueData[i - 1] = queueData[i];
    }

    --queueCount;
}

// 25. AUTOMATIC RETRANSMISSION

void serviceRetries() {

    if (WiFi.status() != WL_CONNECTED ||
        queueCount == 0) {

        return;
    }

    uint32_t now = millis();

    for (uint8_t i = 0;
         i < queueCount;
         ++i) {

        Measurement& msg = queueData[i];

        if (msg.attempted &&
            (uint32_t)(now - msg.lastAttempt)
            < MESSAGE_RETRY_MS) {

            continue;
        }

        msg.attempted = true;
        msg.lastAttempt = now;

        Serial.printf(
            "Sending %s, ORIGINAL time %s\n",
            msg.node,
            msg.timestamp
        );

        if (transmit(msg)) {

            Serial.println(
                "SERVER ACKNOWLEDGED"
            );

            removeMessage(i);

        } else {

            Serial.println(
                "Not acknowledged; retry after 30 seconds"
            );
        }

        break;
    }
}

// 26. SETUP

void setup() {

    Serial.begin(115200);
    Serial.setTimeout(30);

    delay(1000);

    Serial.println();
    Serial.println(
        "EE470 Node-Specific ESP8266 v1.6"
    );

    dht.begin();
    hw506.begin();

    initSwitch(button);
    initSwitch(tilt);

    Serial.println("Time zone: America/Los_Angeles (fixed)");
    Serial.println("Commands: s=network, t=current time, r=resync");

    WiFi.mode(WIFI_STA);
    WiFi.persistent(false);
    WiFi.setAutoReconnect(true);

    WiFi.begin(
        WIFI_SSID,
        WIFI_PASSWORD
    );

    wifiLastAttempt = millis();
}

// 27. MAIN LOOP

void loop() {

    processSerial();

    serviceWiFi();

    serviceClock();

    checkButton();

    checkTilt();

    serviceRetries();

    yield();
}
