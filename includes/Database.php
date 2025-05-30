<?php
require_once __DIR__ . '/../config/env.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    private $conn;

    public function __construct() {
        try {
            // Environment değişkenlerinden database bilgilerini al
            $this->host = EnvConfig::get('DB_HOST', 'localhost');
            $this->db_name = EnvConfig::get('DB_NAME');
            $this->username = EnvConfig::get('DB_USERNAME');
            $this->password = EnvConfig::get('DB_PASSWORD', '');
            $this->charset = EnvConfig::get('DB_CHARSET', 'utf8mb4');
            
            // Gerekli alanların kontrolü
            if (empty($this->db_name)) {
                throw new Exception('Veritabanı adı belirtilmemiş. Lütfen env dosyasında DB_NAME değerini kontrol edin.');
            }
            
            if (empty($this->username)) {
                throw new Exception('Veritabanı kullanıcı adı belirtilmemiş. Lütfen env dosyasında DB_USERNAME değerini kontrol edin.');
            }

            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(Exception $e) {
            // Debug modu açıksa detaylı hata göster
            $errorMessage = "Veritabanı bağlantı hatası: " . $e->getMessage();
            if (EnvConfig::isDebug()) {
                $errorMessage .= "\nHost: {$this->host}\nDatabase: {$this->db_name}\nUsername: {$this->username}";
            }
            die($errorMessage);
        }
    }

    public function getConnection() {
        return $this->conn;
    }
} 