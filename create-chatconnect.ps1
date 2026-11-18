
# ==========================================
# CHATCONNECT - DOCKER + SQLITE GENERATOR
# ==========================================

$project = "chatconnect"

# Create project directory
New-Item -ItemType Directory -Path $project -Force | Out-Null

# Folder structure
$folders = @(
    "config",
    "user",
    "chat",
    "meetings",
    "admin",
    "assets",
    "assets/css",
    "assets/js",
    "assets/images",
    "uploads",
    "database",
    "websocket"
)

foreach ($folder in $folders) {
    New-Item -ItemType Directory -Path "$project/$folder" -Force | Out-Null
}

# Project files
$files = @(
    "index.php",
    "register.php",
    "login.php",
    "logout.php",

    "config/database.php",

    "user/dashboard.php",
    "user/profile.php",
    "user/contacts.php",

    "chat/private_chat.php",
    "chat/group_chat.php",
    "chat/send_message.php",
    "chat/fetch_messages.php",

    "meetings/video_call.php",
    "meetings/signaling.php",

    "admin/dashboard.php",
    "admin/manage_users.php",

    "assets/css/style.css",
    "assets/js/script.js",

    "database/chatconnect.sql",
    "database/.htaccess",

    "websocket/server.php",

    "Dockerfile",
    "docker-compose.yml",
    ".dockerignore",
    "README.md"
)

foreach ($file in $files) {
    $path = "$project/$file"

    if (!(Test-Path $path)) {
        New-Item -ItemType File -Path $path -Force | Out-Null
    }
}

# ==========================================
# DOCKERFILE
# ==========================================

@'
FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/lib/chatconnect \
    && chown -R www-data:www-data /var/lib/chatconnect

WORKDIR /var/www/html

EXPOSE 80
'@ | Set-Content "$project/Dockerfile"

# ==========================================
# DOCKER COMPOSE
# ==========================================

@'
services:
  web:
    build: .
    container_name: chatconnect_web
    ports:
      - "8080:80"
    volumes:
      - .:/var/www/html
      - chatconnect_data:/var/lib/chatconnect
    environment:
      DB_PATH: /var/lib/chatconnect/chatconnect.sqlite
      TZ: Asia/Manila
    restart: unless-stopped

volumes:
  chatconnect_data:
'@ | Set-Content "$project/docker-compose.yml"

# ==========================================
# DATABASE CONNECTION - SQLITE
# ==========================================

@'
<?php

$dbPath = getenv("DB_PATH") ?: 
    __DIR__ . "/../database/chatconnect.sqlite";

$dbDirectory = dirname($dbPath);

if (!is_dir($dbDirectory)) {
    mkdir($dbDirectory, 0775, true);
}

try {
    $pdo = new PDO("sqlite:" . $dbPath);

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

    $pdo->exec("PRAGMA foreign_keys = ON");
    $pdo->exec("PRAGMA journal_mode = WAL");

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

?>
'@ | Set-Content "$project/config/database.php"

# ==========================================
# DATABASE SQL
# ==========================================

@'
PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fullname TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    profile_image TEXT DEFAULT 'default.png',
    status TEXT DEFAULT 'offline'
        CHECK(status IN ('online', 'offline')),
    role TEXT DEFAULT 'user'
        CHECK(role IN ('user', 'admin')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL
        CHECK(type IN ('private', 'group')),
    name TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS conversation_members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(conversation_id, user_id),
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
        ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    sender_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    message_type TEXT DEFAULT 'text'
        CHECK(message_type IN ('text', 'image', 'file')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
        ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    message_id INTEGER NOT NULL,
    filename TEXT NOT NULL,
    filepath TEXT NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id)
        ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS call_sessions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL,
    caller_id INTEGER NOT NULL,
    receiver_id INTEGER NOT NULL,
    status TEXT DEFAULT 'ringing',
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME,
    FOREIGN KEY (conversation_id) REFERENCES conversations(id)
        ON DELETE CASCADE,
    FOREIGN KEY (caller_id) REFERENCES users(id)
        ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id)
        ON DELETE CASCADE
);
'@ | Set-Content "$project/database/chatconnect.sql"

# ==========================================
# TEST DATABASE CONNECTION
# ==========================================

@'
<?php

require_once __DIR__ . "/config/database.php";

echo "<h1>ChatConnect</h1>";
echo "<p>Docker and SQLite connection successful!</p>";

?>
'@ | Set-Content "$project/index.php"

# ==========================================
# PROTECT DATABASE SQL FILE
# ==========================================

@'
Require all denied
'@ | Set-Content "$project/database/.htaccess"

# ==========================================
# DOCKER IGNORE
# ==========================================

@'
.git
.vscode
.DS_Store
*.log
database/*.sqlite
database/*.sqlite-wal
database/*.sqlite-shm
'@ | Set-Content "$project/.dockerignore"

# ==========================================
# README
# ==========================================

@'
# ChatConnect

PHP + SQLite + Docker messaging application.

## Start application

docker compose up --build -d

## Open application

http://localhost:8080

## Stop application

docker compose down

## View logs

docker compose logs -f web

## SQLite database

Stored persistently in Docker volume:
chatconnect_data
'@ | Set-Content "$project/README.md"

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host " CHATCONNECT DOCKER SQLITE CREATED!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Project: $project" -ForegroundColor Cyan
Write-Host ""
tree "$project" /F
