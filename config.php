<?php
$servername = "localhost";
$username = "root"; // CHANGE TO YOUR DB USER
$password = "Technoblade404$"; // CHANGE TO YOUR DB PASS
$dbname = "dashixc2_forum"; // CREATE THIS DB

$conn = mysqli_connect($servername, $username, $password, $dbname);

// CREATE TABLES (RUN ONCE)
$sql = "
CREATE DATABASE IF NOT EXISTS dashixc2_forum;
USE dashixc2_forum;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    content TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT,
    content TEXT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES threads(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
";
mysqli_multi_query($conn, $sql);
?>