-- EE 470 - Part 1.2: MariaDB practice
-- Run on a fresh database, or run commands individually as in the tutorial.
-- It is not intended to be run twice: the CREATE commands will conflict
-- with existing tables, and the INSERT commands can duplicate IDs.
-- In phpMyAdmin, first select YOUR database.

-- Example of CHECK constraints.
CREATE TABLE t2 (
    name VARCHAR(30) CHECK (CHAR_LENGTH(name) > 2),
    start_date DATE,
    end_date DATE CHECK (
        start_date IS NULL OR end_date IS NULL OR start_date < end_date
    )
);

INSERT INTO t2 (name, start_date, end_date)
VALUES ('Ione', '2003-12-15', '2014-11-09');

-- These are EXPECTED TO FAIL and are intentionally commented out:
-- INSERT INTO t2 (name, start_date, end_date)
-- VALUES ('Io', '2003-12-15', '2014-11-09'); -- Name too short.

INSERT INTO t2 (name, start_date, end_date)
VALUES ('Ione', NULL, '2014-11-09');

-- INSERT INTO t2 (name, start_date, end_date)
-- VALUES ('Ione', '2015-12-15', '2014-11-09'); -- End before start.

-- AUTO_INCREMENT example.
CREATE TABLE product (
    product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(200),
    price INT
);
INSERT INTO product (product_name, price) VALUES ('Desk chair', 50);

-- Initial BOOK example for Section 1.1 screenshot.
CREATE TABLE Book (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (id)
);
INSERT INTO Book (id, name)
VALUES (1, 'MariaDB Book'), (2, 'SQLDB Book');
SELECT * FROM Book;

-- After saving the Section 1.1 screenshot, reset tutorial example rows.
-- WARNING: this deletes ALL current records in Book.
TRUNCATE TABLE Book;

-- Final five books for Section 1.2.
INSERT INTO Book (id, name) VALUES
    (1, 'MariaDB Book1'),
    (2, 'MariaDB Book2'),
    (3, 'MariaDB Book3'),
    (4, 'MariaDB Book4'),
    (5, 'MariaDB Book5');

-- Prices have the same numeric ID as their corresponding books.
CREATE TABLE Price (
    id INT NOT NULL AUTO_INCREMENT,
    price FLOAT NOT NULL,
    PRIMARY KEY (id)
);
INSERT INTO Price (id, price) VALUES
    (1, 251),
    (2, 252),
    (3, 223),
    (4, 194),
    (5, 305);

-- Update records as instructed in the course tutorial.
UPDATE Price SET price = 250 WHERE id = 1;
UPDATE Book SET name = 'MariaDB Book1a' WHERE id = 1;

-- Display the individual tables (save screenshots).
SELECT * FROM Book;
SELECT * FROM Price;

-- Extra practice queries from the course tutorial.
SELECT * FROM Price WHERE price < 250;
SELECT * FROM Price WHERE id > 3 OR price < 250;
SELECT name FROM Book WHERE name LIKE '%4';
SELECT * FROM Price WHERE CAST(price AS CHAR) LIKE '2%' ORDER BY price;

-- INNER JOIN: combine the book name and the matching price (save screenshot).
SELECT Book.name, Price.price
FROM Book
INNER JOIN Price ON Book.id = Price.id;

-- In the provided tutorial, inserting Price id=1 a second time would fail
-- because id=1 is already present. Do not run that duplicate INSERT.
-- This would safely add and remove an extra row if you want to practice DELETE:
-- INSERT INTO Price (id, price) VALUES (6, 251);
-- DELETE FROM Price WHERE id = 6;
