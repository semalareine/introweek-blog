<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$databasePad = __DIR__ . DIRECTORY_SEPARATOR . 'post.sqlite';
$pdo = new PDO('sqlite:' . $databasePad);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS users (
        id integer primary key autoincrement not null,
        gebruikersnaam text not null unique,
        wachtwoord_hash text not null,
        pfp text,
        aangemaakt_op text not null
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS post (
        id integer primary key autoincrement not null,
        titel text not null,
        bericht text not null,
        naam text not null,
        datum text not null,
        media text,
        gebruiker_id integer
    )'
);

// Migratie: kolommen toevoegen aan een bestaande database zonder data te verliezen.
function kolomBestaat(PDO $pdo, string $tabel, string $kolom): bool
{
    $kolommen = $pdo->query("PRAGMA table_info($tabel)")->fetchAll();
    foreach ($kolommen as $rij) {
        if ($rij['name'] === $kolom) {
            return true;
        }
    }
    return false;
}

if (!kolomBestaat($pdo, 'post', 'media')) {
    $pdo->exec('ALTER TABLE post ADD COLUMN media text');
}
if (!kolomBestaat($pdo, 'post', 'gebruiker_id')) {
    $pdo->exec('ALTER TABLE post ADD COLUMN gebruiker_id integer');
}

// Mappen voor geuploade bestanden.
foreach (['uploads', 'pfp'] as $map) {
    $pad = __DIR__ . DIRECTORY_SEPARATOR . $map;
    if (!is_dir($pad)) {
        mkdir($pad, 0755, true);
    }
}

function ingelogd(): bool
{
    return isset($_SESSION['gebruiker_id']);
}

function huidigeGebruiker(PDO $pdo): ?array
{
    if (!ingelogd()) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, gebruikersnaam, pfp FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['gebruiker_id']]);
    $gebruiker = $stmt->fetch();

    return $gebruiker ?: null;
}

/**
 * Geeft HTML terug voor een profielfoto: de geuploade afbeelding, of anders
 * een gekleurde cirkel met de eerste letter van de naam.
 */
function pfpHtml(?string $pfpBestand, string $naam, int $grootte = 36): string
{
    $naamVeilig = htmlspecialchars($naam);

    if ($pfpBestand) {
        $pad = htmlspecialchars('pfp/' . $pfpBestand);
        return '<img src="' . $pad . '" alt="' . $naamVeilig . '" class="pfp" style="width:' . $grootte . 'px;height:' . $grootte . 'px;">';
    }

    $letter = htmlspecialchars(mb_strtoupper(mb_substr($naam, 0, 1)));
    $kleuren = ['#f4212e', '#7856ff', '#1d9bf0', '#00ba7c', '#ffad1f', '#e0245e'];
    $index = array_sum(array_map('ord', str_split($naam))) % count($kleuren);
    $kleur = $kleuren[$index];

    return '<div class="pfp pfp-fallback" style="width:' . $grootte . 'px;height:' . $grootte . 'px;line-height:' . $grootte . 'px;background-color:' . $kleur . ';">' . $letter . '</div>';
}