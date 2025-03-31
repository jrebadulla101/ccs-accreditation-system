<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Accreditation System - EARIST Manila</title>
    
    <!-- Favicon -->
    <link rel="icon" href="admin/assets/images/favicon.ico" type="image/x-icon">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Orbitron:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Particles.js -->
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    
    <style>
        :root {
            --primary-color: #4a90e2;
            --secondary-color: #5C6BC0;
            --bg-color: #1a1a1a;
            --card-bg: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --accent-color: #00ff9d;
            --border-color: #3a3a3a;
            --gradient-start: #2c3e50;
            --gradient-end: #1a252f;
            --error-color: #ff3b30;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        
        #particles-js {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 1;
        }
        
        .container {
            position: relative;
            z-index: 2;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .header {
            text-align: center;
            margin-bottom: 3rem;
            animation: fadeInDown 1s ease;
        }
        
        .logo {
            width: 120px;
            height: 120px;
            margin-bottom: 1rem;
        }
        
        .title {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, var(--accent-color), var(--primary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .subtitle {
            color: var(--text-secondary);
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        
        .login-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            animation: fadeInUp 1s ease;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(0, 255, 157, 0.2);
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(74, 144, 226, 0.3);
        }
        
        .error-message {
            background: rgba(255, 59, 48, 0.1);
            border-left: 4px solid var(--error-color);
            color: var(--error-color);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .title {
                font-size: 2rem;
            }
            
            .subtitle {
                font-size: 1rem;
            }
            
            .login-card {
                padding: 1.5rem;
            }
        }
        
        /* Glowing Effects */
        .glow {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, var(--accent-color) 0%, rgba(0,255,157,0) 70%);
            opacity: 0.1;
            filter: blur(50px);
            animation: glowPulse 4s infinite alternate;
        }
        
        .glow:nth-child(1) {
            top: -250px;
            left: -250px;
        }
        
        .glow:nth-child(2) {
            bottom: -250px;
            right: -250px;
            background: radial-gradient(circle, var(--primary-color) 0%, rgba(74,144,226,0) 70%);
        }
        
        @keyframes glowPulse {
            from {
                opacity: 0.1;
                transform: scale(1);
            }
            to {
                opacity: 0.15;
                transform: scale(1.1);
            }
        }
    </style>
</head>
<body>
    <!-- Particles.js background -->
    <div id="particles-js"></div>
    
    <!-- Glowing effects -->
    <div class="glow"></div>
    <div class="glow"></div>
    
    <div class="container">
        <div class="header">
            <img src="admin/assets/images/logo.png" alt="EARIST Logo" class="logo">
            <h1 class="title">CCS Accreditation System</h1>
            <p class="subtitle">EARIST Manila - College of Computer Studies</p>
        </div>
        
        <div class="login-card">
            <?php if (isset($error)): ?>
                <div class="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="admin/login.php">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn">Login to System</button>
            </form>
        </div>
        
        <div class="footer">
            &copy; <?php echo date('Y'); ?> EARIST Manila. All rights reserved.
        </div>
    </div>
    
    <script>
        // Initialize particles.js
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof particlesJS !== 'undefined') {
                particlesJS("particles-js", {
                    particles: {
                        number: { value: 80, density: { enable: true, value_area: 800 } },
                        color: { value: "#4a90e2" },
                        shape: { type: "circle" },
                        opacity: { value: 0.5, random: false },
                        size: { value: 3, random: true },
                        line_linked: {
                            enable: true,
                            distance: 150,
                            color: "#4a90e2",
                            opacity: 0.2,
                            width: 1
                        },
                        move: {
                            enable: true,
                            speed: 2,
                            direction: "none",
                            random: false,
                            straight: false,
                            out_mode: "out",
                            bounce: false
                        }
                    },
                    interactivity: {
                        detect_on: "canvas",
                        events: {
                            onhover: { enable: true, mode: "repulse" },
                            onclick: { enable: true, mode: "push" },
                            resize: true
                        },
                        modes: {
                            repulse: { distance: 100, duration: 0.4 },
                            push: { particles_nb: 4 }
                        }
                    },
                    retina_detect: true
                });
            }
        });
    </script>
</body>
</html> 