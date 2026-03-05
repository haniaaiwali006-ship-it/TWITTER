<?php
// index.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user = getUserData($conn, $user_id);

// Handle tweet posting
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_tweet'])) {
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    
    // Handle image upload
    $image = '';
    if (isset($_FILES['tweet_image']) && $_FILES['tweet_image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        
        $image_name = time() . '_' . basename($_FILES['tweet_image']['name']);
        $target_file = $target_dir . $image_name;
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES['tweet_image']['tmp_name']);
        if($check !== false) {
            if (move_uploaded_file($_FILES['tweet_image']['tmp_name'], $target_file)) {
                $image = $target_file;
            }
        }
    }
    
    $sql = "INSERT INTO tweets (user_id, content, image) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $user_id, $content, $image);
    $stmt->execute();
    
    redirect('index.php');
}

// Handle like/unlike
if (isset($_GET['like_tweet'])) {
    $tweet_id = intval($_GET['like_tweet']);
    
    // Check if already liked
    $check_sql = "SELECT * FROM likes WHERE user_id = ? AND tweet_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $user_id, $tweet_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Unlike
        $delete_sql = "DELETE FROM likes WHERE user_id = ? AND tweet_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $user_id, $tweet_id);
        $delete_stmt->execute();
    } else {
        // Like
        $insert_sql = "INSERT INTO likes (user_id, tweet_id) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ii", $user_id, $tweet_id);
        $insert_stmt->execute();
    }
    
    redirect('index.php');
}

// Handle follow/unfollow
if (isset($_GET['follow_user'])) {
    $following_id = intval($_GET['follow_user']);
    
    if ($following_id != $user_id) {
        // Check if already following
        $check_sql = "SELECT * FROM follows WHERE follower_id = ? AND following_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $user_id, $following_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Unfollow
            $delete_sql = "DELETE FROM follows WHERE follower_id = ? AND following_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $user_id, $following_id);
            $delete_stmt->execute();
        } else {
            // Follow
            $insert_sql = "INSERT INTO follows (follower_id, following_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ii", $user_id, $following_id);
            $insert_stmt->execute();
        }
    }
    
    redirect('index.php');
}

// Get tweets from following users
$sql = "
    SELECT DISTINCT t.*, u.username, u.full_name, u.profile_pic,
    (SELECT COUNT(*) FROM likes WHERE tweet_id = t.id) as like_count,
    (SELECT COUNT(*) FROM comments WHERE tweet_id = t.id) as comment_count,
    (SELECT COUNT(*) FROM likes WHERE tweet_id = t.id AND user_id = ?) as has_liked
    FROM tweets t
    JOIN users u ON t.user_id = u.id
    WHERE t.user_id = ? 
    OR t.user_id IN (SELECT following_id FROM follows WHERE follower_id = ?)
    ORDER BY t.created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$tweets = $stmt->get_result();

// Get suggested users (excluding current user)
$suggested_sql = "
    SELECT u.*, 
    (SELECT COUNT(*) FROM follows WHERE following_id = u.id) as followers_count
    FROM users u
    WHERE u.id != ? 
    AND u.id NOT IN (SELECT following_id FROM follows WHERE follower_id = ?)
    ORDER BY followers_count DESC
    LIMIT 5
";

