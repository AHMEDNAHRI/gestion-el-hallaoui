<?php
require_once __DIR__ . '/../db.php';
session_start();

if (empty($_SESSION['user']['ref'])) {
    header('Location: login.php');
    exit;
}

try {
    $stmt = $conn->prepare("SELECT n.*, e.raison_sociale 
                           FROM stage1_notif n
                           JOIN stage1_entrepreneur e ON n.id_entrepreneur = e.id
                           ORDER BY n.date_add DESC");
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($notifications) > 0) {
        foreach ($notifications as $notification) {
            echo '<div class="notification-card">';
            echo '<div class="notification-header">';
            echo '<h3 class="notification-title">Notification d\'assurance</h3>';
            echo '<div class="notification-date">' . date('d/m/Y H:i', strtotime($notification['date_add'])) . '</div>';
            echo '</div>';
            echo '<div class="notification-content">';
            echo '<p class="notification-message">';
            echo 'L\'assurance de <strong>' . htmlspecialchars($notification['raison_sociale']) . '</strong> ';
            echo 'expire le <strong>' . date('d/m/Y', strtotime($notification['old_date'])) . '</strong>';
            echo '</p>';
            echo '<div class="notification-actions">';
            
            if ($notification['seen'] === '0') {
                echo '<button class="notification-btn mark-read" data-id="' . $notification['id'] . '">Marquer comme lu</button>';
            }
            
            echo '<button class="notification-btn inform" data-id="' . $notification['id'] . '" data-entrepreneur="' . $notification['id_entrepreneur'] . '">Informer l\'entrepreneur</button>';
            echo '<button class="notification-btn delete" data-id="' . $notification['id'] . '">Supprimer</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    } else {
        echo '<div class="notification-card">';
        echo '<div class="notification-content">';
        echo '<p class="notification-message">Aucune notification</p>';
        echo '</div>';
        echo '</div>';
    }
} catch (PDOException $e) {
    echo '<div class="notification-card">';
    echo '<div class="notification-content">';
    echo '<p class="notification-message">Erreur lors du chargement des notifications</p>';
    echo '</div>';
    echo '</div>';
}
?>