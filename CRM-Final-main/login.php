<?php
session_start();
include 'db.php';

// If already logged in, redirect to appropriate dashboard based on role
if(isset($_SESSION['user_id'])) {
    if($_SESSION['role'] === 'user') {
        header("Location: user_dashboard.php");
    } elseif($_SESSION['role'] === 'admin') {
        header("Location: dashboard_advanced.php");
    } else {
        header("Location: superadmin_dashboard.php");
    }
    exit();
}

$error = '';

if($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // MySQL authentication
    $user = authenticateUser($username, $password);

    if($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        // Redirect based on role
        if($user['role'] === 'user') {
            header("Location: user_dashboard.php");
        } elseif($user['role'] === 'admin') {
            header("Location: dashboard_advanced.php");
        } else {
            header("Location: superadmin_dashboard.php");
        }
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Pro - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --secondary-color: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-color: #e2e8f0;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 600px;
            display: flex;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateX(0) translateY(0); }
            100% { transform: translateX(-50px) translateY(-50px); }
        }

        .login-right {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .brand-logo {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .brand-tagline {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        .feature-list {
            list-style: none;
            position: relative;
            z-index: 1;
        }

        .feature-list li {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .feature-list i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .login-form h2 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .login-form p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-floating input {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-floating input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }

        .form-floating label {
            color: var(--text-secondary);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .demo-accounts {
            background: var(--secondary-color);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .demo-accounts h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .demo-account {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .demo-account:last-child {
            border-bottom: none;
        }

        .demo-account .role {
            font-weight: 500;
            color: var(--text-primary);
        }

        .demo-account .credentials {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .alert {
            border-radius: 12px;
            border: none;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                margin: 1rem;
                min-height: auto;
            }

            .login-left {
                padding: 2rem;
                text-align: center;
            }

            .login-right {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="brand-logo">
                <i class="fas fa-chart-line me-3"></i>NexTask
            </div>
            <div class="brand-tagline">
                Streamline your customer relationships with our powerful CRM platform
            </div>
            <ul class="feature-list">
                <li><i class="fas fa-users"></i> Advanced Lead Management</li>
                <li><i class="fas fa-chart-bar"></i> Real-time Analytics</li>
                <li><i class="fas fa-envelope"></i> Email Integration</li>
                <li><i class="fas fa-mobile-alt"></i> Mobile Responsive</li>
                <li><i class="fas fa-shield-alt"></i> Secure & Reliable</li>
            </ul>
        </div>

        <div class="login-right">
            <div class="login-form">
                <h2>Welcome Back</h2>
                <p>Sign in to your account to continue</p>

                <?php if($error): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-floating">
                        <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                        <label for="username"><i class="fas fa-user me-2"></i>Username</label>
                    </div>

                    <div class="form-floating">
                        <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                        <label for="password"><i class="fas fa-lock me-2"></i>Password</label>
                    </div>

                    <button type="submit" class="btn btn-login w-100">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                </form>

                <div class="demo-accounts">
                    <h6><i class="fas fa-key me-2"></i>Demo Accounts</h6>
                    <div class="demo-account">
                        <span class="role">Super Admin</span>
                        <span class="credentials">superadmin / super123</span>
                    </div>
                    <div class="demo-account">
                        <span class="role">Admin</span>
                        <span class="credentials">admin / admin123</span>
                    </div>
                    <div class="demo-account">
                        <span class="role">User</span>
                        <span class="credentials">user / user123</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
