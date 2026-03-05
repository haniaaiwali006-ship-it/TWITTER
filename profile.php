<?php
// profile.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user = getUserData($conn, $user_id);

// Get profile user from URL
$profile_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $user_id;
$profile_user = getUserData($conn, $profile_user_id);

// Check if viewing own profile
$is_own_profile = ($profile_user_id == $user_id);

// Get active tab from URL
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'tweets';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_own_profile) {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $bio = mysqli_real_escape_string($conn, $_POST['bio']);
    
    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        $image_name = time() . '_' . basename($_FILES['profile_pic']['name']);
        $target_file = $target_dir . $image_name;
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES['profile_pic']['tmp_name']);
        if($check !== false) {
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                // Delete old profile picture if not default
                if ($profile_user['profile_pic'] != 'default_profile.png') {
                    @unlink($target_dir . $profile_user['profile_pic']);
                }
                $profile_pic = $image_name;
            } else {
                $profile_pic = $profile_user['profile_pic'];
            }
        } else {
            $profile_pic = $profile_user['profile_pic'];
        }
    } else {
        $profile_pic = $profile_user['profile_pic'];
    }
    
    // Handle cover picture upload
    if (isset($_FILES['cover_pic']) && $_FILES['cover_pic']['error'] == 0) {
        $target_dir = "uploads/";
        $image_name = time() . '_' . basename($_FILES['cover_pic']['name']);
        $target_file = $target_dir . $image_name;
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES['cover_pic']['tmp_name']);
        if($check !== false) {
            if (move_uploaded_file($_FILES['cover_pic']['tmp_name'], $target_file)) {
                // Delete old cover picture if not default
                if ($profile_user['cover_pic'] != 'default_cover.jpg') {
                    @unlink($target_dir . $profile_user['cover_pic']);
                }
                $cover_pic = $image_name;
            } else {
                $cover_pic = $profile_user['cover_pic'];
            }
        } else {
            $cover_pic = $profile_user['cover_pic'];
        }
    } else {
        $cover_pic = $profile_user['cover_pic'];
    }
    
    $update_sql = "UPDATE users SET full_name = ?, bio = ?, profile_pic = ?, cover_pic = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("ssssi", $full_name, $bio, $profile_pic, $cover_pic, $profile_user_id);
    
    if ($update_stmt->execute()) {
        redirect("profile.php?user_id=$profile_user_id&tab=$active_tab");
    }
}

// Get user tweets (for Tweets tab)
if ($active_tab == 'tweets') {
    $tweets_sql = "SELECT t.*, 
        (SELECT COUNT(*) FROM likes WHERE tweet_id = t.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE tweet_id = t.id) as comment_count,
        (SELECT COUNT(*) FROM likes WHERE tweet_id = t.id AND user_id = ?) as has_liked
        FROM tweets t 
        WHERE t.user_id = ? 
        ORDER BY t.created_at DESC";

    $tweets_stmt = $conn->prepare($tweets_sql);
    $tweets_stmt->bind_param("ii", $user_id, $profile_user_id);
    $tweets_stmt->execute();
    $user_tweets = $tweets_stmt->get_result();
}

// Get tweets with replies (for Tweets & Replies tab)
if ($active_tab == 'tweets_replies') {
    $tweets_sql = "SELECT t.*, 
        (SELECT COUNT(*) FROM likes WHERE tweet_id = t.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE tweet_id = t.id) as comment_count,
        (SELECT COUNT(*) FROM likes WHERE tweet_id = t.id AND user_id = ?) as has_liked
        FROM tweets t 
        WHERE t.user_id = ? 
        OR t.id IN (SELECT tweet_id FROM comments WHERE user_id = ?)
        ORDER BY t.created_at DESC";

    $tweets_stmt = $conn->prepare($tweets_sql);
    $tweets_stmt->bind_param("iii", $user_id, $profile_user_id, $profile_user_id);
    $tweets_stmt->execute();
    $user_tweets = $tweets_stmt->get_result();
}

