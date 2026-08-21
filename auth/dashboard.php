<?php
// dashboard.php - Version corrigée
require_once __DIR__ . '/../db.php';

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté en utilisant la clé 'ref'
if (empty($_SESSION['user']['ref'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$error = '';
$success = '';

// Calcul du chemin de base pour les liens
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base_path = ($base_path === '/' || $base_path === '\\') ? '' : $base_path;

// Racine du site (ex: /stage)
$site_base = rtrim(dirname($base_path), '/');

// Génération du token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Traitement de la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token CSRF invalide.";
    } else {
        // Récupérer les données du formulaire
        $nom = $_POST['lastname'] ?? '';
        $prenom = $_POST['firstname'] ?? '';
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        
        // Validation des champs
        if (empty($nom) || empty($prenom) || empty($username) || empty($email)) {
            $error = "Tous les champs sont obligatoires.";
        } else {
            $relativePath = $user['profile_picture']; // Par défaut, garder l'ancienne image
            $uploadSuccess = true;
            
            // Traitement de l'upload de l'image
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_picture'];
                
                // Vérifier le type de fichier
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (in_array($file['type'], $allowedTypes)) {
                    // Vérifier la taille du fichier (max 2MB)
                    $maxFileSize = 2 * 1024 * 1024; // 2MB
                    if ($file['size'] <= $maxFileSize) {
                        // Créer le répertoire d'upload dans le dossier auth/uploads
                        $uploadDir = __DIR__ . '/uploads/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        
                        // Générer un nom de fichier unique
                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = uniqid() . '_' . time() . '.' . $extension;
                        $destination = $uploadDir . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            // Chemin relatif pour la base de données
                            $relativePath = 'auth/uploads/' . $filename;
                        } else {
                            $error = "Erreur lors du déplacement du fichier uploadé.";
                            $uploadSuccess = false;
                        }
                    } else {
                        $error = "Le fichier est trop volumineux. Taille maximale autorisée: 2MB.";
                        $uploadSuccess = false;
                    }
                } else {
                    $error = "Type de fichier non autorisé. Seuls les JPEG, PNG et GIF sont acceptés.";
                    $uploadSuccess = false;
                }
            }
            
            // Si aucune erreur n'est survenue avec l'upload, on met à jour les autres champs
            if ($uploadSuccess && empty($error)) {
                try {
                    // Vérifier si les données ont changé
                    $hasChanged = ($nom !== $user['nom'] || 
                                  $prenom !== $user['prenom'] || 
                                  $username !== $user['username'] || 
                                  $email !== $user['email'] || 
                                  $relativePath !== $user['profile_picture']);
                    
                    if ($hasChanged) {
                        // Vérifier uniquement si l'email ou le username a changé
                        $checkRequired = false;
                        $checkMessage = "";
                        
                        if ($email !== $user['email']) {
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND ref != ?");
                            $stmt->execute([$email, $user['ref']]);
                            if ($stmt->fetchColumn() > 0) {
                                $checkRequired = true;
                                $checkMessage = "L'email est déjà utilisé par un autre compte.";
                            }
                        }
                        
                        if (!$checkRequired && $username !== $user['username']) {
                            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND ref != ?");
                            $stmt->execute([$username, $user['ref']]);
                            if ($stmt->fetchColumn() > 0) {
                                $checkRequired = true;
                                $checkMessage = "Le nom d'utilisateur est déjà utilisé par un autre compte.";
                            }
                        }
                        
                        if ($checkRequired) {
                            $error = $checkMessage;
                        } else {
                            // Mettre à jour la base de données
                            $sql = "UPDATE users SET 
                                    nom = ?, 
                                    prenom = ?, 
                                    username = ?, 
                                    email = ?, 
                                    profile_picture = ? 
                                    WHERE ref = ?";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute([$nom, $prenom, $username, $email, $relativePath, $user['ref']]);
                            
                            // Mettre à jour la session
                            $_SESSION['user']['nom'] = $nom;
                            $_SESSION['user']['prenom'] = $prenom;
                            $_SESSION['user']['username'] = $username;
                            $_SESSION['user']['email'] = $email;
                            $_SESSION['user']['profile_picture'] = $relativePath;
                            $user = $_SESSION['user']; // Mettre à jour les données locales
                            
                            $success = "Profil mis à jour avec succès!";
                        }
                    } else {
                        $success = "Aucune modification détectée. Votre profil est déjà à jour.";
                    }
                } catch (PDOException $e) {
                    $error = "Erreur lors de la mise à jour de la base de données: " . $e->getMessage();
                }
            }
        }
    }
}

