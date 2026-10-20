# EE 470 - Internet of Things: MariaDB Database Assignment

Sonoma State University | Student: Mehdi Kacem

This project includes SQL practice examples, an IoT sensor database, and a PHP webpage that displays the sensor tables and averages.

## Repository files

```text
EE470-IoT-Database/
├── README.md
├── .gitignore
├── index.html                  # Main course webpage, links to database.php
├── database.php               # Read and display sensor data + node_1 averages
├── db_config.example.php      # Database configuration template (no passwords)
└── sql/
    ├── 00_inspection.sql       # SHOW DATABASES, SHOW TABLES, etc.
    ├── 01_practice_book_price.sql
    ├── 02_sensor_database.sql
    ├── 03_validation_tests.sql
    └── 04_optional_book2_view.sql
```

## Setup

1. Create/select a MariaDB database in the hosting control panel and open it in phpMyAdmin.
2. **If you have already created the tables for the lab, do not run these setup scripts again.** The scripts document the SQL commands used and would conflict with existing tables/data.
3. On a *new, empty practice database*, run `sql/01_practice_book_price.sql` and then `sql/02_sensor_database.sql` in phpMyAdmin (or execute statements individually).
4. `sql/03_validation_tests.sql` contains intentional failing INSERT commands. Uncomment and run one at a time to demonstrate constraints.
5. `sql/04_optional_book2_view.sql` is an extra instructor-tutorial example, not required for the sensor view.
6. Copy `db_config.example.php` to `db_config.php` and enter the **real** host/database name/username/password from your hosting provider. Do not put the real configuration file on GitHub.
7. Upload `index.html`, `database.php`, and your PRIVATE `db_config.php` to the website directory (typically `public_html`).
8. Open `https://YOUR_DOMAIN/database.php` and verify five registered sensors, 20 readings, and averages for `node_1`.
9. In `index.html`, replace `YOUR_USERNAME` in the GitHub URL with your own GitHub username.

## Key database structure

- `sensor_register`: `node_name` (primary key), `manufacturer`, `longitude`, `latitude`.
- `sensor_data`: `node_name` (foreign key), `time_received`, `temperature`, `humidity`.
- `sensor_data` uses `(node_name, time_received)` as a *composite primary key*. This allows repeated measurements from a node but refuses duplicate timestamps for that node.
- `sensor_combined` is an INNER JOIN view that includes node information alongside sensor measurements.
- Temperature accepted range: -10 to 100 degrees C; humidity range: 0 to 100 percent.

## Expected results

- `Book` table: five books; `Price` table: five corresponding prices.
- `Book` / `Price` INNER JOIN: five matching records.
- Five registered sensor nodes with **four readings each**, starting at 2022-10-01 11:00, spaced by 30 minutes.
- An INSERT of an unregistered node such as `node_6` is rejected by the foreign key (error #1452).

## Assignment coverage / pending work

- Included: Part 1 SQL tutorial commands, Book/Price INNER JOIN, sensor tables and constraints, sensor VIEW, validation examples.
- Included as code (not verified against your live hosting account): homepage and Part 2 read-only PHP display with average values.
- **Not included / not yet completed:** Part 2 live GET insertion endpoint, cURL/Postman demonstrations, Chart.js graph, JSON export and Base64-decoding endpoint. Do not claim these features are done until implemented and demonstrated.

## GitHub upload

Create an empty GitHub repository named `EE470-IoT-Database`. You can upload the contents of this folder using GitHub's **Add file > Upload files**. Ensure `db_config.php` (the private file you created from the template) is **never** included.

Alternative using Git from a terminal in the project folder:

```bash
git init
git add .
git commit -m "Add EE470 IoT database assignment SQL and PHP"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/EE470-IoT-Database.git
git push -u origin main
```

## Reference

- [Course MariaDB tutorial](https://github.com/faridfarahmand/SSU/blob/main/IoTCourse/dataBase/MariaDB_Tutorial.sql)