$suggested_stmt = $conn->prepare($suggested_sql);
$suggested_stmt->bind_param("ii", $user_id, $user_id);
$suggested_stmt->execute();
$suggested_users = $suggested_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home | Twitter Clone</title>
    <style>
        /* Import Google Fonts */
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
        
        .logout-btn:hover {
            background-color: #f7f9f9;
            color: #f4212e;
        }
        
        .post-btn {
            background-color: #1d9bf0;
            color: white;
            border: none;
            padding: 15px 32px;
            border-radius: 9999px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            margin: 20px 0;
            transition: background-color 0.2s;
        }
        
        .post-btn:hover {
            background-color: #1a8cd8;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 9999px;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 20px;
        }
        
        .user-profile:hover {
            background-color: #f7f9f9;
        }
        
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .user-info h4 {
            font-size: 15px;
            font-weight: 700;
        }
        
        .user-info p {
            font-size: 14px;
            color: #536471;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            max-width: 600px;
            border-right: 1px solid #eff3f4;
        }
        
        .header {
            padding: 15px 20px;
            border-bottom: 1px solid #eff3f4;
            position: sticky;
            top: 0;
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            z-index: 100;
        }
        
        .header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            font-weight: 700;
        }
        
        /* Tweet Form Styles */
        .tweet-form {
            padding: 15px 20px;
            border-bottom: 1px solid #eff3f4;
        }
        
        .tweet-input {
            display: flex;
            gap: 15px;
        }
        
        .tweet-input img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .tweet-content {
            flex: 1;
        }
        
        .tweet-content textarea {
            width: 100%;
            border: none;
            font-size: 20px;
            font-family: 'Inter', sans-serif;
            resize: none;
            min-height: 100px;
            margin-bottom: 15px;
        }
        
        .tweet-content textarea:focus {
            outline: none;
        }
        
        .tweet-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #eff3f4;
            padding-top: 15px;
        }
        
        .tweet-icons {
            display: flex;
            gap: 15px;
        }
        
        .image-upload-btn {
            color: #1d9bf0;
            cursor: pointer;
            font-size: 20px;
            position: relative;
        }
        
        .image-upload-btn input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .image-preview {
            margin-top: 10px;
            position: relative;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 16px;
            object-fit: cover;
        }
        
        .remove-image {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Tweets Feed Styles */
        .tweets-feed {
            border-bottom: 1px solid #eff3f4;
        }
        
        .tweet {
            padding: 15px 20px;
            border-bottom: 1px solid #eff3f4;
            transition: background-color 0.2s;
        }
        
        .tweet:hover {
            background-color: #f7f9f9;
        }
        
        .tweet-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 5px;
        }
        
        .tweet-user {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .tweet-user img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .tweet-user-info h4 {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        
        .tweet-user-info p {
            color: #536471;
            font-size: 14px;
        }
        
        .tweet-time {
            color: #536471;
            font-size: 14px;
        }
        
        .tweet-content {
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .tweet-image {
            width: 100%;
            max-height: 400px;
            border-radius: 16px;
            object-fit: cover;
            margin-bottom: 15px;
        }
        
        .tweet-actions {
            display: flex;
            justify-content: space-between;
            max-width: 425px;
            margin-top: 15px;
        }
        
        .tweet-action {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #536471;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .tweet-action:hover {
            color: #1d9bf0;
        }
        
        .tweet-action.liked {
            color: #f91880;
        }
        
        .tweet-action i {
            font-size: 18px;
        }
        
        /* Right Sidebar Styles */
        .right-sidebar {
            width: 350px;
            padding: 20px;
            position: fixed;
            right: 0;
            height: 100vh;
            overflow-y: auto;
        }
        
        .search-box {
            background-color: #eff3f4;
            border-radius: 9999px;
            padding: 12px 20px;
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            border: none;
            background: none;
            font-size: 15px;
            outline: none;
        }
        
        .trends, .suggestions {
            background-color: #f7f9f9;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .trends h2, .suggestions h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .trend-item, .suggested-user {
            padding: 15px 0;
            border-bottom: 1px solid #eff3f4;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .trend-item:last-child, .suggested-user:last-child {
            border-bottom: none;
        }
        
        .trend-item:hover, .suggested-user:hover {
            background-color: #eff3f4;
        }
        
        .suggested-user {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
        }
        
        .suggested-user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .suggested-user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .follow-btn {
            background-color: #0f1419;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .follow-btn:hover {
            background-color: #1d9bf0;
        }
        
        .follow-btn.following {
            background-color: transparent;
            border: 1px solid #cfd9de;
            color: #0f1419;
        }
        
        /* Delete Button */
        .delete-btn {
            background-color: transparent;
            border: none;
            color: #f4212e;
            cursor: pointer;
            font-size: 14px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        
        .delete-btn:hover {
            background-color: rgba(244, 33, 46, 0.1);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            border-radius: 16px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-header h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 24px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #536471;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #536471;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: #cfd9de;
        }
        
        .empty-state h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            margin-bottom: 10px;
            color: #0f1419;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .right-sidebar {
                display: none;
            }
        }
        
        @media (max-width: 900px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .nav-links a span,
            .user-info,
            .post-btn span,
            .logout-btn span {
                display: none;
            }
            
            .nav-links a {
                justify-content: center;
                padding: 12px;
            }
            
            .logout-btn {
                justify-content: center;
                padding: 12px;
            }
            
            .logo span {
                display: none;
            }
            
            .post-btn {
                padding: 15px;
                font-size: 0;
            }
            
            .post-btn::after {
                content: "＋";
                font-size: 20px;
            }
            
            .logout-btn::after {
                content: "🚪";
                font-size: 20px;
            }
        }
        
        @media (max-width: 600px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: fixed;
                bottom: 0;
                top: auto;
                border-right: none;
                border-top: 1px solid #eff3f4;
                z-index: 1000;
                flex-direction: row;
                padding: 10px;
            }
            
            .main-content {
                margin-left: 0;
                margin-bottom: 70px;
                max-width: 100%;
            }
            
            .nav-links {
                display: flex;
                justify-content: space-around;
                margin-bottom: 0;
                width: 100%;
            }
            
            .nav-links li {
                margin-bottom: 0;
            }
            
            .logo,
            .user-profile,
            .post-btn,
            .logout-btn {
                display: none;
            }
            
            .nav-links a {
                padding: 10px;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Left Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fab fa-twitter"></i>
            <span>Twitter Clone</span>
        </div>
        
        <ul class="nav-links">
            <li><a href="index.php" class="active"><i class="fas fa-home"></i> <span>Home</span></a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> <span>Profile</span></a></li>
        </ul>
        
        <button class="post-btn" onclick="openTweetModal()"><span>Tweet</span></button>
        
        <button class="logout-btn" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </button>
        
        <div class="user-profile" onclick="window.location.href='profile.php'">
            <img src="<?php echo 'uploads/' . $user['profile_pic']; ?>" alt="<?php echo $user['username']; ?>">
            <div class="user-info">
                <h4><?php echo $user['full_name']; ?></h4>
                <p>@<?php echo $user['username']; ?></p>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Home</h1>
        </div>
        
        <!-- Tweet Form -->
        <div class="tweet-form">
            <form method="POST" enctype="multipart/form-data" id="tweetForm">
                <div class="tweet-input">
                    <img src="<?php echo 'uploads/' . $user['profile_pic']; ?>" alt="Profile Picture">
                    <div class="tweet-content">
                        <textarea name="content" id="tweetText" placeholder="What's happening?" maxlength="280" required></textarea>
                        
                        <!-- Image Preview -->
                        <div class="image-preview" id="imagePreview" style="display: none;">
                            <img id="previewImage" src="" alt="Preview">
                            <button type="button" class="remove-image" onclick="removeImage()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="tweet-actions">
                            <div class="tweet-icons">
                                <label class="image-upload-btn">
                                    <i class="fas fa-image" title="Add image"></i>
                                    <input type="file" id="tweet-image" name="tweet_image" accept="image/*" onchange="previewTweetImage(event)">
                                </label>
                            </div>
                            <button type="submit" name="post_tweet" class="post-btn">Tweet</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Tweets Feed -->
        <div class="tweets-feed">
            <?php if($tweets->num_rows > 0): ?>
                <?php while($tweet = $tweets->fetch_assoc()): ?>
                    <?php 
                    $tweet_user = getUserData($conn, $tweet['user_id']);
                    $is_owner = $tweet['user_id'] == $user_id;
                    ?>
                    <div class="tweet" id="tweet-<?php echo $tweet['id']; ?>">
                        <div class="tweet-header">
                            <div class="tweet-user">
                                <img src="<?php echo 'uploads/' . $tweet_user['profile_pic']; ?>" alt="<?php echo $tweet_user['username']; ?>">
                                <div class="tweet-user-info">
                                    <h4><?php echo $tweet_user['full_name']; ?></h4>
                                    <p>@<?php echo $tweet_user['username']; ?></p>
                                </div>
                            </div>
                            <div class="tweet-time">
                                <?php echo timeAgo($tweet['created_at']); ?>
                            </div>
                        </div>
                        
                        <div class="tweet-content">
                            <?php echo nl2br(htmlspecialchars($tweet['content'])); ?>
                        </div>
                        
                        <?php if($tweet['image'] && file_exists($tweet['image'])): ?>
                            <img src="<?php echo $tweet['image']; ?>" alt="Tweet Image" class="tweet-image">
                        <?php endif; ?>
                        
                        <div class="tweet-actions">
                            <a href="?like_tweet=<?php echo $tweet['id']; ?>" class="tweet-action <?php echo $tweet['has_liked'] ? 'liked' : ''; ?>">
                                <i class="fas fa-heart"></i>
                                <span><?php echo $tweet['like_count']; ?></span>
                            </a>
                            <a href="comment.php?tweet_id=<?php echo $tweet['id']; ?>" class="tweet-action">
                                <i class="fas fa-comment"></i>
                                <span><?php echo $tweet['comment_count']; ?></span>
                            </a>
                            <a href="#" class="tweet-action">
                                <i class="fas fa-retweet"></i>
                            </a>
                            <a href="#" class="tweet-action">
                                <i class="fas fa-share"></i>
                            </a>
                            <?php if($is_owner): ?>
                                <button class="delete-btn" onclick="deleteTweet(<?php echo $tweet['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-feather-alt"></i>
                    <h3>Welcome to Twitter Clone!</h3>
                    <p>Start by posting your first tweet or follow other users to see their tweets here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Right Sidebar -->
    <div class="right-sidebar">
        <div class="search-box">
            <input type="text" placeholder="Search Twitter Clone">
        </div>
        
        <div class="trends">
            <h2>Trends for you</h2>
            <div class="trend-item">
                <h4>Trending in Technology</h4>
                <p>#WebDevelopment</p>
            </div>
            <div class="trend-item">
                <h4>Sports · Trending</h4>
                <p>#ChampionsLeague</p>
            </div>
            <div class="trend-item">
                <h4>Entertainment · Trending</h4>
                <p>#NewMovieReleases</p>
            </div>
        </div>
        
        <div class="suggestions">
            <h2>Who to follow</h2>
            <?php if($suggested_users->num_rows > 0): ?>
                <?php while($suggested = $suggested_users->fetch_assoc()): ?>
                    <div class="suggested-user">
                        <div class="suggested-user-info">
                            <img src="<?php echo 'uploads/' . $suggested['profile_pic']; ?>" alt="<?php echo $suggested['username']; ?>">
                            <div>
                                <h4><?php echo $suggested['full_name']; ?></h4>
                                <p>@<?php echo $suggested['username']; ?></p>
                            </div>
                        </div>
                        <a href="?follow_user=<?php echo $suggested['id']; ?>" class="follow-btn">Follow</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 20px; color: #536471;">
                    <p>No users to suggest yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Tweet Modal -->
    <div class="modal" id="tweetModal">
        <div class="modal-content">
            <div class="modal-header">
                <button class="close-modal" onclick="closeTweetModal()">×</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="modalTweetForm">
                <div class="tweet-input">
                    <img src="<?php echo 'uploads/' . $user['profile_pic']; ?>" alt="Profile Picture">
                    <div class="tweet-content">
                        <textarea name="content" placeholder="What's happening?" maxlength="280" required></textarea>
                        
                        <!-- Image Preview for Modal -->
                        <div class="image-preview" id="modalImagePreview" style="display: none;">
                            <img id="modalPreviewImage" src="" alt="Preview">
                            <button type="button" class="remove-image" onclick="removeModalImage()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div class="tweet-actions">
                            <div class="tweet-icons">
                                <label class="image-upload-btn">
                                    <i class="fas fa-image" title="Add image"></i>
                                    <input type="file" id="modal-tweet-image" name="tweet_image" accept="image/*" onchange="previewModalImage(event)">
                                </label>
                            </div>
                            <button type="submit" name="post_tweet" class="post-btn">Tweet</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openTweetModal() {
            document.getElementById('tweetModal').style.display = 'flex';
            document.querySelector('#tweetModal textarea').focus();
        }
        
        function closeTweetModal() {
            document.getElementById('tweetModal').style.display = 'none';
            // Reset modal form
            document.getElementById('modalTweetForm').reset();
            document.getElementById('modalImagePreview').style.display = 'none';
            document.getElementById('modalPreviewImage').src = '';
        }
        
        function deleteTweet(tweetId) {
            if (confirm('Are you sure you want to delete this tweet?')) {
                window.location.href = 'delete_tweet.php?id=' + tweetId;
            }
        }
        
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }
        
        // Image preview for main tweet form
        function previewTweetImage(event) {
            const input = event.target;
            const preview = document.getElementById('previewImage');
            const previewContainer = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Image preview for modal tweet form
        function previewModalImage(event) {
            const input = event.target;
            const preview = document.getElementById('modalPreviewImage');
            const previewContainer = document.getElementById('modalImagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Remove image from main form
        function removeImage() {
            document.getElementById('tweet-image').value = '';
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('previewImage').src = '';
        }
        
        // Remove image from modal form
        function removeModalImage() {
            document.getElementById('modal-tweet-image').value = '';
            document.getElementById('modalImagePreview').style.display = 'none';
            document.getElementById('modalPreviewImage').src = '';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('tweetModal');
            if (event.target == modal) {
                closeTweetModal();
            }
        }
        
        // Auto-expand textarea
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });
        
        // Character counter
        const tweetText = document.getElementById('tweetText');
        tweetText.addEventListener('input', function() {
            const charCount = this.value.length;
            const charLimit = 280;
            
            if (charCount > charLimit) {
                this.value = this.value.substring(0, charLimit);
            }
        });
    </script>
</body>
</html>
