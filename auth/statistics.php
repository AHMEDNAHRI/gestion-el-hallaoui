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
$site_base = rtrim(dirname($base_path), '/'); // AJOUT IMPORTANT

// Récupérer les statistiques
$adminStats = ['actif' => 0, 'inactif' => 0];
$entrepreneurStats = ['actif' => 0, 'inactif' => 0];
$factureStats = ['virement' => 0, 'espece' => 0];

try {
    // Statistiques administrateurs
    $stmt = $conn->prepare("SELECT actif, COUNT(*) as count FROM users WHERE role IN (1, 2) GROUP BY actif");
    $stmt->execute();
    $adminResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($adminResults as $row) {
        if ($row['actif'] == 1) {
            $adminStats['actif'] = $row['count'];
        } else {
            $adminStats['inactif'] = $row['count'];
        }
    }
    
    // Statistiques entrepreneurs
    $stmt = $conn->prepare("SELECT statut, COUNT(*) as count FROM entrepreneur GROUP BY statut");
    $stmt->execute();
    $entrepreneurResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($entrepreneurResults as $row) {
        if ($row['statut'] == 1) {
            $entrepreneurStats['actif'] = $row['count'];
        } else {
            $entrepreneurStats['inactif'] = $row['count'];
        }
    }
    
    // Statistiques factures
    $stmt = $conn->prepare("SELECT mode_pai, COUNT(*) as count FROM facture GROUP BY mode_pai");
    $stmt->execute();
    $factureResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($factureResults as $row) {
        if (strtolower($row['mode_pai']) === 'virement') {
            $factureStats['virement'] = $row['count'];
        } else {
            $factureStats['espece'] = $row['count'];
        }
    }
    
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des statistiques: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - Elhallaoui</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Utilisez le même CSS que dans vos autres fichiers pour la cohérence */
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

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
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

        .stat-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary);
            text-align: center;
        }

        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
            margin-bottom: 20px;
        }

        .stat-details {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
        }

        .stat-detail {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 10px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
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
            <div class="nav-item active">
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
            <div class="page-title">Statistiques</div>
            <div class="header-actions">
                <!-- Bouton Dark Mode -->
                <button id="darkModeToggle" class="header-icon" title="Basculer mode sombre">
                    <i class="fas fa-moon"></i>
                </button>
                
                <!-- Bouton Déconnexion -->
                <a href="logout.php" class="header-icon logout" title="Déconnexion">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
                
                <!-- User Info -->
                <div class="user-info" id="userProfileBtn">
                    <div class="user-avatar">
                        <?php if (!empty($user['profile_picture'])): ?>
                            <!-- CORRECTION ICI : Ajout du chemin complet -->
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
            
            <div class="stats-container">
                <!-- Carte pour les administrateurs -->
                <div class="stat-card">
                    <h3 class="stat-title">Statut des Administrateurs</h3>
                    <div class="chart-container">
                        <canvas id="adminChart"></canvas>
                    </div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <span class="stat-value" style="color: var(--success);"><?= $adminStats['actif'] ?></span>
                            <span class="stat-label">Actifs</span>
                        </div>
                        <div class="stat-detail">
                            <span class="stat-value" style="color: var(--danger);"><?= $adminStats['inactif'] ?></span>
                            <span class="stat-label">Inactifs</span>
                        </div>
                    </div>
                </div>
                
                <!-- Carte pour les entrepreneurs -->
                <div class="stat-card">
                    <h3 class="stat-title">Statut des Entrepreneurs</h3>
                    <div class="chart-container">
                        <canvas id="entrepreneurChart"></canvas>
                    </div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <span class="stat-value" style="color: var(--success);"><?= $entrepreneurStats['actif'] ?></span>
                            <span class="stat-label">Actifs</span>
                        </div>
                        <div class="stat-detail">
                            <span class="stat-value" style="color: var(--danger);"><?= $entrepreneurStats['inactif'] ?></span>
                            <span class="stat-label">Inactifs</span>
                        </div>
                    </div>
                </div>
                
                <!-- Carte pour les factures -->
                <div class="stat-card">
                    <h3 class="stat-title">Mode de Paiement des Factures</h3>
                    <div class="chart-container">
                        <canvas id="factureChart"></canvas>
                    </div>
                    <div class="stat-details">
                        <div class="stat-detail">
                            <span class="stat-value" style="color: var(--success);"><?= $factureStats['virement'] ?></span>
                            <span class="stat-label">Virements</span>
                        </div>
                        <div class="stat-detail">
                            <span class="stat-value" style="color: var(--warning);"><?= $factureStats['espece'] ?></span>
                            <span class="stat-label">Espèces</span>
                        </div>
                    </div>
                </div>
            </div>
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
        
        // Initialisation des graphiques
        document.addEventListener('DOMContentLoaded', function() {
            // Graphique pour les administrateurs
            const adminCtx = document.getElementById('adminChart').getContext('2d');
            const adminChart = new Chart(adminCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Actifs', 'Inactifs'],
                    datasets: [{
                        data: [<?= $adminStats['actif'] ?>, <?= $adminStats['inactif'] ?>],
                        backgroundColor: [
                            'rgba(56, 161, 105, 0.8)',
                            'rgba(229, 62, 62, 0.8)'
                        ],
                        borderColor: [
                            'rgba(56, 161, 105, 1)',
                            'rgba(229, 62, 62, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: 'var(--text-color)'
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
            
            // Graphique pour les entrepreneurs
            const entrepreneurCtx = document.getElementById('entrepreneurChart').getContext('2d');
            const entrepreneurChart = new Chart(entrepreneurCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Actifs', 'Inactifs'],
                    datasets: [{
                        data: [<?= $entrepreneurStats['actif'] ?>, <?= $entrepreneurStats['inactif'] ?>],
                        backgroundColor: [
                            'rgba(56, 161, 105, 0.8)',
                            'rgba(229, 62, 62, 0.8)'
                        ],
                        borderColor: [
                            'rgba(56, 161, 105, 1)',
                            'rgba(229, 62, 62, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: 'var(--text-color)'
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
            
            // Graphique pour les factures
            const factureCtx = document.getElementById('factureChart').getContext('2d');
            const factureChart = new Chart(factureCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Virements', 'Espèces'],
                    datasets: [{
                        data: [<?= $factureStats['virement'] ?>, <?= $factureStats['espece'] ?>],
                        backgroundColor: [
                            'rgba(56, 161, 105, 0.8)',
                            'rgba(221, 107, 32, 0.8)'
                        ],
                        borderColor: [
                            'rgba(56, 161, 105, 1)',
                            'rgba(221, 107, 32, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: 'var(--text-color)'
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        });
    </script>
</body>
</html>