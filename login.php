<?php
// login.php
require_once 'config.php';

if (isLoggedIn()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            redirect('index.php');
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "No account found with this email!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Twitter Clone</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }
        
        .logo {
            font-family: 'Poppins', sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: #1d9bf0;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-form h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px;
            border: 1px solid #cfd9de;
            border-radius: 4px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #1d9bf0;
        }
        
        .login-btn {
            width: 100%;
            background-color: #1d9bf0;
            color: white;
            border: none;
            padding: 15px;
            border-radius: 9999px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
            transition: background-color 0.2s;
        }
        
        .login-btn:hover {
            background-color: #1a8cd8;
        }
        
        .signup-link {
            text-align: center;
            margin-top: 20px;
            color: #536471;
        }
        
        .signup-link a {
            color: #1d9bf0;
            text-decoration: none;
            font-weight: 500;
        }
        
        .signup-link a:hover {
            text-decoration: underline;
        }
        
        .error {
            color: #f4212e;
            background-color: rgba(244, 33, 46, 0.1);
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fab fa-twitter"></i>
        </div>
        
        <div class="login-form">
            <h2>Sign in to Twitter Clone</h2>
            
            <?php if(isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                
                <div class="form-group">
                    <input type="password" name="password" placeholder="Password" required>
                </div>
                
                <button type="submit" class="login-btn">Sign in</button>
            </form>
            
            <div class="signup-link">
                <p>Don't have an account? <a href="signup.php">Sign up</a></p>
            </div>
        </div>
    </div>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>
