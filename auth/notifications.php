<?php
// notifications.php
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user']['ref'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];

// Marquer toutes les notifications comme lues
try {
    $stmt = $conn->prepare("UPDATE notif SET seen = '1'");
    $stmt->execute();
} catch (PDOException $e) {
    $error = "Erreur mise à jour notifications: " . $e->getMessage();
}

// Récupérer les notifications
$notifications = [];
try {
    $stmt = $conn->prepare("
        SELECT n.*, e.nom, e.prenom, e.cin, e.fonction, e.assurance_end 
        FROM notif n
        JOIN entrepreneur e ON n.id_entrepreneur = e.id
        ORDER BY n.date_add DESC
    ");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur récupération notifications: " . $e->getMessage();
}

$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$base_path = ($base_path === '/' || $base_path === '\\') ? '' : $base_path;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Elhallaoui</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Styles existants de dashboard.php */
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

        /* Styles spécifiques aux notifications */
        .notification-container {
            padding: 30px;
            flex: 1;
        }

        .notification-header {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .notification-item {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .notification-title {
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--danger);
        }

        .notification-content {
            margin-bottom: 10px;
        }

        .notification-date {
            color: var(--text-secondary);
            font-size: 14px;
            margin-bottom: 10px;
        }

        .notification-actions {
            display: flex;
            justify-content: flex-end;
        }

        .btn-modify {
            background: var(--accent);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-modify:hover {
            background: #e09800;
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
            <div class="nav-item" data-href="<?= $base_path ?>/facture_list.php">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Factures</span>
            </div>
            <div class="nav-item active" data-href="<?= $base_path ?>/notifications.php">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
            </div>
        </div>
    </div>
    
    <!-- Contenu principal -->
    <div class="main-content">
        <div class="header">
            <div class="page-title">Notifications</div>
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
                            <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Avatar">
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
        
        <div class="notification-container">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= $error ?></span>
                </div>
            <?php endif; ?>
            
            <h1 class="notification-header">Toutes les notifications</h1>
            
            <?php if (count($notifications) > 0): ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item">
                        <div class="notification-title">
                            <?= htmlspecialchars($notification['prenom'] . ' ' . $notification['nom']) ?>
                        </div>
                        <div class="notification-content">
                            <p><strong>LA DATE D'ASSURANCE EST ÉPUISÉE.</strong></p>
                            <p>DATE DE FIN ASSURANCE: <?= htmlspecialchars($notification['assurance_end']) ?> | CIN : <?= htmlspecialchars($notification['cin']) ?> | FONCTION : <?= htmlspecialchars($notification['fonction']) ?></p>
                        </div>
                        <div class="notification-date">
                            ⏰ <?= htmlspecialchars($notification['date_add']) ?>
                        </div>
                        <div class="notification-actions">
                            <a href="<?= $base_path ?>/entrepreneur_edit.php?id=<?= $notification['id_entrepreneur'] ?>" class="btn-modify">Modifier</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Aucune notification.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Script pour le mode sombre
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

        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                if (this.hasAttribute('data-href')) {
                    window.location.href = this.getAttribute('data-href');
                }
            });
        });
    </script>
</body>
</html>