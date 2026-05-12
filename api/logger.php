<?php
/**
 * This function logs a visitor's action to the database.
 * It captures IP, approximates location via a free API, and parses the User-Agent string.
 */
function log_visitor_action(PDO $pdo, string $action) {
    // --- DEVICE EXEMPTION LOGIC ---
    // If you visit any page with ?exempt_device=1 in the URL, it sets a 10-year cookie.
    if (isset($_GET['exempt_device']) && $_GET['exempt_device'] === '1') {
        setcookie('exempt_from_logging', '1', time() + (86400 * 365 * 10), '/'); // Expires in 10 years
        return null;
    }

    // If you visit with ?unexempt_device=1, it deletes the cookie.
    if (isset($_GET['unexempt_device']) && $_GET['unexempt_device'] === '1') {
        setcookie('exempt_from_logging', '', time() - 3600, '/'); // Expire in the past
        unset($_COOKIE['exempt_from_logging']); // Unset for the current request
    }

    // If this device has the exemption cookie, skip logging entirely.
    if (isset($_COOKIE['exempt_from_logging'])) {
        return null;
    }

    try {
        // 1. Get Device Info from User-Agent string.
        // Note: This is a basic parser. For production, a dedicated library like `whichbrowser/parser` is more robust.
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $os = 'Unknown OS';
        if (preg_match('/windows nt 10/i', $user_agent)) $os = 'Windows';
        elseif (preg_match('/macintosh|mac os x/i', $user_agent)) $os = 'macOS';
        elseif (preg_match('/android/i', $user_agent)) $os = 'Android';
        elseif (preg_match('/iphone|ipad/i', $user_agent)) $os = 'iOS';
        elseif (preg_match('/linux/i', $user_agent)) $os = 'Linux';

        $browser = 'Unknown Browser';
        if (preg_match('/edg/i', $user_agent)) $browser = 'Edge';
        elseif (preg_match('/chrome/i', $user_agent) && !preg_match('/chromium/i', $user_agent)) $browser = 'Chrome';
        elseif (preg_match('/safari/i', $user_agent) && !preg_match('/chrome/i', $user_agent)) $browser = 'Safari';
        elseif (preg_match('/firefox/i', $user_agent)) $browser = 'Firefox';

        $device_string = "$browser / $os";

        // 2. Insert the log into the database and return the row ID to track time spent
        $stmt = $pdo->prepare("INSERT INTO visitor_logs (device, action, time_spent_seconds) VALUES (:device, :action, 0) RETURNING id");
        $stmt->execute(['device' => $device_string, 'action' => $action]);
        
        return $stmt->fetchColumn();

    } catch (Exception $e) {
        // Fail silently to ensure logging errors don't break the main application.
        error_log("Visitor logging failed: " . $e->getMessage());
        return null;
    }
}
?>