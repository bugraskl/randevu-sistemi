<?php
/**
 * Telefon numarası yardımcıları.
 *
 * Standart kayıt biçimi: 0 + 10 haneli ulusal numara, boşluksuz (ör. 05372212323).
 * Kabul edilen giriş örnekleri (hepsi 05372212323 olur):
 *   +905372212323 · +90 (537) 221 23 23 · 0090 537 221 23 23 · 905372212323
 *   05372212323 · 0 537 221 23 23 · 5372212323 · +90 0537 221 23 23
 *
 * Aynı kurallar tarayıcıda assets/js/app.js içindeki normalizePhone ile uygulanır;
 * değiştirirseniz ikisini birlikte güncelleyin.
 */

const PHONE_FORMAT_HINT = 'Telefon numarası 10 haneli olmalı; ör. 0537 221 23 23 ya da +90 537 221 23 23.';

/**
 * Numarayı standart biçime çevirir. Geçersizse null döner.
 */
function normalizePhone($raw) {
    $digits = preg_replace('/\D+/', '', (string) $raw);
    if ($digits === '') {
        return null;
    }

    // Uluslararası çıkış kodu: 0090...
    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }
    // Ülke kodu: 90 + (0?) + 10 hane
    if (strlen($digits) >= 12 && strpos($digits, '90') === 0) {
        $digits = substr($digits, 2);
    }
    // Baştaki 0
    if (strlen($digits) === 11 && $digits[0] === '0') {
        $digits = substr($digits, 1);
    }

    // Türkiye ulusal numarası: 10 hane, 2-5 ya da 8 ile başlar (5xx cep, 2xx-4xx sabit hat)
    if (!preg_match('/^[2-58][0-9]{9}$/', $digits)) {
        return null;
    }

    return '0' . $digits;
}

/**
 * Arama için telefon parçası: en az 3 rakam içeren bir terimden,
 * kayıtlı biçimde aranabilecek rakam dizisini çıkarır. Uygun değilse null.
 */
function phoneSearchDigits($term) {
    $term = (string) $term;
    $digits = preg_replace('/\D+/', '', $term);
    if (strlen($digits) < 3) {
        return null;
    }
    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }
    if ((strpos($term, '+') !== false || strlen($digits) >= 12) && strpos($digits, '90') === 0) {
        $digits = substr($digits, 2);
    }
    $digits = ltrim($digits, '0');
    return strlen($digits) >= 3 ? $digits : null;
}

/**
 * Okunaklı gösterim: 0537 221 23 23 (geçersizse olduğu gibi döner).
 */
function formatPhoneDisplay($phone) {
    $normalized = normalizePhone($phone);
    if ($normalized === null) {
        return (string) $phone;
    }
    return substr($normalized, 0, 4) . ' ' . substr($normalized, 4, 3) . ' ' . substr($normalized, 7, 2) . ' ' . substr($normalized, 9, 2);
}
