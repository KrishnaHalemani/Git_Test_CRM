<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Pro - Database Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .setup-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            max-width: 600px;
            width: 100%;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .setup-title {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }
        .btn-modern {
            border-radius: 12px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        .step {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1 class="setup-title">CRM Pro Setup</h1>
            <p class="text-muted">Welcome! Let's set up your MySQL database for CRM Pro.</p>
        </div>

        <?php
        // Check if database connection exists
        $db_configured = false;
        $error_message = '';
        
        try {
            include 'db.php';
            $db_configured = true;
            echo '<div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Database Connected!</strong> Your CRM Pro is ready to use.
                  </div>';
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
        
        if (!$db_configured): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Database Setup Required</strong>
            </div>
            
            <div class="step">
                <h5><i class="fas fa-database me-2"></i>Step 1: Create Database</h5>
                <p>Run this SQL command in your MySQL:</p>
                <div class="bg-dark text-light p-3 rounded">
                    <code>CREATE DATABASE crm_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code>
                </div>
            </div>
            
            <div class="step">
                <h5><i class="fas fa-table me-2"></i>Step 2: Import Schema</h5>
                <p>Import the database schema:</p>
                <div class="bg-dark text-light p-3 rounded">
                    <code>mysql -u root -p crm_pro < database_schema.sql</code>
                </div>
                <small class="text-muted">Or use phpMyAdmin to import the database_schema.sql file</small>
            </div>
            
            <div class="step">
                <h5><i class="fas fa-cog me-2"></i>Step 3: Configure Database</h5>
                <p>Update your database credentials in <code>db.php</code>:</p>
                <ul>
                    <li><strong>Host:</strong> localhost</li>
                    <li><strong>Username:</strong> root (or your MySQL username)</li>
                    <li><strong>Password:</strong> (your MySQL password)</li>
                    <li><strong>Database:</strong> crm_pro</li>
                </ul>
            </div>
            
            <div class="step">
                <h5><i class="fas fa-user me-2"></i>Step 4: Default Login Credentials</h5>
                <p>After setup, you can login with these accounts:</p>
                <div class="row">
                    <div class="col-md-4">
                        <strong>Super Admin:</strong><br>
                        Username: <code>superadmin</code><br>
                        Password: <code>super123</code>
                    </div>
                    <div class="col-md-4">
                        <strong>Admin:</strong><br>
                        Username: <code>admin</code><br>
                        Password: <code>admin123</code>
                    </div>
                    <div class="col-md-4">
                        <strong>User:</strong><br>
                        Username: <code>user</code><br>
                        Password: <code>user123</code>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Error Details:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
            
        <?php else: ?>
            <div class="text-center">
                <h4>🎉 Setup Complete!</h4>
                <p class="text-muted mb-4">Your CRM Pro is ready to use with MySQL database.</p>
                
                <div class="row text-center mb-4">
                    <div class="col-md-4">
                        <div class="p-3">
                            <i class="fas fa-database fa-2x text-primary mb-2"></i>
                            <h6>Database</h6>
                            <small class="text-muted">MySQL Connected</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3">
                            <i class="fas fa-users fa-2x text-success mb-2"></i>
                            <h6>Users</h6>
                            <small class="text-muted">3 Default Accounts</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3">
                            <i class="fas fa-chart-line fa-2x text-info mb-2"></i>
                            <h6>Sample Data</h6>
                            <small class="text-muted">Demo Leads Included</small>
                        </div>
                    </div>
                </div>
                
                <a href="login.php" class="btn btn-primary-modern btn-lg">
                    <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                </a>
                
                <div class="mt-4">
                    <small class="text-muted">
                        <strong>Quick Start:</strong> Login with <code>admin</code> / <code>admin123</code>
                    </small>
                </div>
            </div>
        <?php endif; ?>
        
        <hr class="my-4">
        <div class="text-center">
            <small class="text-muted">
                <i class="fas fa-rocket me-1"></i>
                CRM Pro - Advanced Customer Relationship Management System
            </small>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
