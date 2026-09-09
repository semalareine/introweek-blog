<?php

require_once __DIR__ . '/Config.php';

if (ingelogd()) {
    header('Location: index.php');
    exit;
}

$fouten = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gebruikersnaam    = trim($_POST['gebruikersnaam'] ?? '');
    $wachtwoord        = $_POST['wachtwoord'] ?? '';
    $wachtwoordHerhaal = $_POST['wachtwoord_herhaal'] ?? '';

    if ($gebruikersnaam === '') {
        $fouten[] = 'Gebruikersnaam is verplicht.';
    } elseif (mb_strlen($gebruikersnaam) > 30) {
        $fouten[] = 'Gebruikersnaam mag maximaal 30 tekens zijn.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.]+$/', $gebruikersnaam)) {
        $fouten[] = 'Gebruikersnaam mag alleen letters, cijfers, punten en underscores bevatten.';
    }

    if (strlen($wachtwoord) < 6) {
        $fouten[] = 'Wachtwoord moet minstens 6 tekens zijn.';
    }
    if ($wachtwoord !== $wachtwoordHerhaal) {
        $fouten[] = 'Wachtwoorden komen niet overeen.';
    }

    if (empty($fouten)) {
        $bestaatAl = $pdo->prepare('SELECT id FROM users WHERE gebruikersnaam = :naam');
        $bestaatAl->execute([':naam' => $gebruikersnaam]);
        if ($bestaatAl->fetch()) {
            $fouten[] = 'Deze gebruikersnaam is al in gebruik.';
        }
    }

    if (empty($fouten)) {
        $insert = $pdo->prepare('INSERT INTO users (gebruikersnaam, wachtwoord_hash, aangemaakt_op) VALUES (:naam, :hash, :datum)');
        $insert->execute([
            ':naam'  => $gebruikersnaam,
            ':hash'  => password_hash($wachtwoord, PASSWORD_DEFAULT),
            ':datum' => date('Y-m-d H:i'),
        ]);

        $_SESSION['gebruiker_id'] = (int) $pdo->lastInsertId();
        session_regenerate_id(true);

        header('Location: index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registreren — Blog</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/nav.php'; ?>

<h1 class="auth-titel">Account aanmaken</h1>

<?php if (!empty($fouten)): ?>
    <ul class="fouten">
        <?php foreach ($fouten as $fout): ?>
            <li><?= htmlspecialchars($fout) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="registreren.php">
    <p>
        <label for="gebruikersnaam">Gebruikersnaam</label><br>
        <input type="text" id="gebruikersnaam" name="gebruikersnaam" value="<?= htmlspecialchars($_POST['gebruikersnaam'] ?? '') ?>">
    </p>
    <p>
        <label for="wachtwoord">Wachtwoord</label><br>
        <input type="password" id="wachtwoord" name="wachtwoord">
    </p>
    <p>
        <label for="wachtwoord_herhaal">Wachtwoord herhalen</label><br>
        <input type="password" id="wachtwoord_herhaal" name="wachtwoord_herhaal">
    </p>
    <p>
        <button type="submit" class="knop-pil">Registreren</button>
    </p>
</form>

<p class="auth-wissel">Heb je al een account? <a href="inloggen.php">Log in</a>.</p>

</body>
</html>