// Récupérer les comptes dynamiques
$adminCount = 0;
$entrepreneurCount = 0;
$factureCount = 0;

try {
    // Compter les administrateurs (rôle 1 ou 2)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role IN (1, 2)");
    $stmt->execute();
    $adminCount = $stmt->fetchColumn();

    // Compter les entrepreneurs
    $stmt = $conn->prepare("SELECT COUNT(*) FROM entrepreneur");
    $stmt->execute();
    $entrepreneurCount = $stmt->fetchColumn();
    
    // Compter les factures
    $stmt = $conn->prepare("SELECT COUNT(*) FROM facture");
    $stmt->execute();
    $factureCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
}

// Récupération des fonctions et sociétés pour les menus déroulants
$fonctions = [];
$societes = [];

try {
    $stmt = $conn->prepare("SELECT id, nom FROM function WHERE statut = '1'");
    $stmt->execute();
    $fonctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($fonctions)) {
        $defaultFunctions = ['Directeur', 'Gérant', 'Responsable RH'];
        foreach ($defaultFunctions as $functionName) {
            $stmt = $conn->prepare("INSERT INTO function (nom, statut) VALUES (?, '1')");
            $stmt->execute([$functionName]);
        }
        
        $stmt = $conn->prepare("SELECT id, nom FROM function WHERE statut = '1'");
        $stmt->execute();
        $fonctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupération des sociétés
    $stmt = $conn->prepare("SELECT id, raison_sociale FROM ref_societe");
    $stmt->execute();
    $societes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($societes)) {
        $defaultSocietes = ['Société A', 'Société B', 'Société C'];
        foreach ($defaultSocietes as $societeName) {
            $stmt = $conn->prepare("INSERT INTO ref_societe (raison_sociale) VALUES (?)");
            $stmt->execute([$societeName]);
        }
        
        $stmt = $conn->prepare("SELECT id, raison_sociale FROM ref_societe");
        $stmt->execute();
        $societes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $fonctions = [
        ['id' => 1, 'nom' => 'Directeur'],
        ['id' => 2, 'nom' => 'Gérant'],
        ['id' => 3, 'nom' => 'Responsable RH']
    ];
    
    $societes = [
        ['id' => 1, 'raison_sociale' => 'Société A'],
        ['id' => 2, 'raison_sociale' => 'Société B'],
        ['id' => 3, 'raison_sociale' => 'Société C']
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - Elhallaoui</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* VOTRE CSS EXISTANT RESTE INCHANGÉ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #0d1b3e;
            --secondary: #1a2a6c;
            --accent: #f6ad01;
            --light: #f4f7fa;
            --dark: #2d3748;
            --success: #38a169;
            --warning: #dd6b20;
            --danger: #e53e3e;
            --card-bg: #ffffff;
            --sidebar-bg: #0d1b3e;
            --header-bg: #ffffff;
            --text-color: #333;
            --text-secondary: #666;
            --facture: #8e44ad;
        }

        /* Dark mode variables */
        .dark-mode {
            --primary: #3a86ff;
            --secondary: #8338ec;
            --accent: #ffbe0b;
            --light: #121212;
            --dark: #e0e0e0;
            --card-bg: #1e1e1e;
            --sidebar-bg: #0d1b3e;
            --header-bg: #1e1e1e;
            --text-color: #f0f0f0;
            --text-secondary: #b0b0b0;
            --facture: #9b59b6; /* Nouvelle couleur pour les factures en mode sombre */
        }

        body {
            background-color: var(--light);
            color: var(--dark);
            display: flex;
            min-height: 100vh;
            margin: 0;
            color: var(--text-color);
        }

        /* Barre latérale */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            color: white;
            padding: 25px 0;
            display: flex;
            flex-direction: column;
            transition: all 0.3s;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            z-index: 100;
        }

        .logo-container {
            padding: 0 25px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 25px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            font-size: 28px;
            color: var(--accent);
        }

        .logo-text {
            font-size: 22px;
            font-weight: 700;
        }

        .nav-links {
            flex: 1;
            padding: 0 15px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s;
            color: rgba(255,255,255,0.8);
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-item i {
            font-size: 18px;
            margin-right: 15px;
            width: 24px;
            text-align: center;
        }

        .nav-item span {
            font-size: 16px;
            font-weight: 500;
        }

        /* Contenu principal */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        /* En-tête */
        .header {
            background: var(--header-bg);
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            z-index: 10;
            position: sticky;
            top: 0;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(13, 27, 62, 0.1);
            color: var(--primary);
            cursor: pointer;
            transition: all 0.3s;
            font-size: 18px;
        }

        .header-icon:hover {
            background: rgba(13, 27, 62, 0.2);
        }

        .header-icon.logout:hover {
            background: rgba(229, 62, 62, 0.2);
            color: var(--danger);
        }

        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s;
            padding: 5px 10px;
            border-radius: 8px;
        }

        .user-info:hover {
            background: rgba(13, 27, 62, 0.05);
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
            background-size: cover;
            background-position: center;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-avatar-initials {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: var(--dark);
        }

        .user-role {
            font-size: 13px;
            color: var(--secondary);
            background: rgba(26, 42, 108, 0.1);
            padding: 3px 8px;
            border-radius: 20px;
            margin-top: 3px;
            text-align: center;
        }

        /* Contenu du tableau de bord */
        .dashboard-content {
            padding: 30px;
            flex: 1;
        }

        .welcome-section {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 20px rgba(13, 27, 62, 0.2);
        }

        .welcome-title {
            font-size: 28px;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .welcome-text {
            font-size: 16px;
            line-height: 1.6;
            max-width: 600px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-icon.users {
            background: rgba(56, 161, 105, 0.15);
            color: var(--success);
        }

        .stat-icon.admins {
            background: rgba(221, 107, 32, 0.15);
            color: var(--warning);
        }

        .stat-icon.factures {
            background: rgba(142, 68, 173, 0.15); /* Couleur pour les factures */
            color: var(--facture);
        }

        .stat-icon.activity {
            background: rgba(41, 128, 185, 0.15);
            color: #2980b9;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .stat-title {
            font-size: 16px;
            color: #718096;
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
        }

        .success-message i {
            margin-right: 10px;
            font-size: 18px;
        }

        /* Page de profil */
        .profile-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            display: none;
        }

        .profile-card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 40px 30px 80px;
            text-align: center;
            position: relative;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar-initials {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 50px;
            font-weight: bold;
        }

        .profile-avatar-edit {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: var(--accent);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            transition: all 0.3s;
        }

        .profile-avatar-edit:hover {
            transform: scale(1.1);
            background: #e09800;
        }

        .profile-avatar-edit i {
            font-size: 18px;
            color: white;
        }

        .profile-name {
            color: white;
            font-size: 28px;
            margin-top: 20px;
            font-weight: 700;
        }

        .profile-role {
            color: rgba(255,255,255,0.9);
            font-size: 16px;
            margin-top: 5px;
        }

        .profile-content {
            padding: 100px 40px 40px;
            position: relative;
        }

        .profile-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #e1e5eb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background: #f8f9fc;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(13, 27, 62, 0.1);
            outline: none;
            background: white;
        }

        .btn-save {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 16px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 15px;
            box-shadow: 0 4px 15px rgba(13, 27, 62, 0.3);
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-save:hover {
            background: linear-gradient(to right, #0a1530, #15255c);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(13, 27, 62, 0.4);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .logo-text, .nav-item span {
                display: none;
            }
            
            .nav-item {
                justify-content: center;
                padding: 15px;
            }
            
            .nav-item i {
                margin-right: 0;
                font-size: 20px;
            }

            .profile-form {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: span 1;
            }
        }
        
        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                padding: 15px;
            }
            
            .logo-container {
                padding: 0 15px 15px;
            }
            
            .nav-links {
                display: flex;
                gap: 10px;
                padding: 0;
            }
            
            .nav-item {
                margin-bottom: 0;
                flex: 1;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }

            .profile-header {
                padding: 30px 20px 70px;
            }

            .profile-content {
                padding: 80px 20px 30px;
            }
        }
        
        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .nav-links {
                flex-wrap: wrap;
            }
            
            .nav-item {
                flex: 40%;
            }

            .profile-avatar {
                width: 120px;
                height: 120px;
            }
            
            .profile-avatar-initials {
                font-size: 40px;
            }
        }

        /* Animation pour les cartes */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-card {
            animation: fadeIn 0.5s ease-out forwards;
        }
   </style>
