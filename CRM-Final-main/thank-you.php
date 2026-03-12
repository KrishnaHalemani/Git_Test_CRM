<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - CRM Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --success-color: #10b981;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .thank-you-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 600px;
            width: 100%;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--success-color), #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 2rem;
            color: white;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .thank-you-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .thank-you-message {
            font-size: 1.1rem;
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .next-steps {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }

        .next-steps h4 {
            color: #1e293b;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .next-steps ul {
            list-style: none;
            padding: 0;
        }

        .next-steps li {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            color: #64748b;
        }

        .next-steps li i {
            color: var(--success-color);
            margin-right: 0.75rem;
            width: 20px;
        }

        .btn-modern {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
            color: white;
        }

        .btn-outline-modern {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: #1e293b;
        }

        .btn-outline-modern:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <div class="thank-you-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>

        <h1 class="thank-you-title">Thank You!</h1>

        <p class="thank-you-message">
            Your message has been successfully submitted. We appreciate your interest in CRM Pro and will get back to you within 24 hours.
        </p>

        <div class="next-steps">
            <h4>What happens next?</h4>
            <ul>
                <li><i class="fas fa-envelope"></i>You'll receive a confirmation email shortly</li>
                <li><i class="fas fa-phone"></i>Our team will contact you within 24 hours</li>
                <li><i class="fas fa-calendar"></i>We'll schedule a consultation call</li>
                <li><i class="fas fa-rocket"></i>Start your CRM journey with us</li>
            </ul>
        </div>

        <div class="d-flex flex-wrap justify-content-center">
            <a href="index.html" class="btn btn-modern">
                <i class="fas fa-arrow-left"></i>Back to Home
            </a>
            <a href="login.php" class="btn btn-outline-modern">
                <i class="fas fa-sign-in-alt"></i>Access Dashboard
            </a>
        </div>

        <div class="mt-4">
            <small class="text-muted">
                Need immediate assistance? Call us at <strong>+1 (555) 123-4567</strong>
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>