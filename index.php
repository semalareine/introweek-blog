<?php

require_once __DIR__ . '/Config.php';
function verwijderMediaBestand(PDO $pdo, int $id): void
{
    $ophalen = $pdo->prepare('SELECT media FROM post WHERE id = :id');
    $ophalen->execute([':id' => $id]);
    $mediaBestand = $ophalen->fetchColumn();

    if ($mediaBestand) {
        $mediaVolledigPad = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $mediaBestand;
        if (is_file($mediaVolledigPad)) {
            unlink($mediaVolledigPad);
        }
    }
}

$gebruiker = huidigeGebruiker($pdo);

$actieGevraagd = isset($_GET['nieuw']) || isset($_GET['bewerk']) || isset($_GET['verwijder']) || $_SERVER['REQUEST_METHOD'] === 'POST';
if ($actieGevraagd && !$gebruiker) {
    header('Location: inloggen.php');
    exit;
}

if (isset($_GET['verwijder'])) {
    $id = (int) $_GET['verwijder'];

    $stmt = $pdo->prepare('SELECT gebruiker_id FROM post WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $eigenaarId = $stmt->fetchColumn();

    if ($eigenaarId !== false && (int) $eigenaarId === (int) $gebruiker['id']) {
        verwijderMediaBestand($pdo, $id);
        $verwijder = $pdo->prepare('DELETE FROM post WHERE id = :id');
        $verwijder->execute([':id' => $id]);
    }

    header('Location: index.php');
    exit;
}

$bewerkId = isset($_GET['bewerk']) ? (int) $_GET['bewerk'] : null;
$bewerkRecord = null;

if ($bewerkId) {
    $ophalen = $pdo->prepare('SELECT id, titel, bericht, naam, datum, media, gebruiker_id FROM post WHERE id = :id');
    $ophalen->execute([':id' => $bewerkId]);
    $mogelijkRecord = $ophalen->fetch();

    if ($mogelijkRecord && (int) $mogelijkRecord['gebruiker_id'] === (int) $gebruiker['id']) {
        $bewerkRecord = $mogelijkRecord;
    } else {
        header('Location: index.php');
        exit;
    }
}

$toonFormulier = isset($_GET['nieuw']) || $bewerkRecord !== null || $_SERVER['REQUEST_METHOD'] === 'POST';
$fouten = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postId  = isset($_POST['post_id']) ? (int) $_POST['post_id'] : null;
    $titel   = trim($_POST['titel'] ?? '');
    $bericht = trim($_POST['bericht'] ?? '');

    if ($titel === '') {
        $fouten[] = 'Titel is verplicht.';
    }
    if ($bericht === '') {
        $fouten[] = 'Bericht is verplicht.';
    }

    $bestaandMedia = null;
    if ($postId) {
        $ophalen = $pdo->prepare('SELECT id, titel, bericht, naam, datum, media, gebruiker_id FROM post WHERE id = :id');
        $ophalen->execute([':id' => $postId]);
        $mogelijkRecord = $ophalen->fetch();

        // Alleen de eigenaar mag opslaan.
        if (!$mogelijkRecord || (int) $mogelijkRecord['gebruiker_id'] !== (int) $gebruiker['id']) {
            header('Location: index.php');
            exit;
        }
        $bewerkRecord = $mogelijkRecord;
        $bestaandMedia = $bewerkRecord['media'];
    }

    $nieuweMediaBestandsnaam = null;
    $mediaGeupload = isset($_FILES['media']) && $_FILES['media']['error'] !== UPLOAD_ERR_NO_FILE;

    if (empty($fouten) && $mediaGeupload) {
        if ($_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            $fouten[] = 'Het uploaden van het bestand is mislukt.';
        } else {
            $toegestaneExtensies = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov', 'mp3', 'wav', 'ogg'];
            $extensie = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));

            if (!in_array($extensie, $toegestaneExtensies, true)) {
                $fouten[] = 'Dit bestandstype wordt niet ondersteund.';
            } else {
                $nieuweMediaBestandsnaam = uniqid('media_', true) . '.' . $extensie;
                $bestemming = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $nieuweMediaBestandsnaam;

                if (!move_uploaded_file($_FILES['media']['tmp_name'], $bestemming)) {
                    $fouten[] = 'Het opslaan van het bestand is mislukt.';
                }
            }
        }
    }

    if (empty($fouten)) {
        if ($postId) {
            $media = $bestaandMedia;
            if ($nieuweMediaBestandsnaam !== null) {
                if ($bestaandMedia) {
                    verwijderMediaBestand($pdo, $postId);
                }
                $media = $nieuweMediaBestandsnaam;
            }

            $update = $pdo->prepare('UPDATE post SET titel = :titel, bericht = :bericht, media = :media WHERE id = :id');
            $update->execute([
                    ':titel'   => $titel,
                    ':bericht' => $bericht,
                    ':media'   => $media,
                    ':id'      => $postId,
            ]);
        } else {
            $insert = $pdo->prepare('INSERT INTO post (titel, bericht, naam, datum, media, gebruiker_id) VALUES (:titel, :bericht, :naam, :datum, :media, :gebruiker_id)');
            $insert->execute([
                    ':titel'        => $titel,
                    ':bericht'      => $bericht,
                    ':naam'         => $gebruiker['gebruikersnaam'],
                    ':datum'        => date('Y-m-d H:i'),
                    ':media'        => $nieuweMediaBestandsnaam,
                    ':gebruiker_id' => $gebruiker['id'],
            ]);
        }

        header('Location: index.php');
        exit;
    }
}

