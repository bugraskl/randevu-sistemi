<?php
require_once __DIR__ . '/../config/env.php';

class SessionManager {
    private $db;
    private $tokenExpiry = 30; // Token geçerlilik süresi (gün)

    public function __construct($db) {
        $this->db = $db;
    }

    public function createRememberToken($userId) {
        // Yeni token oluştur
        $token = bin2hex(random_bytes(32));
        $deviceInfo = $_SERVER['HTTP_USER_AGENT'];
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->tokenExpiry} days"));

        // Yeni token'ı kaydet
        $stmt = $this->db->prepare("INSERT INTO remember_tokens (user_id, token, device_info, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $token, $deviceInfo, $expiresAt]);

        return $token;
    }

    public function validateRememberToken($token) {
        try {
            $stmt = $this->db->prepare("SELECT user_id FROM remember_tokens WHERE token = ? AND expires_at > NOW()");
            $stmt->execute([$token]);
            
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Token'ı yenile
                $this->refreshToken($token);
                return $row['user_id'];
            }
        } catch (PDOException $e) {
            error_log("Token validation error: " . $e->getMessage());
        }
        return false;
    }

    private function refreshToken($token) {
        try {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->tokenExpiry} days"));
            $stmt = $this->db->prepare("UPDATE remember_tokens SET expires_at = ? WHERE token = ?");
            $stmt->execute([$expiresAt, $token]);
        } catch (PDOException $e) {
            error_log("Token refresh error: " . $e->getMessage());
        }
    }

    private function cleanupOldTokens($userId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE user_id = ? AND expires_at <= NOW()");
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Token cleanup error: " . $e->getMessage());
        }
    }

    public function deleteRememberToken($token) {
        try {
            $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE token = ?");
            $stmt->execute([$token]);
            
            // Environment'den domain bilgisini al
            $domain = EnvConfig::get('APP_DOMAIN', 'localhost');
            
            // Cookie ayarları
            $cookieOptions = [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => $domain,
                'secure' => EnvConfig::get('APP_ENV') === 'production',
                'httponly' => true,
                'samesite' => 'Lax'
            ];
            
            setcookie('remember_token', '', $cookieOptions);
        } catch (PDOException $e) {
            error_log("Token deletion error: " . $e->getMessage());
        }
    }

    public function getUserDevices($userId) {
        try {
            $stmt = $this->db->prepare("SELECT id, device_info, created_at, expires_at FROM remember_tokens WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get devices error: " . $e->getMessage());
            return [];
        }
    }

    public function deleteDevice($tokenId) {
        try {
            $stmt = $this->db->prepare("DELETE FROM remember_tokens WHERE id = ?");
            $stmt->execute([$tokenId]);
        } catch (PDOException $e) {
            error_log("Device deletion error: " . $e->getMessage());
        }
    }
} 