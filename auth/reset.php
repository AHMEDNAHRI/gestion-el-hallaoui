<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe - ELHALLAOUI</title>
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
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #0d1b3e, #1a2a6c);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: "";
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            top: -50px;
            right: -50px;
        }

        .header::after {
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
            margin-bottom: 15px;
        }

        .logo-icon {
            font-size: 36px;
            margin-right: 15px;
            color: #ffd700;
        }

        .logo-text {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .brand-title {
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .brand-subtitle {
            font-size: 16px;
            line-height: 1.6;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .features {
            list-style: none;
            margin: 20px 0 0;
            padding: 15px;
            text-align: left;
        }

        .feature {
            padding: 8px 0;
            display: flex;
            align-items: center;
            color: white;
            font-weight: 500;
        }

        .feature i {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .content {
            padding: 35px;
        }

        .section-title {
            font-size: 22px;
            color: #1a2a6c;
            margin-bottom: 10px;
            font-weight: 700;
            text-align: center;
        }

        .section-subtitle {
            color: #666;
            font-size: 15px;
            margin-bottom: 25px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 22px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 15px;
            display: flex;
            align-items: center;
        }

        .form-group label i {
            margin-right: 10px;
            color: #1a2a6c;
            font-size: 14px;
        }

        .input-container {
            position: relative;
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

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #1a2a6c;
            font-size: 16px;
        }

        .btn-reset {
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

        .btn-reset:hover {
            background: linear-gradient(to right, #0d1b3e, #1a2a6c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(26, 42, 108, 0.4);
        }

        .form-footer {
            display: flex;
            justify-content: center;
            margin-top: 25px;
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
            margin-top: 30px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        .copyright a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
        }

        /* Animation pour les champs de formulaire */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .content > * {
            animation: fadeIn 0.6s ease-out forwards;
        }

        .section-title { animation-delay: 0.1s; }
        .form-group:nth-child(1) { animation-delay: 0.2s; }
        .form-group:nth-child(2) { animation-delay: 0.3s; }
        .btn-reset { animation-delay: 0.4s; }
        .form-footer { animation-delay: 0.5s; }

        /* Responsive */
        @media (max-width: 600px) {
            .card {
                border-radius: 15px;
            }
            
            .header {
                padding: 25px 20px;
            }
            
            .content {
                padding: 25px;
            }
            
            .logo-text {
                font-size: 28px;
            }
            
            .brand-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="logo">
                    <i class="fas fa-building logo-icon"></i>
                    <div class="logo-text">ELHALLAOUI</div>
                </div>
                
                <h1 class="brand-title">Réinitialisation du mot de passe</h1>
                <p class="brand-subtitle">Entrez votre nouveau mot de passe pour récupérer l'accès à votre compte professionnel.</p>
                
                <ul class="features">
                    <li class="feature">
                        <i class="fas fa-shield-alt"></i>
                        <span>Sécurité des données garantie</span>
                    </li>
                    <li class="feature">
                        <i class="fas fa-lock"></i>
                        <span>Protection maximale de votre compte</span>
                    </li>
                    <li class="feature">
                        <i class="fas fa-sync-alt"></i>
                        <span>Récupération rapide et sécurisée</span>
                    </li>
                </ul>
            </div>
            
            <div class="content">
                <h2 class="section-title">Nouveau mot de passe</h2>
                <p class="section-subtitle">Entrez et confirmez votre nouveau mot de passe</p>
                
                <form>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Nouveau mot de passe</label>
                        <div class="input-container">
                            <i class="input-icon fas fa-lock"></i>
                            <input type="password" class="form-control" placeholder="Saisissez votre nouveau mot de passe" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-redo"></i> Confirmez le mot de passe</label>
                        <div class="input-container">
                            <i class="input-icon fas fa-lock"></i>
                            <input type="password" class="form-control" placeholder="Confirmez votre nouveau mot de passe" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-reset">
                        <i class="fas fa-sync-alt"></i> Changer le mot de passe
                    </button>
                </form>
                
                <div class="form-footer">
                    <div class="links">
                        <a href="login.php">
                            <i class="fas fa-arrow-left"></i> Retour connexion
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="copyright">
            &copy; 2025 Elhallaoui . Tous droits réservés.
        </div>
    </div>
</body>
</html>