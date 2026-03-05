<?php
// comment.php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user = getUserData($conn, $user_id);

if (!isset($_GET['tweet_id'])) {
    redirect('index.php');
}

$tweet_id = intval($_GET['tweet_id']);

// Get tweet details
$tweet_sql = "SELECT t.*, u.username, u.full_name, u.profile_pic FROM tweets t 
              JOIN users u ON t.user_id = u.id 
              WHERE t.id = ?";
$tweet_stmt = $conn->prepare($tweet_sql);
$tweet_stmt->bind_param("i", $tweet_id);
$tweet_stmt->execute();
$tweet_result = $tweet_stmt->get_result();

if ($tweet_result->num_rows == 0) {
    redirect('index.php');
}

$tweet = $tweet_result->fetch_assoc();

// Handle comment posting
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['post_comment'])) {
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    
    $insert_sql = "INSERT INTO comments (user_id, tweet_id, content) VALUES (?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("iis", $user_id, $tweet_id, $content);
    $insert_stmt->execute();
    
    redirect("comment.php?tweet_id=$tweet_id");
}

// Get comments for this tweet
$comments_sql = "SELECT c.*, u.username, u.full_name, u.profile_pic FROM comments c
                 JOIN users u ON c.user_id = u.id
                 WHERE c.tweet_id = ?
                 ORDER BY c.created_at DESC";
$comments_stmt = $conn->prepare($comments_sql);
$comments_stmt->bind_param("i", $tweet_id);
$comments_stmt->execute();
$comments = $comments_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comment | Twitter Clone</title>
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
        }
        
        .comments-container {
            max-width: 600px;
            margin: 0 auto;
            border-left: 1px solid #eff3f4;
            border-right: 1px solid #eff3f4;
        }
        
        .header {
            padding: 15px 20px;
            border-bottom: 1px solid #eff3f4;
            position: sticky;
            top: 0;
            background-color: white;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .back-btn {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #0f1419;
        }
        
        .header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 20px;
            font-weight: 700;
        }
        
        .tweet-thread {
            padding: 20px;
            border-bottom: 1px solid #eff3f4;
        }
        
        .tweet {
            margin-bottom: 20px;
        }
        
        .tweet-header {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 15px;
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
        
        .tweet-content {
            font-size: 15px;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        
        .tweet-time {
            color: #536471;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .comment-form {
            padding: 20px;
            border-bottom: 1px solid #eff3f4;
        }
        
        .comment-input {
            display: flex;
            gap: 15px;
        }
        
        .comment-input img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .comment-content {
            flex: 1;
        }
        
        .comment-content textarea {
            width: 100%;
            border: none;
            font-size: 20px;
            font-family: 'Inter', sans-serif;
            resize: none;
            min-height: 80px;
            margin-bottom: 15px;
        }
        
        .comment-content textarea:focus {
            outline: none;
        }
        
        .comment-btn {
            background-color: #1d9bf0;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 9999px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            float: right;
        }
        
        .comments-list {
            padding: 20px;
        }
        
        .comment {
            padding: 15px 0;
            border-bottom: 1px solid #eff3f4;
        }
        
        .comment-header {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .comment-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .comment-content {
            font-size: 15px;
            line-height: 1.5;
        }
        
        .comment-time {
            color: #536471;
            font-size: 14px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="comments-container">
        <div class="header">
            <button class="back-btn" onclick="window.history.back()">←</button>
            <h1>Thread</h1>
        </div>
        
        <div class="tweet-thread">
            <div class="tweet">
                <div class="tweet-header">
                    <div class="tweet-user">
                        <img src="<?php echo 'uploads/' . $tweet['profile_pic']; ?>" alt="<?php echo $tweet['username']; ?>">
                    </div>
                    <div class="tweet-user-info">
                        <h4><?php echo $tweet['full_name']; ?></h4>
                        <p>@<?php echo $tweet['username']; ?></p>
                    </div>
                </div>
                
                <div class="tweet-content">
                    <?php echo nl2br(htmlspecialchars($tweet['content'])); ?>
                </div>
                
                <div class="tweet-time">
                    <?php echo date('g:i A · F j, Y', strtotime($tweet['created_at'])); ?>
                </div>
            </div>
        </div>
        
        <div class="comment-form">
            <form method="POST">
                <div class="comment-input">
                    <img src="<?php echo 'uploads/' . $user['profile_pic']; ?>" alt="Your profile">
                    <div class="comment-content">
                        <textarea name="content" placeholder="Tweet your reply" maxlength="280" required></textarea>
                        <button type="submit" name="post_comment" class="comment-btn">Reply</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="comments-list">
            <?php while($comment = $comments->fetch_assoc()): ?>
                <div class="comment">
                    <div class="comment-header">
                        <img src="<?php echo 'uploads/' . $comment['profile_pic']; ?>" alt="<?php echo $comment['username']; ?>">
                        <div class="tweet-user-info">
                            <h4><?php echo $comment['full_name']; ?></h4>
                            <p>@<?php echo $comment['username']; ?></p>
                        </div>
                    </div>
                    
                    <div class="comment-content">
                        <?php echo nl2br(htmlspecialchars($comment['content'])); ?>
                    </div>
                    
                    <div class="comment-time">
                        <?php echo timeAgo($comment['created_at']); ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