$records = $pdo->query(
        'SELECT post.id, post.titel, post.bericht, post.datum, post.media, post.gebruiker_id,
            post.naam AS oude_naam, users.gebruikersnaam, users.pfp
     FROM post
     LEFT JOIN users ON users.id = post.gebruiker_id
     ORDER BY post.id DESC'
)->fetchAll();

function formatteerDatum(string $datum): string
{
    $tijdstip = strtotime($datum);
    return $tijdstip === false ? $datum : date('Y-m-d H:i', $tijdstip);
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/Nav.php'; ?>

<?php if (!$toonFormulier): ?>

    <?php if ($gebruiker): ?>
        <a href="?nieuw=1" class="post-knop"><span class="knop-pil">Post</span></a>
    <?php else: ?>
        <p class="login-hint"><a href="inloggen.php">Log in</a> om een bericht te posten.</p>
    <?php endif; ?>

    <?php foreach ($records as $record):
        $weergaveNaam = $record['gebruikersnaam'] ?? $record['oude_naam'];
        $isEigenaar = $gebruiker && (int) $record['gebruiker_id'] === (int) $gebruiker['id'];
        ?>
        <div class="post">
            <div class="post-header">
                <?= pfpHtml($record['pfp'] ?? null, $weergaveNaam, 32) ?>
                <div>
                    <div class="post-naam"><?= htmlspecialchars($weergaveNaam) ?></div>
                    <div class="post-datum"><?= htmlspecialchars(formatteerDatum($record['datum'])) ?></div>
                </div>
            </div>
            <h3><?= htmlspecialchars($record['titel']) ?></h3>
            <p><?= nl2br(htmlspecialchars($record['bericht'])) ?></p>
            <?php if (!empty($record['media'])):
                $mediaPad = 'uploads/' . $record['media'];
                $extensie = strtolower(pathinfo($record['media'], PATHINFO_EXTENSION));
                $afbeeldingExtensies = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $videoExtensies = ['mp4', 'webm', 'mov'];
                $audioExtensies = ['mp3', 'wav', 'ogg'];
                ?>
                <?php if (in_array($extensie, $afbeeldingExtensies, true)): ?>
                <p><img src="<?= htmlspecialchars($mediaPad) ?>" alt=""></p>
            <?php elseif (in_array($extensie, $videoExtensies, true)): ?>
                <p><video src="<?= htmlspecialchars($mediaPad) ?>" controls></video></p>
            <?php elseif (in_array($extensie, $audioExtensies, true)): ?>
                <p><audio src="<?= htmlspecialchars($mediaPad) ?>" controls></audio></p>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($isEigenaar): ?>
                <div class="post-acties">
                    <a href="?bewerk=<?= urlencode((string) $record['id']) ?>">Edit</a>
                    <form class="verwijder-form" method="post" action="index.php?verwijder=<?= urlencode((string) $record['id']) ?>" onsubmit="return confirm('zeker queen?');">
                        <button type="submit">Verwijderen</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

<?php else: ?>

    <?php if (!empty($fouten)): ?>
        <ul class="fouten">
            <?php foreach ($fouten as $fout): ?>
                <li><?= htmlspecialchars($fout) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="index.php" enctype="multipart/form-data">
        <?php if ($bewerkRecord): ?>
            <input type="hidden" name="post_id" value="<?= htmlspecialchars((string) $bewerkRecord['id']) ?>">
        <?php endif; ?>
        <p>
            <label for="titel">Titel</label><br>
            <input type="text" id="titel" name="titel" value="<?= htmlspecialchars($_POST['titel'] ?? $bewerkRecord['titel'] ?? '') ?>">
        </p>
        <p>
            <label for="bericht">Bericht</label><br>
            <textarea id="bericht" name="bericht" rows="5" cols="40"><?= htmlspecialchars($_POST['bericht'] ?? $bewerkRecord['bericht'] ?? '') ?></textarea>
        </p>
        <p>
            <label for="media">Media</label><br>
            <input type="file" id="media" name="media" accept="image/*,video/*,audio/*">
            <?php if ($bewerkRecord && !empty($bewerkRecord['media'])): ?>
        <div class="huidige-media">Huidige media: <?= htmlspecialchars($bewerkRecord['media']) ?> (laat leeg om te behouden)</div>
    <?php endif; ?>
        </p>
        <p style="display: flex; align-items: center; gap: 12px;">
            <input type="image" src="smashbutton.png" alt="Smash to post" class="smash-knop">
            <a href="index.php" class="annuleren">Annuleren</a>
        </p>
    </form>

<?php endif; ?>

</body>
</html>