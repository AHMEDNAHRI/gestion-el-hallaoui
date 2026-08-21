<?php
// print_facture.php
require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user']['ref'])) {
    header('Location: login.php');
    exit;
}

// Vérifier si l'ID de la facture est fourni
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID de facture manquant.");
}

$facture_id = intval($_GET['id']);

try {
    // Récupérer les détails de la facture et les informations de l'entrepreneur
    $stmt = $conn->prepare("
        SELECT f.*, e.prenom, e.nom, e.cin
        FROM facture f
        JOIN entrepreneur e ON f.id_entrepreneur = e.id
        WHERE f.id = ?
    ");
    $stmt->execute([$facture_id]);
    $facture = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$facture) {
        die("Facture non trouvée.");
    }
} catch (PDOException $e) {
    die("Erreur de base de données: " . $e->getMessage());
}

// Fonction pour convertir les nombres en mots (français)
function nombreEnLettres($nombre) {
    $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
    $dizaines = ['', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix'];
    $exceptions = [
        11 => 'onze', 12 => 'douze', 13 => 'treize', 14 => 'quatorze', 
        15 => 'quinze', 16 => 'seize', 17 => 'dix-sept', 18 => 'dix-huit', 19 => 'dix-neuf'
    ];

    if ($nombre == 0) return 'zéro';

    $texte = '';

    // Millions
    if ($nombre >= 1000000) {
        $millions = floor($nombre / 1000000);
        $texte .= nombreEnLettres($millions) . ' million' . ($millions > 1 ? 's ' : ' ');
        $nombre %= 1000000;
    }

    // Milliers
    if ($nombre >= 1000) {
        $milliers = floor($nombre / 1000);
        if ($milliers == 1) {
            $texte .= 'mille ';
        } else {
            $texte .= nombreEnLettres($milliers) . ' mille ';
        }
        $nombre %= 1000;
    }

    // Centaines
    if ($nombre >= 100) {
        $centaines = floor($nombre / 100);
        if ($centaines == 1) {
            $texte .= 'cent ';
        } else {
            $texte .= $unites[$centaines] . ' cent' . ($centaines > 1 && $nombre % 100 == 0 ? 's ' : ' ');
        }
        $nombre %= 100;
    }

    // Dizaines et unités
    if ($nombre > 0) {
        if ($texte != '') {
            $texte .= 'et ';
        }

        if ($nombre < 10) {
            $texte .= $unites[$nombre];
        } elseif ($nombre >= 11 && $nombre <= 19) {
            $texte .= $exceptions[$nombre];
        } else {
            $dizaine = floor($nombre / 10);
            $unite = $nombre % 10;
            
            if ($dizaine == 7 || $dizaine == 9) {
                $dizaine--;
                $unite += 10;
                $texte .= $dizaines[$dizaine];
                if ($unite > 0) {
                    $texte .= '-' . ($unite == 1 ? 'et ' : '') . $unites[$unite];
                }
            } else {
                $texte .= $dizaines[$dizaine];
                if ($unite > 0) {
                    if ($dizaine == 8 && $unite == 0) {
                        $texte .= 's';
                    } else {
                        $texte .= ($dizaine > 1 && $unite == 1 ? ' et ' : '-') . $unites[$unite];
                    }
                }
            }
        }
    }

    return trim($texte);
}

// Formater la date en français
function formatDateFr($dateStr) {
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $date = new DateTime($dateStr);
    return $date->format('d') . ' ' . $mois[intval($date->format('m'))] . ' ' . $date->format('Y');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?= $facture['num_fac'] ?> - Elhallaoui</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
            padding: 20px;
        }

        .print-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            position: relative;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            border-bottom: 2px solid #0d1b3e;
            padding-bottom: 20px;
        }

        .company-info {
            flex: 1;
        }

        .company-logo {
            font-size: 28px;
            color: #0d1b3e;
            margin-bottom: 10px;
        }

        .company-name {
            font-size: 22px;
            font-weight: bold;
            color: #0d1b3e;
            margin-bottom: 5px;
        }

        .invoice-title {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            color: #0d1b3e;
            margin-bottom: 30px;
            text-transform: uppercase;
        }

        .invoice-number {
            background: #0d1b3e;
            color: white;
            padding: 5px 15px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
            margin-top: 10px;
        }

        .details-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .client-info, .invoice-info {
            flex: 1;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #0d1b3e;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #0d1b3e;
        }

        .info-item {
            margin-bottom: 8px;
            display: flex;
        }

        .info-label {
            font-weight: bold;
            min-width: 120px;
        }

        .table-container {
            margin: 30px 0;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .invoice-table th {
            background-color: #0d1b3e;
            color: white;
            text-align: left;
            padding: 12px 15px;
            font-weight: bold;
        }

        .invoice-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #ddd;
        }

        .invoice-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .total-row {
            font-weight: bold;
            background-color: #f0f0f0;
        }

        .total-row td {
            border-top: 2px solid #0d1b3e;
            border-bottom: none;
        }

        .amount-in-words {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #eee;
            border-radius: 8px;
            font-style: italic;
        }

        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #0d1b3e;
            text-align: center;
            color: #666;
        }

        .signature {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            width: 45%;
            text-align: center;
            padding-top: 50px;
        }

        .signature-line {
            border-top: 1px solid #333;
            width: 80%;
            margin: 0 auto;
            padding-top: 10px;
        }

        .print-actions {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 30px;
        }

        .print-button {
            background: #0d1b3e;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .print-button:hover {
            background: #1a2a6c;
            transform: translateY(-2px);
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .print-container {
                box-shadow: none;
                padding: 15px;
            }
            
            .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="print-actions">
        <button class="print-button" onclick="window.print()">
            <i class="fas fa-print"></i> Imprimer la facture
        </button>
    </div>
    
    <div class="print-container">
        <div class="header">
            <div class="company-info">
                <div class="company-logo">
                    <i class="fas fa-building"></i> ELHALLAOUI
                </div>
                <div class="company-name">ELHALLAOUI Entreprise</div>
                <div>123 Avenue des Entrepreneurs</div>
                <div>Casablanca, Maroc</div>
                <div>Tél: +212 6 12 34 56 78</div>
                <div>Email: contact@elhallaoui.ma</div>
            </div>
            
            <div class="invoice-info">
                <div class="section-title">Facture</div>
                <div class="info-item">
                    <div class="info-label">N° Facture:</div>
                    <div><?= htmlspecialchars($facture['num_fac']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date:</div>
                    <div><?= formatDateFr($facture['date']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Mode de paiement:</div>
                    <div><?= htmlspecialchars($facture['mode_pai']) ?></div>
                </div>
            </div>
        </div>
        
        <div class="invoice-title">FACTURE</div>
        
        <div class="details-container">
            <div class="client-info">
                <div class="section-title">Client</div>
                <div class="info-item">
                    <div class="info-label">Nom:</div>
                    <div><?= htmlspecialchars($facture['prenom'] . ' ' . htmlspecialchars($facture['nom'])) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">CIN:</div>
                    <div><?= htmlspecialchars($facture['cin']) ?></div>
                </div>
                <?php if (!empty($facture['add_pear'])): ?>
                <div class="info-item">
                    <div class="info-label">Adresse:</div>
                    <div><?= htmlspecialchars($facture['add_pear']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="table-container">
            <table class="invoice-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantité</th>
                        <th>Prix unitaire</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($facture['designation2']) ?></td>
                        <td>1</td>
                        <td><?= number_format($facture['prix'], 2) ?> MAD</td>
                        <td><?= number_format($facture['prix'], 2) ?> MAD</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="3" style="text-align: right;">Total:</td>
                        <td><?= number_format($facture['prix'], 2) ?> MAD</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="amount-in-words">
            <strong>Montant en lettres:</strong> 
            <?= ucfirst(nombreEnLettres($facture['prix'])) ?> dirhams marocains
        </div>
        
        <div class="signature">
            <div class="signature-box">
                <div>Signature du client</div>
                <div class="signature-line"></div>
            </div>
            <div class="signature-box">
                <div>Signature et cachet</div>
                <div class="signature-line"></div>
            </div>
        </div>
        
        <div class="footer">
            <div>ELHALLAOUI Entreprise - SIRET: 123 456 789 00010 - N° TVA: MA 123456789</div>
            <div>Tél: +212 6 12 34 56 78 - Email: contact@elhallaoui.ma - Site: www.elhallaoui.ma</div>
            <div>Merci pour votre confiance!</div>
        </div>
    </div>
    
    <script>
        // Imprimer automatiquement si demandé
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autoPrint') === '1') {
            window.print();
        }
    </script>
</body>
</html>