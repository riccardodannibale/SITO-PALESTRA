<?php
session_start();
include 'db.php';

// Recupera l'ID utente dalla sessione (se presente)
$id_user = $_SESSION['id_user'] ?? null;
$has_active_abbonamento = false; // Stato abbonamento attivo
$prenotazioni = []; // Prenotazioni dell'utente

if ($id_user) {
    // Verifica se l'utente ha un abbonamento attivo
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM user_abbonamenti 
        WHERE id_user = ? AND stato = 'attivo' AND data_scadenza >= CURDATE()
    ");
    if ($stmt) {
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        $has_active_abbonamento = ($count > 0);
    }

    // Recupera le prenotazioni dell'utente
    $stmt = $conn->prepare("
        SELECT c.nome_corso, c.descrizione, p.data_prenotazione
        FROM prenotazione p
        JOIN corsi c ON p.id_corso = c.id_corso
        WHERE p.id_user = ?
        ORDER BY p.data_prenotazione DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $id_user);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $prenotazioni[] = $row;
        }
        $stmt->close();
    }
}

// Recupera tutti i corsi disponibili
$corsi = [];
$result = $conn->query("SELECT id_corso, nome_corso, descrizione FROM corsi");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $corsi[] = $row;
    }
    $result->free();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8" />
    <title>FITNESS STUDIO - Corsi</title>
    <link rel="stylesheet" href="style/corsi.css" type="text/css" />
    <script>
    // Funzione JavaScript per prenotare un corso via fetch (AJAX)
    function prenotaCorso(id_corso) {
        fetch('prenota_corso.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id_corso=' + encodeURIComponent(id_corso)
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                const btn = document.getElementById('btn_' + id_corso);
                if (btn) {
                    btn.disabled = true;
                    btn.value = "Prenotato";
                }
                location.reload(); // Ricarica per aggiornare prenotazioni
            }
        })
        .catch(() => alert("Errore di rete o server"));
    }
    </script>
</head>

<body>
<!-- Header e navigazione -->
<div id="header" class="main-header">
    <div class="logo">FITNESS STUDIO</div>
    <ul class="main-menu">
        <li><a href="home_page.php">Home</a></li>
        <?php if (!$id_user): ?>
            <!-- Link per utenti non autenticati -->
            <li><a href="login.php">Area Riservata</a></li>
        <?php else: ?>
            <!-- Link visibili solo agli utenti loggati -->
            <li><a href="stato_abbonamento.php">Stato abbonamento</a></li>
            <li><a href="logout.php">Logout</a></li>
        <?php endif; ?>
        <li><a href="promo.php">Promo</a></li>
        <li><a href="Chi_Siamo.php">Chi Siamo</a></li>
    </ul>
</div>

<h1>I Nostri Corsi</h1>

<!-- Tabella corsi -->
<table>
    <tr>
        <th><p class="tb_desc">CORSO</p></th>
        <th><p class="tb_desc">DESCRIZIONE</p></th>
        <th><p class="tb_desc">PRENOTA</p></th>
    </tr>

    <?php
    // Mappa immagini per corso
    $imgMap = [
        'Pilates' => 'Pilates.png',
        'Funzionale' => 'Funzionale.png',
        'Zumba' => 'Zumba.png',
        'Ginnastica Dolce' => 'GinnasticaDolce.png'
    ];
    ?>

    <?php foreach ($corsi as $corso): ?>
    <tr>
        <td>
            <?php
            // Se non esiste l'immagine specifica, usa default
            $imgFile = $imgMap[$corso['nome_corso']] ?? 'default.png';
            ?>
            <img src="img/<?= htmlspecialchars($imgFile) ?>" 
                 alt="Immagine corso <?= htmlspecialchars($corso['nome_corso']) ?>" 
                 class="corso-img" />
            <p class="nome"><?= htmlspecialchars($corso['nome_corso']) ?></p>
        </td>
        <td>
            <p class="descrizione"><?= nl2br(htmlspecialchars($corso['descrizione'])) ?></p>
        </td>
        <td>
            <?php if ($id_user && $has_active_abbonamento): ?>
                <!-- Bottone prenotazione attivo solo con login e abbonamento -->
                <input type="button" 
                       id="btn_<?= (int)$corso['id_corso'] ?>" 
                       class="btn-prenota" 
                       value="Prenota" 
                       onclick="prenotaCorso(<?= (int)$corso['id_corso'] ?>)" />
            <?php else: ?>
                <em>Login e abbonamento attivo richiesti</em>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>

<!-- Prenotazioni dell'utente loggato -->
<?php if ($id_user): ?>
<h2 style="text-align:center; color: yellow;">Le tue prenotazioni</h2>

<?php if (count($prenotazioni) > 0): ?>
<table>
    <tr>
        <th><p class="tb_desc">CORSO</p></th>
        <th><p class="tb_desc">DESCRIZIONE</p></th>
        <th><p class="tb_desc">DATA</p></th>
    </tr>
    <?php foreach ($prenotazioni as $p): ?>
    <tr>
        <td><p class="nome"><?= htmlspecialchars($p['nome_corso']) ?></p></td>
        <td><p class="descrizione"><?= nl2br(htmlspecialchars($p['descrizione'])) ?></p></td>
        <td><p class="descrizione"><?= htmlspecialchars(date("d/m/Y", strtotime($p['data_prenotazione']))) ?></p></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
    <p style="text-align:center;">Nessuna prenotazione effettuata.</p>
<?php endif; ?>
<?php endif; ?>

<!-- Footer -->
<div id="footer">
    <p>&copy; 2025 FITNESS STUDIO. Tutti i diritti riservati.</p>
</div>
</body>
</html>
