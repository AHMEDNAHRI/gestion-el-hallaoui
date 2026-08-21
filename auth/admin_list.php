<?php
require_once __DIR__ . '/../db.php';

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calcul du chemin de base
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base_path = ($base_path === '/' || $base_path === '\\') ? '' : $base_path;
$site_base = rtrim(dirname($base_path), '/'); // Ajout pour la photo de profil

if (empty($_SESSION['user']['ref'])) {
    header('Location: ' . $base_path . '/login.php');
    exit;
}


$user = $_SESSION['user'];
$error = '';
$success = '';
// Vérifier si l'utilisateur est un comptable (role = 3) OU un service admin (role = 2)
$userRole = isset($user['role']) ? $user['role'] : (isset($user['doca']) ? $user['doca'] : 3);
if ($userRole == 3 || $userRole == 2) {
    // Utiliser un chemin relatif au lieu de $base_path
    header('Location: dashboard.php');
    exit;
}

// Génération du token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Traitement de la mise à jour de l'ordre via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $order = $_POST['order'];
    
    try {
        $conn->beginTransaction();
        
        foreach ($order as $position => $adminId) {
            $stmt = $conn->prepare("UPDATE users SET order_position = ? WHERE ref = ?");
            $stmt->execute([$position, $adminId]);
        }
        
        $conn->commit();
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()]);
        exit;
    }
}

// Gestion de l'ajout d'un administrateur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    // Validation CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de sécurité invalide";
    } else {
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $role = $_POST['role']; // Rôle sélectionné
        $add_by = $user['prenom'] . '-' . $user['nom']; // Ajouté par utilisateur connecté

        // Vérification des champs obligatoires
        if (empty($nom) || empty($prenom) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error = "Tous les champs obligatoires doivent être remplis.";
        } elseif ($password !== $confirm_password) {
            $error = "Les mots de passe ne correspondent pas.";
        } else {
            // Vérifier si l'email ou le nom d'utilisateur existe déjà
            $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$email, $username]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                $error = "L'email ou le nom d'utilisateur existe déjà.";
            } else {
                // Hachage du mot de passe
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                try {
                    $stmt = $conn->prepare("INSERT INTO users (nom, prenom, username, email, password, actif, role, date_add, add_by)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
                    $stmt->execute([
                        $nom,
                        $prenom,
                        $username,
                        $email,
                        $hashedPassword,
                        1,       // actif = 1
                        $role,
                        $add_by
                    ]);

                    $success = "Administrateur ajouté avec succès!";
                } catch (PDOException $e) {
                    $error = "Erreur lors de l'ajout: " . $e->getMessage();
                }
            }
        }
    }
}

// Traitement du blocage/déblocage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    // Validation CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de sécurité invalide";
    } else {
        $adminId = $_POST['admin_id'];
        $newStatus = $_POST['new_status'];
        
        try {
            $stmt = $conn->prepare("UPDATE users SET actif = ? WHERE ref = ?");
            $stmt->execute([$newStatus, $adminId]);
            
            if ($newStatus == 1) {
                $success = "Administrateur débloqué avec succès!";
            } else {
                $success = "Administrateur bloqué avec succès!";
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de la mise à jour: " . $e->getMessage();
        }
    }
}

// Traitement de la modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin'])) {
    // Validation CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de sécurité invalide";
    } else {
        $adminId = $_POST['admin_id'];
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $role = $_POST['role'];
        
        try {
            $stmt = $conn->prepare("UPDATE users SET nom = ?, prenom = ?, username = ?, email = ?, role = ? WHERE ref = ?");
            $stmt->execute([$nom, $prenom, $username, $email, $role, $adminId]);
            
            $success = "Administrateur modifié avec succès!";
        } catch (PDOException $e) {
            $error = "Erreur lors de la modification: " . $e->getMessage();
        }
    }
}

// TRAITEMENT DE LA SUPPRESSION - AJOUTÉ ICI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    // Validation CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Token de sécurité invalide";
    } else {
        $adminId = $_POST['admin_id'];
        
        // Empêcher l'utilisateur de se supprimer lui-même
        if ($adminId == $user['ref']) {
            $error = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM users WHERE ref = ?");
                $stmt->execute([$adminId]);
                
                if ($stmt->rowCount() > 0) {
                    $success = "Administrateur supprimé avec succès!";
                } else {
                    $error = "Aucun administrateur trouvé avec cet ID.";
                }
            } catch (PDOException $e) {
                $error = "Erreur lors de la suppression: " . $e->getMessage();
            }
        }
    }
}