// Get media tweets (for Media tab)
if ($active_tab == 'media') {
    $tweets_sql = "SELECT t.*, 
        (SELECT COUNT(*) FROM likes WHERE tweet_id = t.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE tweet_id = t.id) as comment_count,
        (SELECT COUNT(*) FROM likes WHERE tweet_id = t.id AND user_id = ?) as has_liked
        FROM tweets t 
        WHERE t.user_id = ? 
        AND t.image IS NOT NULL 
        AND t.image != ''
        ORDER BY t.created_at DESC";

    $tweets_stmt = $conn->prepare($tweets_sql);
    $tweets_stmt->bind_param("ii", $user_id, $profile_user_id);
    $tweets_stmt->execute();
    $user_tweets = $tweets_stmt->get_result();
}

// Get liked tweets (for Likes tab)
if ($active_tab == 'likes') {
    $tweets_sql = "SELECT t.*, u.username, u.full_name, u.profile_pic,
        (SELECT COUNT(*) FROM likes WHERE tweet_id = t.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE tweet_id = t.id) as comment_count,
        (SELECT COUNT(*) FROM likes WHERE tweet_id = t.id AND user_id = ?) as has_liked
        FROM tweets t 
        JOIN users u ON t.user_id = u.id
        WHERE t.id IN (SELECT tweet_id FROM likes WHERE user_id = ?)
        ORDER BY t.created_at DESC";

    $tweets_stmt = $conn->prepare($tweets_sql);
    $tweets_stmt->bind_param("ii", $profile_user_id, $profile_user_id);
    $tweets_stmt->execute();
    $user_tweets = $tweets_stmt->get_result();
}

// Get follower count
$followers_sql = "SELECT COUNT(*) as count FROM follows WHERE following_id = ?";
$followers_stmt = $conn->prepare($followers_sql);
$followers_stmt->bind_param("i", $profile_user_id);
$followers_stmt->execute();
$followers_result = $followers_stmt->get_result()->fetch_assoc();
$follower_count = $followers_result['count'];

// Get following count
$following_sql = "SELECT COUNT(*) as count FROM follows WHERE follower_id = ?";
$following_stmt = $conn->prepare($following_sql);
$following_stmt->bind_param("i", $profile_user_id);
$following_stmt->execute();
$following_result = $following_stmt->get_result()->fetch_assoc();
$following_count = $following_result['count'];

// Check if current user is following this profile
$is_following = false;
if (!$is_own_profile) {
    $check_follow_sql = "SELECT * FROM follows WHERE follower_id = ? AND following_id = ?";
    $check_follow_stmt = $conn->prepare($check_follow_sql);
    $check_follow_stmt->bind_param("ii", $user_id, $profile_user_id);
    $check_follow_stmt->execute();
    $check_follow_result = $check_follow_stmt->get_result();
    $is_following = ($check_follow_result->num_rows > 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $profile_user['full_name']; ?> | Twitter Clone</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Inter:wght@300;400;500&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #ffffff;
            color: #0f1419;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            border-right: 1px solid #eff3f4;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        
        .logo {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 700;
            color: #1d9bf0;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo i {
            font-size: 32px;
        }
        
        .nav-links {
            list-style: none;
            margin-bottom: auto;
            flex-grow: 1;
        }
        
        .nav-links li {
            margin-bottom: 15px;
        }
        
        .nav-links a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            text-decoration: none;
            color: #0f1419;
            font-size: 18px;
            font-weight: 500;
            border-radius: 9999px;
            transition: all 0.2s;
        }
        
        .nav-links a:hover {
            background-color: #f7f9f9;
        }
        
        .nav-links a.active {
            font-weight: 700;
            color: #1d9bf0;
        }
        
        .nav-links i {
            font-size: 24px;
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            background: none;
            border: none;
            color: #0f1419;
            font-size: 18px;
            font-weight: 500;
            border-radius: 9999px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
            width: 100%;
            font-family: 'Inter', sans-serif;
        }
        
        .logout-btn