</head>
<body>
    <!-- Barre latérale -->
    <div class="sidebar">
        <div class="logo-container">
            <div class="logo">
                <i class="fas fa-building logo-icon"></i>
                <div class="logo-text">ELHALLAOUI</div>
            </div>
        </div>
        
        <div class="nav-links">
            <div class="nav-item active" data-href="<?= $base_path ?>/dashboard.php">
                <i class="fas fa-home"></i>
                <span>Tableau de bord</span>
            </div>
            <div class="nav-item" data-href="<?= $base_path ?>/admin_list.php">
                <i class="fas fa-user-shield"></i>
                <span>Administrateurs</span>
            </div>
            <div class="nav-item" data-href="<?= $base_path ?>/entrepreneur_list.php">
                <i class="fas fa-user-tie"></i>
                <span>Entrepreneurs</span>
            </div>
            <div class="nav-item" data-href="<?= $base_path ?>/facture_list.php">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Factures</span>
            </div>
            <div class="nav-item" data-href="<?= $base_path ?>/statistics.php">
    <i class="fas fa-chart-pie"></i>
    <span>Statistiques</span>
</div>
            <div class="nav-item">
                <i class="fas fa-users"></i>
                <span>Utilisateurs</span>
            </div>
        </div>
    </div>
    
    <!-- Contenu principal -->
    <div class="main-content">
        <!-- En-tête -->
        <div class="header">
            <div class="page-title">Tableau de Bord</div>
            <div class="header-actions">
                <button id="darkModeToggle" class="header-icon" title="Basculer mode sombre">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="logout.php" class="header-icon logout" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
                <div class="user-info" id="userProfileBtn">
                    <div class="user-avatar">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <img src="<?= $site_base ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Avatar">
                        <?php else: ?>
                            <div class="user-avatar-initials">
                                <?= strtoupper(substr($user['prenom'], 0, 1)) . strtoupper(substr($user['nom'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></div>
                        <div class="user-role">
                            <?php 
                            $role = $user['role'] ?? $user['doca'] ?? 3;
                            switch($role) {
                                case 1: echo 'Super-Admin'; break;
                                case 2: echo 'Admin'; break;
                                default: echo 'Utilisateur'; break;
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contenu du tableau de bord -->
        <div class="dashboard-content" id="dashboardContent">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $success ?></span>
                </div>
            <?php endif; ?>
            
            <div class="welcome-section">
                <h1 class="welcome-title">Bienvenue, <?= htmlspecialchars($user['prenom']) ?>!</h1>
                <p class="welcome-text">Vous pouvez gérer votre compte et suivre les activités de votre plateforme Elhallaoui.</p>
            </div>
            
            <div class="stats-container">
                <div class="stat-card animate-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $adminCount ?></div>
                            <div class="stat-title">Administrateurs</div>
                        </div>
                        <div class="stat-icon admins">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card animate-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $entrepreneurCount ?></div>
                            <div class="stat-title">Entrepreneurs</div>
                        </div>
                        <div class="stat-icon users">
                            <i class="fas fa-user-tie"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card animate-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $factureCount ?></div>
                            <div class="stat-title">Factures</div>
                        </div>
                        <div class="stat-icon factures">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Page de profil -->
    <div id="profile" class="profile-container">
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php if (!empty($user['profile_picture'])): ?>
                        <img src="<?= $site_base ?>/<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile" id="profileAvatarImg">
                    <?php else: ?>
                        <div class="profile-avatar-initials" id="profileAvatarInitials">
                            <?= strtoupper(substr($user['prenom'], 0, 1)) . strtoupper(substr($user['nom'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                    <div class="profile-avatar-edit" id="editProfilePicture">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <h1 class="profile-name"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></h1>
                <div class="profile-role">
                    <?php 
                    switch($role) {
                        case 1: echo 'Super-Admin'; break;
                        case 2: echo 'Admin'; break;
                        default: echo 'Utilisateur'; break;
                    }
                    ?>
                </div>
            </div>
            
            <div class="profile-content">
                <?php if (!empty($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= $error ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <span><?= $success ?></span>
                    </div>
                <?php endif; ?>
                
                <form id="profileForm" method="post" enctype="multipart/form-data">
                    <input type="file" name="profile_picture" id="profilePictureInput" style="display: none;" accept="image/*">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    
                    <div class="form-group">
                        <label for="firstname">Prénom</label>
                        <input type="text" id="firstname" name="firstname" class="form-control" 
                               value="<?= htmlspecialchars($user['prenom']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lastname">Nom</label>
                        <input type="text" id="lastname" name="lastname" class="form-control" 
                               value="<?= htmlspecialchars($user['nom']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur</label>
                        <input type="text" id="username" name="username" class="form-control" 
                               value="<?= htmlspecialchars($user['username']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Téléphone</label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="06 12 34 56 78">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Rôle</label>
                        <input type="text" id="role" class="form-control" value="<?php 
                            switch($role) {
                                case 1: echo 'Super-Admin'; break;
                                case 2: echo 'Admin'; break;
                                default: echo 'Utilisateur'; break;
                            }
                        ?>" disabled>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="address">Adresse</label>
                        <textarea id="address" name="address" class="form-control" rows="3">123 Avenue des Champs-Élysées, Paris</textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Animation pour les cartes
        document.querySelectorAll('.stat-card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease-out';
            
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, 100 + (index * 100));
        });
        
        // Navigation vers le profil
        document.getElementById('userProfileBtn').addEventListener('click', function() {
            document.getElementById('dashboardContent').style.display = 'none';
            document.getElementById('profile').style.display = 'block';
            document.querySelector('.page-title').textContent = 'Profil Utilisateur';
            
            // Ajout du bouton de retour
            if (!document.querySelector('.back-button')) {
                const backButton = document.createElement('button');
                backButton.className = 'back-button';
                backButton.innerHTML = '<i class="fas fa-arrow-left"></i> Retour';
                backButton.style.background = 'var(--light)';
                backButton.style.border = 'none';
                backButton.style.padding = '8px 15px';
                backButton.style.borderRadius = '8px';
                backButton.style.cursor = 'pointer';
                backButton.style.marginRight = '15px';
                backButton.style.transition = 'all 0.3s';
                
                backButton.addEventListener('mouseover', function() {
                    this.style.background = '#e2e8f0';
                });
                
                backButton.addEventListener('mouseout', function() {
                    this.style.background = 'var(--light)';
                });
                
                backButton.addEventListener('click', function() {
                    document.getElementById('dashboardContent').style.display = 'block';
                    document.getElementById('profile').style.display = 'none';
                    document.querySelector('.page-title').textContent = 'Tableau de Bord';
                    this.remove();
                });
                
                document.querySelector('.header').insertBefore(backButton, document.querySelector('.page-title'));
            }
        });
        
        // Gestion des clics sur les éléments du menu
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.nav-item').forEach(el => {
                    el.classList.remove('active');
                });
                this.classList.add('active');
                if (this.hasAttribute('data-href')) {
                    window.location.href = this.getAttribute('data-href');
                }
            });
        });
        
        // Dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        const darkModeIcon = darkModeToggle.querySelector('i');
        
        if (localStorage.getItem('darkMode') === 'enabled') {
            document.body.classList.add('dark-mode');
            darkModeIcon.classList.remove('fa-moon');
            darkModeIcon.classList.add('fa-sun');
        }
        
        darkModeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            
            if (document.body.classList.contains('dark-mode')) {
                localStorage.setItem('darkMode', 'enabled');
                darkModeIcon.classList.remove('fa-moon');
                darkModeIcon.classList.add('fa-sun');
            } else {
                localStorage.setItem('darkMode', 'disabled');
                darkModeIcon.classList.remove('fa-sun');
                darkModeIcon.classList.add('fa-moon');
            }
        });
        
        // Gestion de l'upload de la photo de profil
        document.getElementById('editProfilePicture').addEventListener('click', function() {
            document.getElementById('profilePictureInput').click();
        });
        
        document.getElementById('profilePictureInput').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Créer une prévisualisation
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Nouvelle photo de profil';
                    img.style.width = '100%';
                    img.style.height = '100%';
                    img.style.objectFit = 'cover';
                    
                    // Remplacer l'avatar existant
                    const profileAvatar = document.querySelector('.profile-avatar');
                    const initials = document.getElementById('profileAvatarInitials');
                    const existingImg = document.getElementById('profileAvatarImg');
                    
                    if (existingImg) {
                        existingImg.src = e.target.result;
                    } else if (initials) {
                        initials.style.display = 'none';
                        profileAvatar.appendChild(img);
                        img.id = 'profileAvatarImg';
                    } else {
                        profileAvatar.innerHTML = '';
                        profileAvatar.appendChild(img);
                        img.id = 'profileAvatarImg';
                    }
                    
                    // Ajouter l'icône de caméra
                    const cameraIcon = document.createElement('div');
                    cameraIcon.className = 'profile-avatar-edit';
                    cameraIcon.innerHTML = '<i class="fas fa-camera"></i>';
                    cameraIcon.id = 'editProfilePicture';
                    cameraIcon.addEventListener('click', function() {
                        document.getElementById('profilePictureInput').click();
                    });
                    profileAvatar.appendChild(cameraIcon);
                };
                
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>