<?php
require_once __DIR__ . '/../db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT ref FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    $msg = "<div class='success-message'><i class='fas fa-check-circle'></i> Si l'adresse existe, un lien a été envoyé.</div>";

    if ($user) {
        $token  = bin2hex(random_bytes(32));
        $expire = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

        $conn->prepare("DELETE FROM password_resets WHERE user_ref = ?")
              ->execute([$user['ref']]);

        $conn->prepare("INSERT INTO password_resets (user_ref, token, expire_at)
                        VALUES (?,?,?)")
              ->execute([$user['ref'], $token, $expire]);

        // URL ABSOLUE GARANTIE
        $link = "http://localhost/stage/auth/reset.php?token=$token";
        // AFFICHAGE FACILE À COPIER
        $msg = "<div class='success-message'>
                    <i class='fas fa-check-circle'></i>
                    <span>Lien de réinitialisation généré avec succès!</span>
                </div>
                <div class='link-box'>
                    <div class='link-container'>
                        <input type='text' value='$link' id='reset-link' readonly>
                        <button class='copy-btn' onclick='copyLink()'>
                            <i class='fas fa-copy'></i> Copier
                        </button>
                    </div>
                </div>
                <script>
                function copyLink() {
                    const link = document.getElementById('reset-link');
                    link.select();
                    document.execCommand('copy');
                    
                    // Animation de confirmation
                    const btn = document.querySelector('.copy-btn');
                    btn.innerHTML = '<i class=\"fas fa-check\"></i> Copié!';
                    btn.style.background = '#38a169';
                    
                    setTimeout(() => {
                        btn.innerHTML = '<i class=\"fas fa-copy\"></i> Copier';
                        btn.style.background = '';
                    }, 2000);
                }
                </script>";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié - Elhallaoui</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0d1b3e, #1a2a6c);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #333;
            padding: 20px;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .forgot-container {
            display: flex;
            width: 100%;
            max-width: 900px;
            height: auto;
            min-height: 550px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            position: relative;
        }

        .brand-section {
            flex: 1;
            background: linear-gradient(135deg, #0d1b3e, #1a2a6c);
            color: white;
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .brand-section::before {
            content: "";
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            top: -50px;
            right: -50px;
        }

        .brand-section::after {
            content: "";
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            bottom: -100px;
            left: -100px;
        }

        .logo {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo-icon {
            font-size: 36px;
            margin-right: 15px;
            color: #ffd700;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .brand-title {
            font-size: 28px;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .brand-subtitle {
            font-size: 16px;
            line-height: 1.6;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .features {
            margin-top: 30px;
        }

        .feature {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .feature i {
            margin-right: 15px;
            color: #ffd700;
            font-size: 18px;
        }

        .forgot-section {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .forgot-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .forgot-title {
            font-size: 26px;
            color: #1a2a6c;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .forgot-subtitle {
            color: #666;
            font-size: 15px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 14px;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #1a2a6c;
            font-size: 16px;
        }

        .form-control {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e1e5eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8f9fc;
        }

        .form-control:focus {
            border-color: #1a2a6c;
            box-shadow: 0 0 0 3px rgba(26, 42, 108, 0.1);
            outline: none;
            background: white;
        }

        .error-message {
            color: #e53e3e;
            background: #fff5f5;
            padding: 12px;
            border-radius: 8px;
            margin: 10px 0;
            display: flex;
            align-items: center;
            font-weight: 500;
            animation: fadeIn 0.5s ease-out;
        }

        .error-message i {
            margin-right: 10px;
            font-size: 18px;
        }

        .success-message {
            color: #065f46;
            background: #d1fae5;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            font-weight: 500;
            animation: fadeIn 0.5s ease-out;
        }

        .success-message i {
            margin-right: 10px;
            font-size: 18px;
        }

        .btn-send {
            background: linear-gradient(to right, #1a2a6c, #3a5fc5);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 15px;
            box-shadow: 0 4px 15px rgba(26, 42, 108, 0.3);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-send:hover {
            background: linear-gradient(to right, #0d1b3e, #1a2a6c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 42, 108, 0.4);
        }

        .link-box {
            margin: 20px 0;
        }

        .link-container {
            display: flex;
            gap: 10px;
        }

        .link-container input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #e1e5eb;
            border-radius: 10px;
            font-size: 14px;
            background: #f8f9fc;
        }

        .copy-btn {
            background: #1a2a6c;
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .copy-btn:hover {
            background: #0d1b3e;
        }

        .form-footer {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            font-size: 14px;
        }

        .links a {
            color: #1a2a6c;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .links a:hover {
            color: #0d1b3e;
            text-decoration: underline;
        }

        .copyright {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 14px;
        }

        .copyright a {
            color: #1a2a6c;
            text-decoration: none;
            font-weight: 500;
        }

        /* Animation pour les champs de formulaire */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .forgot-section > * {
            animation: fadeIn 0.6s ease-out forwards;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .btn-send { animation-delay: 0.2s; }
        .form-footer { animation-delay: 0.3s; }
        .copyright { animation-delay: 0.4s; }
        
        .brand-section > * {
            animation: slideIn 0.6s ease-out forwards;
        }
        
        .logo { animation-delay: 0.1s; }
        .brand-title { animation-delay: 0.2s; }
        .brand-subtitle { animation-delay: 0.3s; }
        .features { animation-delay: 0.4s; }

        /* Responsive */
        @media (max-width: 950px) {
            .forgot-container {
                flex-direction: column;
                max-width: 600px;
            }
            
            .brand-section {
                padding: 30px;
            }
            
            .forgot-section {
                padding: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .link-container {
                flex-direction: column;
            }
            
            .copy-btn {
                padding: 12px;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .forgot-container {
                border-radius: 15px;
            }
            
            .form-control {
                padding: 12px 15px 12px 45px;
            }
            
            .brand-section {
                padding: 25px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="brand-section">
            <div class="logo">
                <i class="fas fa-building logo-icon"></i>
                <div class="logo-text">ELHALLAOUI</div>
            </div>
            <h1 class="brand-title">Réinitialisation du mot de passe</h1>
            <p class="brand-subtitle">Entrez votre adresse e-mail pour récupérer l'accès à votre compte professionnel.</p>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-shield-alt"></i>
                    <span>Sécurité des données garantie</span>
                </div>
                <div class="feature">
                    <i class="fas fa-lock"></i>
                    <span>Protection maximale de votre compte</span>
                </div>
                <div class="feature">
                    <i class="fas fa-sync-alt"></i>
                    <span>Récupération rapide et sécurisée</span>
                </div>
            </div>
        </div>
        
        <div class="forgot-section">
            <div class="forgot-header">
                <h2 class="forgot-title">Mot de passe oublié</h2>
                <p class="forgot-subtitle">Entrez votre adresse e-mail pour recevoir un lien de réinitialisation</p>
            </div>
            
            <?php if ($msg): ?>
                <?= $msg ?>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label for="email">Adresse e-mail</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="votre@email.com" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-send">
                    <i class="fas fa-paper-plane"></i> Envoyer le lien
                </button>
            </form>
            
            <div class="form-footer">
                <div class="links">
                    <a href="login.php">
                        <i class="fas fa-arrow-left"></i> Retour connexion
                    </a>
                </div>
            </div>
            
            <div class="copyright">
                &copy; <?= date('Y') ?> Elhallaoui. Tous droits réservés.
            </div>
        </div>
    </div>
</body>
</html>