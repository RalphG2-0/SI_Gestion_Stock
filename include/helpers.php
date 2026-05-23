<?php
/**
 * Générateur et validateur de tokens CSRF
 */

class CSRFToken {
    public static function generate() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validate($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function field() {
        $token = self::generate();
        return "<input type='hidden' name='" . CSRF_TOKEN_NAME . "' value='" . htmlspecialchars($token) . "'>";
    }
}

/**
 * Validateurs côté serveur
 */
class Validator {
    public static function validateInt($value) {
        if (!is_numeric($value) || (int)$value != $value) {
            return false;
        }
        return (int)$value;
    }
    
    public static function validateFloat($value) {
        if (!is_numeric($value)) {
            return false;
        }
        return (float)$value;
    }
    
    public static function validatePositive($value) {
        $num = self::validateFloat($value);
        if ($num === false || $num < 0) {
            return false;
        }
        return $num;
    }
    
    public static function validateString($value, $minLength = 1, $maxLength = 255) {
        if (!is_string($value)) {
            return false;
        }
        $len = strlen($value);
        if ($len < $minLength || $len > $maxLength) {
            return false;
        }
        return trim($value);
    }
    
    public static function validateEmail($value) {
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : false;
    }
    
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date ? $date : false;
    }
}

/**
 * Classe pour les réponses messages flash
 */
class Message {
    public static function success($msg) {
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => $msg];
    }
    
    public static function error($msg) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => $msg];
    }
    
    public static function display() {
        if (!isset($_SESSION['flash_message'])) {
            return '';
        }
        $msg = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        $type = htmlspecialchars($msg['type']);
        $text = htmlspecialchars($msg['text']);
        return "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                    {$text}
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
    }
}
?>
