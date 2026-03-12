<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Pro - Contact Us</title>
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
            padding: 2rem 1rem;
        }
        
        .contact-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 1000px;
            display: flex;
            min-height: 600px;
        }
        
        .contact-left {
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
        
        .contact-left::before {
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
        
        .contact-right {
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
        
        .contact-info {
            position: relative;
            z-index: 1;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .contact-item i {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .contact-form h2 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .contact-form p {
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-floating input,
        .form-floating select,
        .form-floating textarea {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-floating input:focus,
        .form-floating select:focus,
        .form-floating textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
        }
        
        .form-floating label {
            color: var(--text-secondary);
        }
        
        .btn-submit {
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
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        @media (max-width: 768px) {
            .contact-container {
                flex-direction: column;
                margin: 1rem;
                min-height: auto;
            }
            
            .contact-left {
                padding: 2rem;
                text-align: center;
            }
            
            .contact-right {
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="contact-container">
        <div class="contact-left">
            <div class="brand-logo">
                <i class="fas fa-chart-line me-3"></i>CRM Pro
            </div>
            <div class="brand-tagline">
                Ready to transform your business? Get in touch with our experts today.
            </div>
            <div class="contact-info">
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <div class="fw-semibold">Email Us</div>
                        <div>hello@crmPro.com</div>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <div class="fw-semibold">Call Us</div>
                        <div>+1 (555) 123-4567</div>
                    </div>
                </div>
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <div class="fw-semibold">Visit Us</div>
                        <div>123 Business Ave, Suite 100</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="contact-right">
            <div class="contact-form">
                <h2>Get Started Today</h2>
                <p>Fill out the form below and we'll get back to you within 24 hours</p>
                
                <form action="submit-lead.php" method="POST">
                    <input type="hidden" name="form_source" value="website">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="name" name="name" placeholder="Full Name" required>
                                <label for="name"><i class="fas fa-user me-2"></i>Full Name *</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                                <label for="email"><i class="fas fa-envelope me-2"></i>Email Address *</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="tel" class="form-control" id="phone" name="phone" placeholder="Phone">
                                <label for="phone"><i class="fas fa-phone me-2"></i>Phone Number</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="company" name="company" placeholder="Company">
                                <label for="company"><i class="fas fa-building me-2"></i>Company</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating">
                        <select class="form-select" id="service" name="service" required>
                            <option value="">Choose a service...</option>
                            <option value="web-development">Web Development</option>
                            <option value="mobile-app">Mobile App Development</option>
                            <option value="digital-marketing">Digital Marketing</option>
                            <option value="consulting">Business Consulting</option>
                            <option value="other">Other Services</option>
                        </select>
                        <label for="service"><i class="fas fa-cogs me-2"></i>Service Interest *</label>
                    </div>
                    
                    <div class="form-floating">
                        <textarea class="form-control" id="message" name="message" placeholder="Message" style="height: 120px"></textarea>
                        <label for="message"><i class="fas fa-comment me-2"></i>Tell us about your project</label>
                    </div>
                    
                    <button type="submit" class="btn btn-submit w-100">
                        <i class="fas fa-paper-plane me-2"></i>Send Message
                    </button>
                </form>
                
                <div class="text-center mt-3">
                    <small class="text-muted">
                        Already have an account? <a href="login.php" class="text-decoration-none">Sign in here</a>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
