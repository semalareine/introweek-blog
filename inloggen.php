<?php

require_once __DIR__ . '/Config.php';

if (ingelogd()) {
    header('Location: index.php');
    exit;
}

$fouten = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gebruikersnaam = trim($_POST['gebruikersnaam'] ?? '');
    $wachtwoord     = $_POST['wachtwoord'] ?? '';

    $stmt = $pdo->prepare('SELECT id, wachtwoord_hash FROM users WHERE gebruikersnaam = :naam');
    $stmt->execute([':naam' => $gebruikersnaam]);
    $gevondenGebruiker = $stmt->fetch();

    if (!$gevondenGebruiker || !password_verify($wachtwoord, $gevondenGebruiker['wachtwoord_hash'])) {
        $fouten[] = 'Gebruikersnaam of wachtwoord is onjuist.';
    } else {
        $_SESSION['gebruiker_id'] = (int) $gevondenGebruiker['id'];
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
    <title>Inloggen — Blog</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/nav.php'; ?>

<h1 class="auth-titel">Inloggen</h1>

<?php if (!empty($fouten)): ?>
    <ul class="fouten">
        <?php foreach ($fouten as $fout): ?>
            <li><?= htmlspecialchars($fout) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<form method="post" action="inloggen.php">
    <p>
        <label for="gebruikersnaam">Gebruikersnaam</label><br>
        <input type="text" id="gebruikersnaam" name="gebruikersnaam" value="<?= htmlspecialchars($_POST['gebruikersnaam'] ?? '') ?>">
    </p>
    <p>
        <label for="wachtwoord">Wachtwoord</label><br>
        <input type="password" id="wachtwoord" name="wachtwoord">
    </p>
    <p>
        <button type="submit" class="knop-pil">Inloggen</button>
    </p>
</form>

<p class="auth-wissel">Nog geen account? <a href="registreren.php">Registreer je</a>.</p>

</body>
</html>