<?php
require_once __DIR__ . '/../db.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    // Recherche par e‑mail
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['actif'] == '1' && password_verify($pass, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;          // on stocke toute la ligne
        header('Location: dashboard.php');
        exit;
    }
    $msg = "Identifiants incorrects.";
}

// Correction du chemin du logo
$logoPath = '../assets/css/logohallaoui.png'; // Chemin relatif corrigé
$absolutePath = realpath(__DIR__ . '/../assets/css/logohallaoui.png');
$logoFound = $absolutePath && file_exists($absolutePath);
$webPath = $logoFound ? $logoPath : '';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Elhallaoui</title>
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
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .login-container {
            display: flex;
            width: 900px;
            height: 550px;
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
            padding: 50px 40px;
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
            justify-content: center;
            margin-bottom: 30px;
            flex-direction: column;
        }

        .logo-img {
            max-width: 250px;
            max-height: 100px;
            height: auto;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 1px;
            text-align: center;
            margin-top: 15px;
        }

        .logo-error {
            color: #ff9999;
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
            font-style: italic;
        }

        .brand-title {
            font-size: 32px;
            margin-bottom: 15px;
            font-weight: 700;
            text-align: center;
        }

        .brand-subtitle {
            font-size: 18px;
            line-height: 1.6;
            opacity: 0.9;
            margin-bottom: 30px;
            text-align: center;
        }

        .features {
            margin-top: 30px;
        }

        .feature {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            font-size: 16px;
        }

        .feature i {
            margin-right: 15px;
            color: #ffd700;
            font-size: 20px;
        }

        .login-section {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-title {
            font-size: 28px;
            color: #1a2a6c;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .login-subtitle {
            color: #666;
            font-size: 16px;
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
            font-size: 18px;
        }

        .form-control {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e1e5eb;
            border-radius: 10px;
            font-size: 16px;
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
            margin: 15px 0;
            display: flex;
            align-items: center;
            font-weight: 500;
            animation: fadeIn 0.5s ease-out;
        }

        .error-message i {
            margin-right: 10px;
            font-size: 18px;
        }

        .btn-login {
            background: linear-gradient(to right, #1a2a6c, #3a5fc5);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(26, 42, 108, 0.3);
        }

        .btn-login:hover {
            background: linear-gradient(to right, #0d1b3e, #1a2a6c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 42, 108, 0.4);
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
        }

        .remember-me input {
            margin-right: 8px;
        }

        .links a {
            color: #1a2a6c;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            margin-left: 15px;
        }

        .links a:hover {
            color: #0d1b3e;
            text-decoration: underline;
        }

        .copyright {
            text-align: center;
            margin-top: 40px;
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

        .login-section > * {
            animation: fadeIn 0.6s ease-out forwards;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .btn-login { animation-delay: 0.3s; }
        .form-footer { animation-delay: 0.4s; }
        .copyright { animation-delay: 0.5s; }
        
        .brand-section > * {
            animation: slideIn 0.6s ease-out forwards;
        }
        
        .logo { animation-delay: 0.1s; }
        .brand-title { animation-delay: 0.2s; }
        .brand-subtitle { animation-delay: 0.3s; }
        .features { animation-delay: 0.4s; }

        /* Responsive */
        @media (max-width: 950px) {
            .login-container {
                width: 90%;
                height: auto;
                flex-direction: column;
            }
            
            .brand-section {
                padding: 30px;
            }
            
            .login-section {
                padding: 40px 30px;
            }

            .logo-img {
                max-width: 200px;
            }
        }
        
        @media (max-width: 480px) {
            .form-footer {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            
            .links a {
                margin: 0 8px;
            }

            .logo-img {
                max-width: 180px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="brand-section">
            <div class="logo">
                <?php if ($logoFound): ?>
                    <img src="<?= $webPath ?>" alt="Logo Elhallaoui" class="logo-img">
                <?php else: ?>
                    <i class="fas fa-building" style="font-size: 50px; color: #ffd700; margin-bottom: 15px;"></i>
                    <div class="logo-text">ELHALLAOUI</div>
                    <div class="logo-error">
                        Logo non trouvé : <?= htmlspecialchars($logoPath) ?>
                        <?php if ($absolutePath): ?>
                            <br>(Chemin absolu: <?= htmlspecialchars($absolutePath) ?>)
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <h1 class="brand-title">Bienvenue sur notre plateforme</h1>
            <p class="brand-subtitle">Connectez-vous pour accéder à votre espace professionnel et gérer vos activités en toute simplicité.</p>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-shield-alt"></i>
                    <span>Sécurité et confidentialité garanties</span>
                </div>
                <div class="feature">
                    <i class="fas fa-sync-alt"></i>
                    <span>Synchronisation en temps réel</span>
                </div>
                <div class="feature">
                    <i class="fas fa-headset"></i>
                    <span>Support technique 24/7</span>
                </div>
            </div>
        </div>
        
        <div class="login-section">
            <div class="login-header">
                <h2 class="login-title">Connexion à votre compte</h2>
                <p class="login-subtitle">Entrez vos informations d'identification pour accéder à votre espace</p>
            </div>
            
            <form method="post">
                <div class="form-group">
                    <label for="email">Adresse e-mail</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" class="form-control" placeholder="votre@email.com" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Votre mot de passe" required>
                    </div>
                </div>
                
                <?php if ($msg): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span id="errorMsg"><?= $msg ?></span>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn-login">Se connecter</button>
            </form>
            
            <div class="form-footer">
                <div class="remember-me">
                    <input type="checkbox" id="remember">
                    <label for="remember">Se souvenir de moi</label>
                </div>
                
                <div class="links">
                    <a href="register.php">Créer un compte</a>
                    <a href="forgot.php">Mot de passe oublié?</a>
                </div>
            </div>
            
            <div class="copyright">
                &copy; <?= date('Y') ?> Elhallaoui. Tous droits réservés. <a href="#">Politique de confidentialité</a>
            </div>
        </div>
    </div>
</body>
</html>