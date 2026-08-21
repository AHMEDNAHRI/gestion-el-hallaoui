<?php
require_once __DIR__ . '/../db.php';

// Démarrer la session
// Après session_start() et avant tout contenu HTML
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if (empty($_SESSION['user']['ref'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// Calcul du chemin de base pour les redirections
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base_path = ($base_path === '/' || $base_path === '\\') ? '' : $base_path;

// Vérifier si l'utilisateur est un comptable (role = 3)
$userRole = isset($user['role']) ? $user['role'] : (isset($user['doca']) ? $user['doca'] : 3);
if ($userRole == 3) {
    // Utiliser un chemin relatif au lieu de $base_path
    header('Location: dashboard.php');
    exit;
}

// Racine du site (ex: /stage)
$site_base = rtrim(dirname($base_path), '/');

// Récupération des messages de session
if (!empty($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (!empty($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Génération du token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Traitement de l'ajout d'un entrepreneur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entrepreneur'])) {
    // Validation CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Token de sécurité invalide";
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        // Récupération des données
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $cin = $_POST['cin'];
        $tele = $_POST['tele'];
        $ice = $_POST['ice'];
        $if = $_POST['if'];
        $assurance_start = $_POST['assurance_start'];
        $assurance_end = $_POST['assurance_end'];
        $email = $_POST['email'];
        $id_fonction = $_POST['id_fonction'];
        $rib = $_POST['rib'];
        $id_societe = $_POST['id_societe'];
        $adresse = $_POST['adresse'];
        
        // Validation basique
        if (empty($nom) || empty($prenom) || empty($cin) || empty($id_fonction) || empty($id_societe)) {
            $_SESSION['error'] = "Tous les champs obligatoires doivent être remplis.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            try {
                // Vérifier si la fonction existe
                $stmt = $conn->prepare("SELECT COUNT(*) FROM function WHERE id = ?");
                $stmt->execute([$id_fonction]);
                $functionExists = $stmt->fetchColumn();
                
                // Vérifier si la société existe
                $stmt = $conn->prepare("SELECT COUNT(*) FROM ref_societe WHERE id = ?");
                $stmt->execute([$id_societe]);
                $societeExists = $stmt->fetchColumn();
                
                if (!$functionExists) {
                    $_SESSION['error'] = "La fonction sélectionnée n'existe pas dans la base de données.";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } elseif (!$societeExists) {
                    $_SESSION['error'] = "La société sélectionnée n'existe pas dans la base de données.";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } else {
                    // Insertion dans la table entrepreneur
                    $stmt = $conn->prepare("INSERT INTO `entrepreneur` 
                        (nom, prenom, cin, tele, ice, identification_fiscale, rib, email, assurance_start, assurance_end, adresse, id_fonction, id_ref, date_add, statut) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1)");
                    
                    // Exécution de la requête avec les valeurs dans le bon ordre
                    $stmt->execute([
                        $nom, 
                        $prenom, 
                        $cin,       // CIN
                        $tele,      // téléphone
                        $ice,       // ICE
                        $if,        // identifiant fiscal
                        $rib,       // RIB
                        $email,     // email
                        $assurance_start, 
                        $assurance_end, 
                        $adresse,   // adresse
                        $id_fonction, // fonction
                        $id_societe  // société
                    ]);
                    
                    $_SESSION['success'] = "Entrepreneur ajouté avec succès!";
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Erreur lors de l'ajout: " . $e->getMessage();
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
}

// Récupération des fonctions et sociétés pour les menus déroulants
$fonctions = [];
$societes = [];

try {
    $stmt = $conn->prepare("SELECT id, nom FROM function WHERE statut = '1'");
    $stmt->execute();
    $fonctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Vérification si aucune fonction n'existe
    if (empty($fonctions)) {
        // Insertion de fonctions par défaut
        $defaultFunctions = ['Directeur', 'Gérant', 'Responsable RH'];
        foreach ($defaultFunctions as $functionName) {
            $stmt = $conn->prepare("INSERT INTO function (nom, statut) VALUES (?, '1')");
            $stmt->execute([$functionName]);
        }
        
        // Recharger les fonctions
        $stmt = $conn->prepare("SELECT id, nom FROM function WHERE statut = '1'");
        $stmt->execute();
        $fonctions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Récupération des sociétés
    $stmt = $conn->prepare("SELECT id, raison_sociale FROM ref_societe");
    $stmt->execute();
    $societes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Vérification si aucune société n'existe
    if (empty($societes)) {
        // Insertion de sociétés par défaut
        $defaultSocietes = ['Société A', 'Société B', 'Société C'];
        foreach ($defaultSocietes as $societeName) {
            $stmt = $conn->prepare("INSERT INTO ref_societe (raison_sociale) VALUES (?)");
            $stmt->execute([$societeName]);
        }
        
        // Recharger les sociétés
        $stmt = $conn->prepare("SELECT id, raison_sociale FROM ref_societe");
        $stmt->execute();
        $societes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // En cas d'erreur, utiliser des données de démo
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

// Récupérer la liste des entrepreneurs
$entrepreneurs = [];
try {
    $stmt = $conn->prepare("SELECT * FROM entrepreneur ORDER BY date_add DESC");
    $stmt->execute();
    $entrepreneurs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des entrepreneurs: " . $e->getMessage();
}

// Traitement de la mise à jour de l'ordre via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $order = $_POST['order'];
    
    try {
        $conn->beginTransaction();
        
        foreach ($order as $position => $entrepreneurId) {
            $stmt = $conn->prepare("UPDATE entrepreneur SET order_position = ? WHERE id = ?");
            $stmt->execute([$position, $entrepreneurId]);
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

// Traitement du changement de statut
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $id = $_POST['id'];
    $newStatus = $_POST['new_status'];
    
    try {
        $stmt = $conn->prepare("UPDATE entrepreneur SET statut = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du statut: ' . $e->getMessage()]);
        exit;
    }
}

// Récupérer le nombre de notifications non lues (table notif)
$unreadNotificationsCount = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notif WHERE is_read = 0");
    $stmt->execute();
    $unreadNotificationsCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    // En cas d'erreur, on continue avec 0
    error_log("Erreur de récupération des notifications: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Entrepreneurs - Elhallaoui</title>
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
        
        .btn-activate, .btn-deactivate {
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            color: white;
            font-size: 12px;
            transition: all 0.3s;
        }
        
        .btn-activate {
            background-color: var(--success);
        }
        
        .btn-deactivate {
            background-color: var(--danger);
        }

        .btn-activate:hover, .btn-deactivate:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }

        .status-active {
            color: var(--success);
            font-weight: bold;
            background-color: rgba(56, 161, 105, 0.1);
            padding: 3px 8px;
            border-radius: 12px;
            display: inline-block;
        }

        .status-inactive {
            color: var(--danger);
            font-weight: bold;
            background-color: rgba(229, 62, 62, 0.1);
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

        .add-entrepreneur-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .btn-add-entrepreneur {
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
        
        .btn-add-entrepreneur:hover {
            background-color: var(--secondary);
        }
        
        .btn-add-entrepreneur i {
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
        
        /* Styles pour l'icône de notification */
        .notification-icon {
            position: relative;
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
            text-decoration: none;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .notification-dropdown {
            position: absolute;
            top: 65px;
            right: 30px;
            width: 350px;
            background: var(--card-bg);
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--dark);
        }
        
        .mark-all-read {
            color: var(--primary);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .notification-list {
            padding: 0;
            margin: 0;
            list-style: none;
        }
        
        .notification-item {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .notification-item:hover {
            background: rgba(13, 27, 62, 0.05);
        }
        
        .notification-item.unread {
            background: rgba(58, 134, 255, 0.05);
        }
        
        .notification-item-title {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .notification-item-title i {
            color: var(--accent);
        }
        
        .notification-item-content {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }
        
        .notification-item-time {
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            justify-content: space-between;
        }
        
        .notification-footer {
            padding: 15px 20px;
            text-align: center;
        }
        
        .view-all-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .notification-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            margin-right: 8px;
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
            
            .notification-dropdown {
                right: 10px;
                width: calc(100% - 20px);
                max-width: 350px;
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
            <div class="nav-item" data-href="dashboard.php">
                <i class="fas fa-home"></i>
                <span>Tableau de bord</span>
            </div>
            <div class="nav-item" data-href="listadmin.php">
                <i class="fas fa-user-shield"></i>
                <span>Administrateurs</span>
            </div>
            <div class="nav-item active">
                <i class="fas fa-user-tie"></i>
                <span>Entrepreneurs</span>
            </div>
            <div class="nav-item" data-href="facture_list.php">
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
            <div class="page-title">Liste des Entrepreneurs</div>
            <div class="header-actions">
                <!-- Bouton Dark Mode -->
                <button id="darkModeToggle" class="header-icon" title="Basculer mode sombre">
                    <i class="fas fa-moon"></i>
                </button>
                
                <!-- Bouton Notification -->
                <div class="notification-icon" id="notificationBtn" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadNotificationsCount > 0): ?>
                        <span class="notification-badge"><?= $unreadNotificationsCount ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Menu déroulant des notifications -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <div class="notification-title">Notifications</div>
                        <button class="mark-all-read" id="markAllReadBtn">Tout marquer comme lu</button>
                    </div>
                    <ul class="notification-list">
                        <!-- Les notifications seront chargées dynamiquement ici -->
                    </ul>
                    <div class="notification-footer">
                        <a href="notification_list.php" class="view-all-link">Voir toutes les notifications</a>
                    </div>
                </div>
                
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
            <div class="add-entrepreneur-header">
                <h1>Liste des entrepreneurs</h1>
                <button id="addEntrepreneurBtn" class="btn-add-entrepreneur">
                    <i class="fas fa-plus"></i> NOUVEAU ENTREPRENEUR
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
                <span>Ordre des entrepreneurs mis à jour avec succès!</span>
            </div>
            
            <div id="statusSuccess" class="success-message" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <span>Statut mis à jour avec succès!</span>
            </div>
            
            <table class="content-table">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>CIN</th>
                        <th>Téléphone</th>
                        <th>Date d'ajout</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="sortableEntrepreneurs">
                    <?php if (count($entrepreneurs) > 0): ?>
                        <?php foreach ($entrepreneurs as $entrepreneur): ?>
                            <tr data-id="<?= $entrepreneur['id'] ?>">
                                <td class="drag-handle"><i class="fas fa-grip-lines"></i></td>
                                <td><?= $entrepreneur['id'] ?></td>
                                <td><?= htmlspecialchars($entrepreneur['prenom'] . ' ' . htmlspecialchars($entrepreneur['nom'])) ?></td>
                                <td><?= htmlspecialchars($entrepreneur['email']) ?></td>
                                <td><?= htmlspecialchars($entrepreneur['cin']) ?></td>
                                <td><?= htmlspecialchars($entrepreneur['tele']) ?></td>
                                <td><?= $entrepreneur['date_add'] ?></td>
                                <td>
                                    <?php if ($entrepreneur['statut'] == 1): ?>
                                        <span class="status-active">ACTIF</span>
                                    <?php else: ?>
                                        <span class="status-inactive">INACTIF</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($entrepreneur['statut'] == 1): ?>
                                        <button class="btn-deactivate" data-id="<?= $entrepreneur['id'] ?>">Désactiver</button>
                                    <?php else: ?>
                                        <button class="btn-activate" data-id="<?= $entrepreneur['id'] ?>">Activer</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">Aucun entrepreneur trouvé.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="instructions" style="margin-top: 20px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                <i class="fas fa-info-circle" style="color: var(--primary); margin-right: 10px;"></i>
                <span>Glissez-déposez les lignes pour réorganiser la liste des entrepreneurs.</span>
            </div>
        </div>
    </div>
    
    <!-- Modal pour ajouter un entrepreneur -->
    <div id="addEntrepreneurModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Ajouter Nouveau Entrepreneur</h2>
                <span class="close-modal">&times;</span>
            </div>
            
            <?php if (!empty($error) && isset($_POST['add_entrepreneur'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success) && isset($_POST['add_entrepreneur'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $success ?></span>
                </div>
            <?php endif; ?>
            
            <form id="entrepreneurForm" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-grid">
                    <!-- Colonne gauche -->
                    <div>
                        <div class="form-group">
                            <label for="nom">Nom</label>
                            <input type="text" id="nom" name="nom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom</label>
                            <input type="text" id="prenom" name="prenom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="cin">CIN</label>
                            <input type="text" id="cin" name="cin" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="tele">Téléphone</label>
                            <input type="tel" id="tele" name="tele" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="ice">ICE</label>
                            <input type="text" id="ice" name="ice" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="if">Identifiant fiscal</label>
                            <input type="text" id="if" name="if" class="form-control" required>
                        </div>
                    </div>
                    
                    <!-- Colonne droite -->
                    <div>
                        <div class="form-group">
                            <label for="assurance_start">Date de début assurance</label>
                            <input type="date" id="assurance_start" name="assurance_start" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="assurance_end">Date de fin assurance</label>
                            <input type="date" id="assurance_end" name="assurance_end" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email">E-mail</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="id_fonction">Fonction</label>
                            <select id="id_fonction" name="id_fonction" class="form-control" required>
                                <option value="">Sélectionner une fonction</option>
                                <?php foreach ($fonctions as $fonction): ?>
                                    <option value="<?= $fonction['id'] ?>"><?= htmlspecialchars($fonction['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="rib">RIB</label>
                            <input type="text" id="rib" name="rib" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="id_societe">Société</label>
                            <select id="id_societe" name="id_societe" class="form-control" required>
                                <option value="">Sélectionner une société</option>
                                <?php foreach ($societes as $societe): ?>
                                    <option value="<?= $societe['id'] ?>"><?= htmlspecialchars($societe['raison_sociale']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="adresse">Adresse</label>
                    <textarea id="adresse" name="adresse" class="form-control" rows="3" required>Tanger</textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-close">FERMER</button>
                    <button type="submit" class="btn-add" name="add_entrepreneur">AJOUTER</button>
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
        
        // Initialisation du drag and drop avec SortableJS pour les entrepreneurs
        document.addEventListener('DOMContentLoaded', function() {
            const sortableEntrepreneurs = document.getElementById('sortableEntrepreneurs');
            
            if (sortableEntrepreneurs) {
                new Sortable(sortableEntrepreneurs, {
                    animation: 150,
                    handle: '.drag-handle',
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: function(evt) {
                        // Récupérer les IDs dans le nouvel ordre
                        const rows = Array.from(sortableEntrepreneurs.querySelectorAll('tr'));
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

        // Modal pour ajouter un entrepreneur
        const addEntrepreneurBtn = document.getElementById('addEntrepreneurBtn');
        const entrepModal = document.getElementById('addEntrepreneurModal');
        const closeEntrepModal = entrepModal.querySelector('.close-modal');
        const closeEntrepBtn = entrepModal.querySelector('.btn-close');
        
        addEntrepreneurBtn.addEventListener('click', function() {
            entrepModal.style.display = 'block';
        });
        
        closeEntrepModal.addEventListener('click', function() {
            entrepModal.style.display = 'none';
        });
        
        closeEntrepBtn.addEventListener('click', function() {
            entrepModal.style.display = 'none';
        });
        
        // Fermer les modals en cliquant à l'extérieur
        window.addEventListener('click', function(event) {
            if (event.target === entrepModal) {
                entrepModal.style.display = 'none';
            }
        });
        
        // Gestion du changement de statut
        document.querySelectorAll('.btn-activate, .btn-deactivate').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const newStatus = this.classList.contains('btn-activate') ? 1 : 0;
                
                // Envoyer la requête au serveur
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `toggle_status=true&id=${id}&new_status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Afficher un message de succès
                        const successMessage = document.getElementById('statusSuccess');
                        successMessage.style.display = 'flex';
                        
                        // Recharger la page après 1 seconde
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        console.error('Erreur lors de la mise à jour du statut:', data.message);
                        alert('Erreur lors de la mise à jour du statut: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue: ' + error.message);
                });
            });
        });
        
        // Gestion des notifications
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const markAllReadBtn = document.getElementById('markAllReadBtn');
        
        // Charger les notifications
        function loadNotifications() {
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    const notificationList = document.querySelector('.notification-list');
                    notificationList.innerHTML = '';
                    
                    if (data.length === 0) {
                        notificationList.innerHTML = '<li class="notification-item"><div class="notification-item-content">Aucune notification</div></li>';
                        return;
                    }
                    
                    data.forEach(notif => {
                        const li = document.createElement('li');
                        li.className = `notification-item ${notif.is_read ? '' : 'unread'}`;
                        
                        li.innerHTML = `
                            <div class="notification-item-title">
                                ${notif.is_read ? '' : '<span class="notification-dot"></span>'}
                                <i class="fas fa-exclamation-circle"></i>
                                ${notif.title}
                            </div>
                            <div class="notification-item-content">${notif.message}</div>
                            <div class="notification-item-time">
                                <span>${notif.time_ago}</span>
                                <button class="mark-read" data-id="${notif.id}">Marquer comme lu</button>
                            </div>
                        `;
                        
                        notificationList.appendChild(li);
                    });
                    
                    // Ajouter les gestionnaires d'événements pour marquer comme lu
                    document.querySelectorAll('.mark-read').forEach(btn => {
                        btn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            const notificationId = this.getAttribute('data-id');
                            markNotificationAsRead(notificationId);
                        });
                    });
                    
                    // Mettre à jour le badge de notification
                    const unreadCount = data.filter(n => !n.is_read).length;
                    const notificationBadge = document.querySelector('.notification-badge');
                    
                    if (unreadCount > 0) {
                        if (!notificationBadge) {
                            const badge = document.createElement('span');
                            badge.className = 'notification-badge';
                            badge.textContent = unreadCount;
                            notificationBtn.appendChild(badge);
                        } else {
                            notificationBadge.textContent = unreadCount;
                        }
                    } else if (notificationBadge) {
                        notificationBadge.remove();
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des notifications:', error);
                });
        }
        
        // Marquer une notification comme lue
        function markNotificationAsRead(notificationId) {
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications(); // Recharger les notifications
                }
            })
            .catch(error => {
                console.error('Erreur lors du marquage comme lu:', error);
            });
        }
        
        // Marquer toutes les notifications comme lues
        function markAllNotificationsAsRead() {
            fetch('mark_all_notifications_read.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadNotifications(); // Recharger les notifications
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du marquage comme lu:', error);
                });
        }
        
        // Toggle le menu déroulant des notifications
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.style.display = notificationDropdown.style.display === 'block' ? 'none' : 'block';
            loadNotifications(); // Charger les notifications à chaque ouverture
        });
        
        // Fermer le menu déroulant en cliquant ailleurs
        document.addEventListener('click', function(e) {
            if (!notificationDropdown.contains(e.target) && e.target !== notificationBtn) {
                notificationDropdown.style.display = 'none';
            }
        });
        
        // Gestion du bouton "Tout marquer comme lu"
        markAllReadBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            markAllNotificationsAsRead();
        });
        
        // Charger les notifications au démarrage
        loadNotifications();
    </script>
</body>
</html>