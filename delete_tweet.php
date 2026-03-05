<?php
// delete_tweet.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['id'])) {
    $tweet_id = intval($_GET['id']);
    
    // Check if the tweet belongs to the current user
    $check_sql = "SELECT * FROM tweets WHERE id = ? AND user_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $tweet_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Get tweet image path before deletion
        $tweet = $check_result->fetch_assoc();
        $tweet_image = $tweet['image'];
        
        // Delete the tweet (cascade will handle likes and comments)
        $delete_sql = "DELETE FROM tweets WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $tweet_id);
        
        if ($delete_stmt->execute()) {
            // Delete tweet image file if exists
            if (!empty($tweet_image) && file_exists($tweet_image)) {
                @unlink($tweet_image);
            }
        }
    }
}

// Get active tab and user_id for redirection
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'tweets';
$profile_user_id = isset($_GET['user_id']) ? $_GET['user_id'] : $user_id;

// Redirect back to profile page with active tab
redirect("profile.php?user_id=$profile_user_id&tab=$tab");
?>
