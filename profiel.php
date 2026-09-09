<?php

require_once __DIR__ . '/Config.php';

if (!ingelogd()) {
    header('Location: inloggen.php');
    exit;
}

$gebruiker = huidigeGebruiker($pdo);
$fouten = [];
$succes = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nieuwPfpBestandsnaam = null;
    $mediaGeupload = isset($_FILES['pfp']) && $_FILES['pfp']['error'] !== UPLOAD_ERR_NO_FILE;

    if ($mediaGeupload) {
        if ($_FILES['pfp']['error'] !== UPLOAD_ERR_OK) {
            $fouten[] = 'Het uploaden van de profielfoto is mislukt.';
        } else {
            $toegestaneExtensies = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $extensie = strtolower(pathinfo($_FILES['pfp']['name'], PATHINFO_EXTENSION));

            if (!in_array($extensie, $toegestaneExtensies, true)) {
                $fouten[] = 'Alleen afbeeldingen (jpg, png, gif, webp) zijn toegestaan als profielfoto.';
            } else {
                $nieuwPfpBestandsnaam = uniqid('pfp_', true) . '.' . $extensie;
                $bestemming = __DIR__ . DIRECTORY_SEPARATOR . 'pfp' . DIRECTORY_SEPARATOR . $nieuwPfpBestandsnaam;

                if (!move_uploaded_file($_FILES['pfp']['tmp_name'], $bestemming)) {
                    $fouten[] = 'Het opslaan van de profielfoto is mislukt.';
                }
            }
        }
    }

    $huidigWachtwoord = $_POST['huidig_wachtwoord'] ?? '';
    $nieuwWachtwoord  = trim($_POST['nieuw_wachtwoord'] ?? '');

    if ($nieuwWachtwoord !== '') {
        $stmt = $pdo->prepare('SELECT wachtwoord_hash FROM users WHERE id = :id');
        $stmt->execute([':id' => $gebruiker['id']]);
        $huidigeHash = $stmt->fetchColumn();

        if (!password_verify($huidigWachtwoord, $huidigeHash)) {
            $fouten[] = 'Huidig wachtwoord is onjuist.';
        } elseif (strlen($nieuwWachtwoord) < 6) {
            $fouten[] = 'Nieuw wachtwoord moet minstens 6 tekens zijn.';
        }
    }

    if (empty($fouten)) {
        if ($nieuwPfpBestandsnaam) {
            if (!empty($gebruiker['pfp'])) {
                $oudPad = __DIR__ . DIRECTORY_SEPARATOR . 'pfp' . DIRECTORY_SEPARATOR . $gebruiker['pfp'];
                if (is_file($oudPad)) {
                    unlink($oudPad);
                }
            }
            $update = $pdo->prepare('UPDATE users SET pfp = :pfp WHERE id = :id');
            $update->execute([':pfp' => $nieuwPfpBestandsnaam, ':id' => $gebruiker['id']]);
        }

        if ($nieuwWachtwoord !== '') {
            $update = $pdo->prepare('UPDATE users SET wachtwoord_hash = :hash WHERE id = :id');
            $update->execute([':hash' => password_hash($nieuwWachtwoord, PASSWORD_DEFAULT), ':id' => $gebruiker['id']]);
        }

        $gebruiker = huidigeGebruiker($pdo);
        $succes = true;
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profiel — Blog</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/nav.php'; ?>

<h1 class="auth-titel">Profiel bewerken</h1>

<?php if ($succes): ?>
    <p class="melding">Profiel bijgewerkt.</p>
<?php endif; ?>

<?php if (!empty($fouten)): ?>
    <ul class="fouten">
        <?php foreach ($fouten as $fout): ?>
            <li><?= htmlspecialchars($fout) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="profiel.php" enctype="multipart/form-data">
    <p>
        <label>Profielfoto</label><br>
    <div class="huidige-pfp">
        <?= pfpHtml($gebruiker['pfp'], $gebruiker['gebruikersnaam'], 48) ?>
        <span><?= htmlspecialchars($gebruiker['gebruikersnaam']) ?></span>
    </div>
    <input type="file" id="pfp" name="pfp" accept="image/*">
    </p>
    <p>
        <label for="huidig_wachtwoord">Huidig wachtwoord (alleen nodig om het wachtwoord te wijzigen)</label><br>
        <input type="password" id="huidig_wachtwoord" name="huidig_wachtwoord">
    </p>
    <p>
        <label for="nieuw_wachtwoord">Nieuw wachtwoord (laat leeg om het huidige te behouden)</label><br>
        <input type="password" id="nieuw_wachtwoord" name="nieuw_wachtwoord">
    </p>
    <p>
        <button type="submit" class="knop-pil">Opslaan</button>
    </p>
</form>

</body>
</html>