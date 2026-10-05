
#include <Wire.h>
#include <Adafruit_INA260.h>

Adafruit_INA260 ina260;

#define SDA_PIN D2
#define SCL_PIN D1

void setup() {
  Serial.begin(115200);
  delay(1000);

  // Initialize I2C on ESP8266
  Wire.begin(SDA_PIN, SCL_PIN);

  // Initialize INA260
  if (!ina260.begin(0x40, &Wire)) {
    Serial.println("INA260 not detected!");
    while (1) {
      delay(1000);
    }
  }

  Serial.println("INA260 initialized.");
}

void loop() {

  // Read sensor values
  float voltage = ina260.readBusVoltage() / 1000.0;
  float current = ina260.readCurrent() / 1000.0;
  float power   = ina260.readPower() / 1000.0;

  // Display measurements
  Serial.print("Voltage: ");
  Serial.print(voltage, 3);
  Serial.println(" V");

  Serial.print("Current: ");
  Serial.print(current, 3);
  Serial.println(" A");

  Serial.print("Power: ");
  Serial.print(power, 3);
  Serial.println(" W");

  Serial.println("----------------");

  delay(60000);
}
