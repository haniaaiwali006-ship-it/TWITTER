<?php
// config.php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'rsoa_rsoa278_9');
define('DB_PASS', '654321#');
define('DB_NAME', 'rsoa_rsoa278_9');

// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Function to redirect
function redirect($url) {
    echo "<script>window.location.href = '$url';</script>";
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get user data
function getUserData($conn, $user_id) {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Format time difference
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    
    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "just now";
    } else if ($minutes <= 60) {
        return "$minutes min ago";
    } else if ($hours <= 24) {
        return "$hours h ago";
    } else if ($days <= 7) {
        return "$days d ago";
    } else if ($weeks <= 4.3) {
        return "$weeks w ago";
    } else if ($months <= 12) {
        return "$months mo ago";
    } else {
        return "$years y ago";
    }
}
?>
