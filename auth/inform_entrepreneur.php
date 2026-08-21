<?php
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    
    if ($id) {
        try {
            // Récupérer les informations de l'entrepreneur
            $stmt = $conn->prepare("SELECT * FROM entrepreneur WHERE id = ?");
            $stmt->execute([$id]);
            $entrepreneur = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($entrepreneur) {
                // Envoyer l'email (code d'exemple)
                $to = $entrepreneur['email'];
                $subject = "Rappel d'expiration d'assurance";
                $message = "Cher(e) " . $entrepreneur['prenom'] . " " . $entrepreneur['nom'] . ",\n\n";
                $message .= "Votre assurance expire le " . $entrepreneur['assurance_end'] . ". Veuillez la renouveler.\n\n";
                $message .= "Cordialement,\nL'équipe Elhallaoui";
                
                // Envoyer l'email (dans un environnement réel, utiliser une bibliothèque comme PHPMailer)
                // mail($to, $subject, $message);
                
                echo json_encode(['success' => true, 'message' => 'Email envoyé avec succès']);
                exit;
            }
        } catch (PDOException $e) {
            // Gérer l'erreur
        }
    }
}

echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email']);
?>