<?php
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!empty($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (!empty($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_facture'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Token de sécurité invalide";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $id_entrepreneur = $_POST['id_entrepreneur'];
        $num_fac = $_POST['num_fac'];
        $mode_pai = $_POST['mode_pai'];
        $date = $_POST['date'];
        $designation2 = $_POST['designation2'];
        $prix = $_POST['prix'];
        $total_chiffre = $_POST['total_chiffre'];
        
        if (empty($id_entrepreneur) || empty($num_fac) || empty($mode_pai) || empty($date) || empty($prix) || empty($total_chiffre)) {
            $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO `facture` 
                    (id_entrepreneur, num_fac, mode_pai, date, designation2, prix, total_chiffre, date_add) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $stmt->execute([
                    $id_entrepreneur, 
                    $num_fac, 
                    $mode_pai, 
                    $date, 
                    $designation2, 
                    $prix,
                    $total_chiffre
                ]);
                
                $_SESSION['success'] = "Facture ajoutée avec succès!";
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                $_SESSION['error'] = "Erreur lors de l'ajout: " . $e->getMessage();
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
}

$entrepreneurs = [];
try {
    $stmt = $conn->prepare("SELECT id, CONCAT(prenom, ' ', nom) AS nom_complet, cin, prenom, nom 
                            FROM entrepreneur 
                            WHERE statut = 1");
    $stmt->execute();
    $entrepreneurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des entrepreneurs: " . $e->getMessage();
}

$factures = [];
try {
    $stmt = $conn->prepare("SELECT f.*, e.prenom, e.nom 
                            FROM facture f
                            JOIN entrepreneur e ON f.id_entrepreneur = e.id
                            ORDER BY f.date_add DESC");
    $stmt->execute();
    $factures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des factures: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $order = $_POST['order'];
    
    try {
        $conn->beginTransaction();
        
        foreach ($order as $position => $factureId) {
            $stmt = $conn->prepare("UPDATE facture SET order_position = ? WHERE id = ?");
            $stmt->execute([$position, $factureId]);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_facture'])) {
    $id = $_POST['id'];
    
    try {
        $stmt = $conn->prepare("DELETE FROM facture WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Facture supprimée avec succès!";
        } else {
            $_SESSION['error'] = "Aucune facture trouvée avec cet ID.";
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } catch (PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la suppression: " . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Factures - Elhallaoui</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <style>
        /* Styles CSS optimisés */
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
            --info: #3182ce;
            --card-bg: #ffffff;
            --sidebar-bg: #0d1b3e;
            --header-bg: #ffffff;
            --text-color: #333;
            --text-secondary: #666;
        }

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

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

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
        
        .btn-delete {
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            color: white;
            font-size: 12px;
            transition: all 0.3s;
            background-color: var(--danger);
        }

        .btn-print {
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            color: white;
            font-size: 12px;
            transition: all 0.3s;
            background-color: var(--info);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-delete:hover, .btn-print:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .status-paid {
            color: var(--success);
            font-weight: bold;
            background-color: rgba(56, 161, 105, 0.1);
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-block;
        }

        .status-pending {
            color: var(--warning);
            font-weight: bold;
            background-color: rgba(221, 107, 32, 0.1);
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-block;
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

        .add-facture-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .btn-add-facture {
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
        
        .btn-add-facture:hover {
            background-color: var(--secondary);
        }
        
        .btn-add-facture i {
            font-size: 16px;
        }

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
        
        .btn-add {
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

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
            color: var(--primary);
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

            .btn-close, .btn-add {
                width: 100%;
            }

            .content-table th,
            .content-table td {
                padding: 8px 10px;
                font-size: 12px;
            }

            .action-buttons a, .action-buttons button {
                width: 100%;
                text-align: center;
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
            <div class="nav-item" data-href="<?= $base_path ?>/admin_list.php">
                <i class="fas fa-user-shield"></i>
                <span>Administrateurs</span>
            </div>
            <div class="nav-item" data-href="<?= $base_path ?>/entrepreneur_list.php">
                <i class="fas fa-user-tie"></i>
                <span>Entrepreneurs</span>
            </div>
            <div class="nav-item active">
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
            <div class="page-title">Liste des Factures</div>
            <div class="header-actions">
                <!-- Bouton Dark Mode -->
                <button id="darkModeToggle" class="header-icon" title="Basculer mode sombre">
                    <i class="fas fa-moon"></i>
                </button>
                
                <!-- Bouton Déconnexion -->
                <a href="logout.php" class="header-icon logout" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
                
                <!-- User Info avec photo de profil réelle -->
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
            <div class="add-facture-header">
                <h1>Liste des factures</h1>
                <button id="addFactureBtn" class="btn-add-facture">
                    <i class="fas fa-plus"></i> NOUVELLE FACTURE
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
                <span>Ordre des factures mis à jour avec succès!</span>
            </div>
            
            <table class="content-table">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>ID</th>
                        <th>Entrepreneur</th>
                        <th>Numéro Facture</th>
                        <th>Date</th>
                        <th>Mode Paiement</th>
                        <th>Prix</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="sortableFactures">
                    <?php if (count($factures) > 0): ?>
                        <?php foreach ($factures as $facture): ?>
                            <tr data-id="<?= $facture['id'] ?>">
                                <td class="drag-handle"><i class="fas fa-grip-lines"></i></td>
                                <td><?= $facture['id'] ?></td>
                                <td><?= htmlspecialchars($facture['prenom'] . ' ' . htmlspecialchars($facture['nom'])) ?></td>
                                <td><?= htmlspecialchars($facture['num_fac']) ?></td>
                                <td><?= date('d/m/Y', strtotime($facture['date'])) ?></td>
                                <td>
                                    <?php if ($facture['mode_pai'] == 'Virement'): ?>
                                        <span class="status-paid">VIREMENT</span>
                                    <?php else: ?>
                                        <span class="status-pending">ESPÈCES</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($facture['prix'], 2) ?> MAD</td>
                                <td><?= htmlspecialchars($facture['total_chiffre']) ?></td>
                                <td class="action-buttons">
                                    <a href="print_facture.php?id=<?= $facture['id'] ?>" class="btn-print" target="_blank">
                                        <i class="fas fa-print"></i> Imprimer
                                    </a>
                                    <button class="btn-delete" data-id="<?= $facture['id'] ?>">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">Aucune facture trouvée.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="instructions" style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 10px;"></i>
                <span>Glissez-déposez les lignes pour réorganiser la liste des factures.</span>
            </div>
        </div>
    </div>
    
    <!-- Modal pour ajouter une facture -->
    <div id="addFactureModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Ajouter Nouvelle Facture</h2>
                <span class="close-modal">&times;</span>
            </div>
            
            <?php if (!empty($error) && isset($_POST['add_facture'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success) && isset($_POST['add_facture'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $success ?></span>
                </div>
            <?php endif; ?>
            
            <form id="factureForm" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="section-title">Mode de paiement</div>
                <div class="form-group">
                    <div style="display: flex; gap: 20px;">
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <input type="radio" name="mode_pai" value="Virement" checked> Virement
                        </label>
                        <label style="display: flex; align-items: center; gap: 5px;">
                            <input type="radio" name="mode_pai" value="Espace"> Espèce
                        </label>
                    </div>
                </div>
                
                <div class="form-grid">
                    <!-- Colonne gauche -->
                    <div>
                        <div class="section-title">Entrepreneur</div>
                        <div class="form-group">
                            <select id="id_entrepreneur" name="id_entrepreneur" class="form-control" required>
                                <option value="">Sélectionner un entrepreneur</option>
                                <?php foreach ($entrepreneurs as $entrepreneur): ?>
                                    <option value="<?= $entrepreneur['id'] ?>" 
                                        data-cin="<?= htmlspecialchars($entrepreneur['cin']) ?>"
                                        data-prenom="<?= htmlspecialchars($entrepreneur['prenom']) ?>"
                                        data-nom="<?= htmlspecialchars($entrepreneur['nom']) ?>">
                                        <?= htmlspecialchars($entrepreneur['nom_complet']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Colonne droite -->
                    <div>
                        <div class="section-title">Date</div>
                        <div class="form-group">
                            <input type="date" id="date" name="date" class="form-control" required>
                        </div>
                        
                        <div class="section-title">Num Facture</div>
                        <div class="form-group">
                            <input type="text" id="num_fac" name="num_fac" class="form-control" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="section-title">CIN</div>
                    <input type="text" id="ctrl" class="form-control" readonly>
                </div>
                
                <div class="form-grid">
                    <!-- Colonne gauche -->
                    <div>
                        <div class="section-title">Nom</div>
                        <div class="form-group">
                            <input type="text" id="nom" class="form-control" readonly>
                        </div>
                    </div>
                    
                    <!-- Colonne droite -->
                    <div>
                        <div class="section-title">Prénom</div>
                        <div class="form-group">
                            <input type="text" id="prenom" class="form-control" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="section-title">Désignation</div>
                <div class="form-group">
                    <textarea id="designation2" name="designation2" class="form-control" rows="3" required>PRESTATION DU MOIS</textarea>
                </div>
                
                <div class="form-grid">
                    <!-- Colonne gauche -->
                    <div>
                        <div class="section-title">Prix</div>
                        <div class="form-group">
                            <input type="number" id="prix" name="prix" class="form-control" step="0.01" required>
                        </div>
                    </div>
                    
                    <!-- Colonne droite -->
                    <div>
                        <div class="section-title">Total chiffre</div>
                        <div class="form-group">
                            <input type="text" id="total_chiffre" name="total_chiffre" class="form-control" readonly required>
                        </div>
                    </div>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-close">FERMER</button>
                    <button type="submit" class="btn-add" name="add_facture">AJOUTER</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Fonction de conversion nombre -> texte (version améliorée)
        function convertNumberToWords(number) {
            // Gestion des nombres négatifs
            if (number < 0) {
                return 'moins ' + convertNumberToWords(Math.abs(number));
            }
            
            // Gestion des nombres à virgule
            const integerPart = Math.floor(number);
            const decimalPart = Math.round((number - integerPart) * 100);
            
            // Conversion de la partie entière
            let words = convertIntegerToWords(integerPart);
            
            // Ajout des décimales si nécessaire
            if (decimalPart > 0) {
                words += ' virgule ' + convertIntegerToWords(decimalPart);
            }
            
            return words;
        }
        
        // Conversion de la partie entière
        function convertIntegerToWords(n) {
            if (n === 0) return 'zéro';
            
            const units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
            const teens = ['dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
            const tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];
            const bigNumbers = [
                { value: 1000000000, name: 'milliard', plural: 'milliards' },
                { value: 1000000, name: 'million', plural: 'millions' },
                { value: 1000, name: 'mille', plural: 'mille' }
            ];
            
            let words = '';
            
            // Traitement des grands nombres (milliards, millions, milliers)
            for (const bigNum of bigNumbers) {
                if (n >= bigNum.value) {
                    const quotient = Math.floor(n / bigNum.value);
                    const remainder = n % bigNum.value;
                    
                    if (quotient > 1) {
                        words += convertIntegerToWords(quotient) + ' ';
                        words += bigNum.plural;
                    } else {
                        words += bigNum.name;
                    }
                    
                    if (remainder > 0) {
                        words += ' ';
                        words += convertIntegerToWords(remainder);
                    }
                    return words;
                }
            }
            
            // Traitement des centaines
            if (n >= 100) {
                const hundreds = Math.floor(n / 100);
                const remainder = n % 100;
                
                if (hundreds === 1) {
                    words += 'cent';
                } else {
                    words += units[hundreds] + ' cent';
                }
                
                if (remainder > 0) {
                    words += ' ' + convertIntegerToWords(remainder);
                }
                return words;
            }
            
            // Traitement des dizaines et unités
            if (n < 10) {
                return units[n];
            } else if (n < 20) {
                return teens[n - 10];
            } else {
                const ten = Math.floor(n / 10);
                const unit = n % 10;
                
                // Cas particuliers pour 70, 80, 90
                if (ten === 7 || ten === 9) {
                    words = tens[ten];
                    if (ten === 7) {
                        words += '-' + teens[unit];
                    } else {
                        words += '-' + (unit === 0 ? 'dix' : teens[unit]);
                    }
                } else {
                    words = tens[ten];
                    if (unit === 1 && ten !== 8) {
                        words += ' et un';
                    } else if (unit > 0) {
                        if (ten === 8) {
                            words += unit === 1 ? '-un' : '-' + units[unit];
                        } else {
                            words += '-' + units[unit];
                        }
                    } else if (ten === 8) {
                        words += 's';
                    }
                }
                return words;
            }
        }
        
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
        
        // Initialisation du drag and drop avec SortableJS
        document.addEventListener('DOMContentLoaded', function() {
            const sortableFactures = document.getElementById('sortableFactures');
            if (sortableFactures) {
                new Sortable(sortableFactures, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: function(evt) {
                        const rows = Array.from(sortableFactures.querySelectorAll('tr'));
                        const newOrder = rows.map(row => row.getAttribute('data-id'));
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
                                const successMessage = document.getElementById('orderSuccess');
                                successMessage.style.display = 'flex';
                                setTimeout(() => {
                                    successMessage.style.display = 'none';
                                }, 3000);
                            }
                        });
                    }
                });
            }
        });

        // Modal pour ajouter une facture
        const addFactureBtn = document.getElementById('addFactureBtn');
        const factureModal = document.getElementById('addFactureModal');
        const closeFactureModal = factureModal.querySelector('.close-modal');
        const closeFactureBtn = factureModal.querySelector('.btn-close');
        
        addFactureBtn.addEventListener('click', function() {
            factureModal.style.display = 'block';
        });
        
        closeFactureModal.addEventListener('click', function() {
            factureModal.style.display = 'none';
        });
        
        closeFactureBtn.addEventListener('click', function() {
            factureModal.style.display = 'none';
        });
        
        window.addEventListener('click', function(event) {
            if (event.target === factureModal) {
                factureModal.style.display = 'none';
            }
        });
        
        // Remplissage automatique des champs
        const entrepreneurSelect = document.getElementById('id_entrepreneur');
        const ctrlInput = document.getElementById('ctrl');
        const nomInput = document.getElementById('nom');
        const prenomInput = document.getElementById('prenom');
        
        entrepreneurSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                ctrlInput.value = selectedOption.getAttribute('data-cin') || '';
                nomInput.value = selectedOption.getAttribute('data-nom') || '';
                prenomInput.value = selectedOption.getAttribute('data-prenom') || '';
            } else {
                ctrlInput.value = '';
                nomInput.value = '';
                prenomInput.value = '';
            }
        });
        
        // Initialiser la date avec la date d'aujourd'hui
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('date').value = today;
        
        // Conversion prix -> texte
        document.getElementById('prix').addEventListener('input', function() {
            const prix = parseFloat(this.value);
            const totalChiffreField = document.getElementById('total_chiffre');
            
            if (isNaN(prix)) {
                totalChiffreField.value = '';
                return;
            }
            
            totalChiffreField.value = convertNumberToWords(prix);
        });
        
        // Gestion de la suppression de facture
        document.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function() {
                if (confirm("Êtes-vous sûr de vouloir supprimer cette facture ?")) {
                    const id = this.getAttribute('data-id');
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const csrfToken = document.createElement('input');
                    csrfToken.type = 'hidden';
                    csrfToken.name = 'csrf_token';
                    csrfToken.value = '<?= $_SESSION['csrf_token'] ?>';
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'id';
                    idInput.value = id;
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'delete_facture';
                    actionInput.value = '1';
                    
                    form.appendChild(csrfToken);
                    form.appendChild(idInput);
                    form.appendChild(actionInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    </script>
</body>
</html>