// Récupérer la liste des administrateurs
$admins = [];
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE role IN (1, 2, 3) ORDER BY order_position ASC, date_add DESC");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des administrateurs: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Administrateurs - Elhallaoui</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <style>
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

        .content-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            font-size: 0.9em;
            border-radius: 5px 5px 0 0;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.15);
            background-color: var(--card-bg);
        }
        
        .content-table thead tr {
            background-color: var(--primary);
            color: #ffffff;
            text-align: left;
            font-weight: bold;
        }
        
        .content-table th,
        .content-table td {
            padding: 12px 15px;
            color: var(--text-color);
        }
        
        .content-table tbody tr {
            border-bottom: 1px solid var(--light);
        }
        
        .content-table tbody tr:nth-of-type(even) {
            background-color: rgba(0,0,0,0.05);
        }
        
        .content-table tbody tr:last-of-type {
            border-bottom: 2px solid var(--primary);
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-edit, .btn-delete, .btn-toggle {
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-edit {
            background-color: var(--success);
        }
        
        .btn-delete {
            background-color: var(--danger);
        }
        
        .btn-toggle {
            background-color: var(--warning);
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

        .success-message {
            color: var(--success);
            background: #f0fff4;
            padding: 12px;
            border-radius: 8px;
            margin: 15px 0;
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .dark-mode .error-message {
            color: #ff6b6b;
            background: #2c0a0a;
        }

        .dark-mode .success-message {
            color: #68d391;
            background: #1a2c1d;
        }

        .error-message i,
        .success-message i {
            margin-right: 10px;
            font-size: 18px;
        }

        /* Styles pour le drag and drop */
        .drag-handle {
            cursor: move;
            padding: 0 10px;
            color: var(--text-secondary);
        }

        .sortable-ghost {
            background-color: rgba(13, 27, 62, 0.1) !important;
            opacity: 0.7;
        }

        .sortable-chosen {
            background-color: rgba(246, 173, 1, 0.1) !important;
        }

        .content-table tbody tr {
            transition: background-color 0.3s ease;
        }

        /* Style pour le bouton "Nouveau Administrateur" */
        .add-admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .btn-add-admin {
            background-color: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-add-admin:hover {
            background-color: var(--secondary);
        }
        
        .btn-add-admin i {
            font-size: 16px;
        }

        /* Modal pour ajouter/modifier un administrateur */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: var(--card-bg);
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.3);
            width: 80%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            position: sticky;
            top: 0;
            background: var(--card-bg);
            padding: 10px 0;
            z-index: 10;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }
        
        .close-modal {
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: var(--dark);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
            position: sticky;
            bottom: 0;
            background: var(--card-bg);
            padding: 15px 0;
            z-index: 10;
        }
        
        .btn-close {
            background: #e53e3e;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
        }
        
        .btn-add, .btn-update {
            background: var(--success);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
        }

        .form-group {
            margin-bottom: 20px;
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
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-active {
            background-color: var(--success);
        }
        
        .status-inactive {
            background-color: var(--danger);
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
            
            .content-table {
                display: block;
                overflow-x: auto;
            }
            
            .add-admin-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .modal-content {
                width: 90%;
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-actions {
                align-self: flex-end;
            }
            
            .nav-links {
                flex-wrap: wrap;
            }
            
            .nav-item {
                flex: 40%;
            }

            .action-buttons {
                flex-direction: column;
            }
            
            .modal-buttons {
                flex-direction: column;
            }

            .btn-close, .btn-add, .btn-update {
                width: 100%;
            }
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
            <div class="nav-item" data-href="<?= $base_path ?>/dashboard.php">
                <i class="fas fa-home"></i>
                <span>Tableau de bord</span>
            </div>
            <div class="nav-item active">
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

    <div class="main-content">
        <div class="header">
            <div class="page-title">Liste des Administrateurs</div>
            <div class="header-actions">
                <!-- Bouton Dark Mode -->
                <button id="darkModeToggle" class="header-icon" title="Basculer mode sombre">
                    <i class="fas fa-moon"></i>
                </button>
                
                <!-- Bouton Déconnexion -->
                <a href="<?= $base_path ?>/logout.php" class="header-icon logout" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
                
                <!-- User Info -->
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

        <div class="dashboard-content">
            <!-- En-tête avec bouton d'ajout -->
            <div class="add-admin-header">
                <h1>Liste des administrateurs</h1>
                <button id="addAdminBtn" class="btn-add-admin">
                    <i class="fas fa-plus"></i> NOUVEAU ADMINISTRATEUR
                </button>
            </div>
            
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
            
            <div id="orderSuccess" class="success-message" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span>Ordre mis à jour avec succès!</span>
            </div>
            
            <table class="content-table">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Nom d'utilisateur</th>
                        <th>Rôle</th>
                        <th>Date d'ajout</th>
                        <th>Ajouté par</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="sortableAdmins">
                    <?php if (count($admins) > 0): ?>
                        <?php foreach ($admins as $admin): 
                            // Déterminer le libellé du rôle
                            $roleLabel = '';
                            switch($admin['role']) {
                                case 1: $roleLabel = 'Super Admin'; break;
                                case 2: $roleLabel = 'Administrateur'; break;
                                case 3: $roleLabel = 'Comptable'; break;
                                default: $roleLabel = 'Inconnu'; break;
                            }
                        ?>
                            <tr data-id="<?= $admin['ref'] ?>">
                                <td class="drag-handle"><i class="fas fa-grip-lines"></i></td>
                                <td><?= $admin['ref'] ?></td>
                                <td><?= htmlspecialchars($admin['prenom'] . ' ' . htmlspecialchars($admin['nom'])) ?></td>
                                <td><?= htmlspecialchars($admin['email']) ?></td>
                                <td><?= htmlspecialchars($admin['username']) ?></td>
                                <td><?= $roleLabel ?></td>
                                <td><?= $admin['date_add'] ?></td>
                                <td><?= htmlspecialchars($admin['add_by']) ?></td>
                                <td>
                                    <?php if ($admin['actif'] == 1): ?>
                                        <span class="status-indicator status-active"></span> Actif
                                    <?php else: ?>
                                        <span class="status-indicator status-inactive"></span> Bloqué
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn-toggle" 
                                        data-id="<?= $admin['ref'] ?>" 
                                        data-active="<?= $admin['actif'] ?>"
                                        title="<?= $admin['actif'] == 1 ? 'Bloquer' : 'Débloquer' ?>">
                                        <?php if ($admin['actif'] == 1): ?>
                                            <i class="fas fa-lock"></i>
                                        <?php else: ?>
                                            <i class="fas fa-lock-open"></i>
                                        <?php endif; ?>
                                    </button>
                                    <button class="btn-edit" 
                                        data-id="<?= $admin['ref'] ?>" 
                                        data-nom="<?= htmlspecialchars($admin['nom']) ?>" 
                                        data-prenom="<?= htmlspecialchars($admin['prenom']) ?>" 
                                        data-username="<?= htmlspecialchars($admin['username']) ?>" 
                                        data-email="<?= htmlspecialchars($admin['email']) ?>" 
                                        data-role="<?= $admin['role'] ?>"
                                        title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" data-id="<?= $admin['ref'] ?>" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center;">Aucun administrateur trouvé.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="instructions" style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 10px;"></i>
                <span>Glissez-déposez les lignes pour réorganiser la liste des administrateurs.</span>
            </div>
        </div>
    </div>
    
    <!-- Modal pour ajouter un administrateur -->
    <div id="addAdminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Ajouter Nouvel Administrateur</h2>
                <span class="close-modal">&times;</span>
            </div>

            <?php if (!empty($error) && isset($_POST['add_admin'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success) && isset($_POST['add_admin'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form id="adminForm" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="form-grid">
                    <!-- Colonne gauche -->
                    <div>
                        <div class="form-group">
                            <label for="nom_admin">Nom</label>
                            <input type="text" id="nom_admin" name="nom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="prenom_admin">Prénom</label>
                            <input type="text" id="prenom_admin" name="prenom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="username_admin">Nom d'utilisateur</label>
                            <input type="text" id="username_admin" name="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="role_admin">Rôle</label>
                            <select id="role_admin" name="role" class="form-control" required>
                                <option value="1">Super Admin</option>
                                <option value="2" selected>Administrateur</option>
                                <option value="3">Comptable</option>
                            </select>
                        </div>
                    </div>

                    <!-- Colonne droite -->
                    <div>
                        <div class="form-group">
                            <label for="email_admin">E-mail</label>
                            <input type="email" id="email_admin" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="password_admin">Mot de passe</label>
                            <input type="password" id="password_admin" name="password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password_admin">Confirmer le mot de passe</label>
                            <input type="password" id="confirm_password_admin" name="confirm_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="add_by_admin">Ajouté par</label>
                            <input type="text" id="add_by_admin" class="form-control" value="<?= htmlspecialchars($user['prenom'] . '-' . $user['nom']) ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-close">FERMER</button>
                    <button type="submit" class="btn-add" name="add_admin">AJOUTER</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal pour modifier un administrateur -->
    <div id="editAdminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Modifier Administrateur</h2>
                <span class="close-modal">&times;</span>
            </div>

            <form id="editAdminForm" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="admin_id" id="edit_admin_id">
                <input type="hidden" name="edit_admin" value="1">

                <div class="form-grid">
                    <!-- Colonne gauche -->
                    <div>
                        <div class="form-group">
                            <label for="edit_nom_admin">Nom</label>
                            <input type="text" id="edit_nom_admin" name="nom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_prenom_admin">Prénom</label>
                            <input type="text" id="edit_prenom_admin" name="prenom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_username_admin">Nom d'utilisateur</label>
                            <input type="text" id="edit_username_admin" name="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_role_admin">Rôle</label>
                            <select id="edit_role_admin" name="role" class="form-control" required>
                                <option value="1">Super Admin</option>
                                <option value="2">Administrateur</option>
                                <option value="3">Comptable</option>
                            </select>
                        </div>
                    </div>

                    <!-- Colonne droite -->
                    <div>
                        <div class="form-group">
                            <label for="edit_email_admin">E-mail</label>
                            <input type="email" id="edit_email_admin" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_add_by_admin">Ajouté par</label>
                            <input type="text" id="edit_add_by_admin" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label for="edit_date_add_admin">Date d'ajout</label>
                            <input type="text" id="edit_date_add_admin" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label for="edit_status_admin">Statut</label>
                            <input type="text" id="edit_status_admin" class="form-control" readonly>
                        </div>
                    </div>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-close">FERMER</button>
                    <button type="submit" class="btn-update">MODIFIER</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Gestion des clics sur les éléments du menu
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                // Retirer la classe active de tous les éléments
                document.querySelectorAll('.nav-item').forEach(el => {
                    el.classList.remove('active');
                });
                
                // Ajouter la classe active à l'élément cliqué
                this.classList.add('active');
                
                // Redirection si l'élément a un attribut data-href
                if (this.hasAttribute('data-href')) {
                    window.location.href = this.getAttribute('data-href');
                }
            });
        });
        
        // Dark mode toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        const darkModeIcon = darkModeToggle.querySelector('i');
        
        // Vérifier le localStorage pour le mode sombre
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
        
        // Initialisation du drag and drop avec SortableJS
        document.addEventListener('DOMContentLoaded', function() {
            const sortableAdmins = document.getElementById('sortableAdmins');
            
            if (sortableAdmins) {
                new Sortable(sortableAdmins, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: function(evt) {
                        // Récupérer les IDs dans le nouvel ordre
                        const rows = Array.from(sortableAdmins.querySelectorAll('tr'));
                        const newOrder = rows.map(row => row.getAttribute('data-id'));
                        
                        // Envoyer la nouvelle commande au serveur
                        fetch(window.location.href, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'update_order=true&order=' + JSON.stringify(newOrder)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Afficher un message de succès
                                const successMessage = document.getElementById('orderSuccess');
                                successMessage.style.display = 'flex';
                                
                                // Masquer le message après 3 secondes
                                setTimeout(() => {
                                    successMessage.style.display = 'none';
                                }, 3000);
                            } else {
                                console.error('Erreur lors de la mise à jour:', data.message);
                                alert('Erreur lors de la mise à jour de l\'ordre: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Erreur:', error);
                            alert('Une erreur est survenue: ' + error.message);
                        });
                    }
                });
            }
        });
        
        // Modal pour ajouter un administrateur
        const addAdminBtn = document.getElementById('addAdminBtn');
        const adminModal = document.getElementById('addAdminModal');
        const closeAdminModal = adminModal.querySelector('.close-modal');
        const closeAdminBtn = adminModal.querySelector('.btn-close');
        
        addAdminBtn.addEventListener('click', function() {
            adminModal.style.display = 'block';
        });
        
        closeAdminModal.addEventListener('click', function() {
            adminModal.style.display = 'none';
        });
        
        closeAdminBtn.addEventListener('click', function() {
            adminModal.style.display = 'none';
        });
        
        // Modal pour modifier un administrateur
        const editAdminModal = document.getElementById('editAdminModal');
        const closeEditAdminModal = editAdminModal.querySelector('.close-modal');
        const closeEditAdminBtn = editAdminModal.querySelector('.btn-close');
        
        closeEditAdminModal.addEventListener('click', function() {
            editAdminModal.style.display = 'none';
        });
        
        closeEditAdminBtn.addEventListener('click', function() {
            editAdminModal.style.display = 'none';
        });
        
        // Fermer les modals en cliquant à l'extérieur
        window.addEventListener('click', function(event) {
            if (event.target === adminModal) {
                adminModal.style.display = 'none';
            }
            if (event.target === editAdminModal) {
                editAdminModal.style.display = 'none';
            }
        });
        
        // Validation du formulaire d'ajout d'admin
        document.getElementById('adminForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password_admin').value;
            const confirmPassword = document.getElementById('confirm_password_admin').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert("Les mots de passe ne correspondent pas.");
            }
        });
        
        // Gestion du bouton de blocage/déblocage
        document.querySelectorAll('.btn-toggle').forEach(button => {
            button.addEventListener('click', function() {
                const adminId = this.getAttribute('data-id');
                const currentStatus = this.getAttribute('data-active');
                const newStatus = currentStatus == 1 ? 0 : 1;
                
                // Créer un formulaire pour envoyer la requête
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = 'csrf_token';
                csrfToken.value = '<?= $_SESSION['csrf_token'] ?>';
                
                const adminIdInput = document.createElement('input');
                adminIdInput.type = 'hidden';
                adminIdInput.name = 'admin_id';
                adminIdInput.value = adminId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'new_status';
                statusInput.value = newStatus;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'toggle_status';
                actionInput.value = '1';
                
                form.appendChild(csrfToken);
                form.appendChild(adminIdInput);
                form.appendChild(statusInput);
                form.appendChild(actionInput);
                
                document.body.appendChild(form);
                form.submit();
            });
        });
        
        // Gestion du bouton d'édition
        document.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function() {
                // Récupérer les données de l'administrateur
                const adminId = this.getAttribute('data-id');
                const nom = this.getAttribute('data-nom');
                const prenom = this.getAttribute('data-prenom');
                const username = this.getAttribute('data-username');
                const email = this.getAttribute('data-email');
                const role = this.getAttribute('data-role');
                
                // Trouver la ligne complète dans le tableau
                const row = this.closest('tr');
                const addBy = row.cells[7].textContent;
                const dateAdd = row.cells[6].textContent;
                const status = row.cells[8].textContent.trim();
                
                // Remplir le formulaire de modification
                document.getElementById('edit_admin_id').value = adminId;
                document.getElementById('edit_nom_admin').value = nom;
                document.getElementById('edit_prenom_admin').value = prenom;
                document.getElementById('edit_username_admin').value = username;
                document.getElementById('edit_email_admin').value = email;
                document.getElementById('edit_role_admin').value = role;
                document.getElementById('edit_add_by_admin').value = addBy;
                document.getElementById('edit_date_add_admin').value = dateAdd;
                document.getElementById('edit_status_admin').value = status;
                
                // Afficher le modal
                editAdminModal.style.display = 'block';
            });
        });
        
        // Gestion du bouton de suppression - MODIFIÉ POUR ÊTRE FONCTIONNEL
        document.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function() {
                if (confirm("Êtes-vous sûr de vouloir supprimer cet administrateur ?")) {
                    const adminId = this.getAttribute('data-id');
                    
                    // Créer un formulaire pour envoyer la requête de suppression
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const csrfToken = document.createElement('input');
                    csrfToken.type = 'hidden';
                    csrfToken.name = 'csrf_token';
                    csrfToken.value = '<?= $_SESSION['csrf_token'] ?>';
                    
                    const adminIdInput = document.createElement('input');
                    adminIdInput.type = 'hidden';
                    adminIdInput.name = 'admin_id';
                    adminIdInput.value = adminId;

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'delete_admin';
                    actionInput.value = '1';
                    
                    form.appendChild(csrfToken);
                    form.appendChild(adminIdInput);
                    form.appendChild(actionInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    </script>
</body>
</html>