<?php
// register.php - Version corrigée avec gestion de session

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérification de l'état de la session avant de la démarrer
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

$msg = '';
$username = $email = $role = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    $role = $_POST['role'];

    // Validation des champs
    if (empty($username) || empty($email) || empty($password) || empty($confirm) || empty($role)) {
        $msg = "<div class='error-message'><i class='fas fa-exclamation-circle'></i> Tous les champs sont obligatoires.</div>";
    } elseif ($password !== $confirm) {
        $msg = "<div class='error-message'><i class='fas fa-exclamation-circle'></i> Les mots de passe ne correspondent pas.</div>";
    } elseif (strlen($password) < 6) {
        $msg = "<div class='error-message'><i class='fas fa-exclamation-circle'></i> Le mot de passe doit contenir au moins 6 caractères.</div>";
    } else {
        // Vérifier si l'utilisateur existe déjà
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $msg = "<div class='error-message'><i class='fas fa-exclamation-circle'></i> Ce nom d'utilisateur ou email est déjà utilisé.</div>";
        } else {
            // Hasher le mot de passe
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Générer une référence unique
            $ref = uniqid('user_');
            
            // Insérer le nouvel utilisateur
            $stmt = $conn->prepare("INSERT INTO users (ref, username, email, password, role) VALUES (?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$ref, $username, $email, $hash, $role])) {
                $msg = "<div class='success-message'><i class='fas fa-check-circle'></i> Inscription réussie! <a href='login.php'>Se connecter</a></div>";
                // Réinitialiser les champs
                $username = $email = $role = '';
            } else {
                $msg = "<div class='error-message'><i class='fas fa-exclamation-circle'></i> Une erreur s'est produite. Veuillez réessayer.</div>";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Elhallaoui</title>
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
            min-height: 100vh;
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

        .centered-container {
            width: 100%;
            max-width: 500px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.25);
            animation: fadeIn 0.8s ease-out;
        }

        .logo-header {
            background: linear-gradient(135deg, #0d1b3e, #1a2a6c);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .logo-header::before {
            content: "";
            position: absolute;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            top: -50px;
            right: -50px;
        }

        .logo-header::after {
            content: "";
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            bottom: -100px;
            left: -100px;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .logo-icon {
            font-size: 32px;
            margin-right: 15px;
            color: #ffd700;
        }

        .logo-text {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .brand-title {
            font-size: 22px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .brand-subtitle {
            font-size: 14px;
            line-height: 1.5;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .features {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }

        .feature {
            display: flex;
            align-items: center;
            font-size: 12px;
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 12px;
            border-radius: 20px;
        }

        .feature i {
            margin-right: 8px;
            color: #ffd700;
            font-size: 14px;
        }

        .register-section {
            padding: 30px;
        }

        .register-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .register-title {
            font-size: 22px;
            color: #1a2a6c;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .register-subtitle {
            color: #666;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 20px;
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

        select.form-control {
            padding: 14px 15px;
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

        .btn-register {
            background: linear-gradient(to right, #1a2a6c, #3a5fc5);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 10px;
            font-size: 16px;
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

        .btn-register:hover {
            background: linear-gradient(to right, #0d1b3e, #1a2a6c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 42, 108, 0.4);
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

        .password-container {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #1a2a6c;
            font-size: 16px;
        }

        /* Animation pour les champs de formulaire */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .register-section > * {
            animation: fadeIn 0.6s ease-out forwards;
        }

        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.15s; }
        .form-group:nth-child(3) { animation-delay: 0.2s; }
        .form-group:nth-child(4) { animation-delay: 0.25s; }
        .form-group:nth-child(5) { animation-delay: 0.3s; }
        .btn-register { animation-delay: 0.35s; }
        .form-footer { animation-delay: 0.4s; }
        .copyright { animation-delay: 0.45s; }

        /* Responsive */
        @media (max-width: 600px) {
            .centered-container {
                border-radius: 15px;
            }
            
            .logo-header {
                padding: 20px;
            }
            
            .register-section {
                padding: 20px;
            }
            
            .form-control {
                padding: 12px 15px 12px 45px;
            }
            
            .features {
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            
            .feature {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="centered-container">
        <div class="logo-header">
            <div class="logo">
                <i class="fas fa-building logo-icon"></i>
                <div class="logo-text">ELHALLAOUI</div>
            </div>
            <h1 class="brand-title">Création de compte</h1>
            <p class="brand-subtitle">Rejoignez notre plateforme pour gérer vos projets entrepreneuriaux</p>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-shield-alt"></i>
                    <span>Sécurité garantie</span>
                </div>
                <div class="feature">
                    <i class="fas fa-chart-line"></i>
                    <span>Suivi détaillé</span>
                </div>
                <div class="feature">
                    <i class="fas fa-headset"></i>
                    <span>Support 24/7</span>
                </div>
            </div>
        </div>
        
        <div class="register-section">
            <div class="register-header">
                <h2 class="register-title">Créer un compte</h2>
                <p class="register-subtitle">Remplissez le formulaire pour créer votre compte</p>
            </div>
            
            <?php if ($msg): ?>
                <?= $msg ?>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label>Nom d'utilisateur</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" class="form-control" placeholder="Entrez votre nom d'utilisateur" value="<?= htmlspecialchars($username) ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Adresse email</label>
                    <div class="input-with-icon">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="Entrez votre adresse email" value="<?= htmlspecialchars($email) ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Mot de passe</label>
                    <div class="input-with-icon password-container">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control" placeholder="Créez un mot de passe" required>
                        <i class="toggle-password fas fa-eye" onclick="togglePassword(this)"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Confirmez le mot de passe</label>
                    <div class="input-with-icon password-container">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm" class="form-control" placeholder="Confirmez votre mot de passe" required>
                        <i class="toggle-password fas fa-eye" onclick="togglePassword(this)"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Rôle</label>
                    <select name="role" class="form-control" required>
                        <option value="">Sélectionnez un rôle</option>
                        <option value="entrepreneur" <?= $role === 'entrepreneur' ? 'selected' : '' ?>>Entrepreneur</option>
                        <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-register">
                    <i class="fas fa-user-plus"></i> Créer un compte
                </button>
            </form>
            
            <div class="form-footer">
                <div class="links">
                    <a href="login.php">
                        <i class="fas fa-sign-in-alt"></i> Déjà inscrit? Se connecter
                    </a>
                </div>
            </div>
            
            <div class="copyright">
                &copy; <?= date('Y') ?> Elhallaoui. Tous droits réservés.
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(icon) {
            const input = icon.previousElementSibling;
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }
    </script>
</body>
</html>