<?php
$navGebruiker = huidigeGebruiker($pdo);
?>
<div class="nav">
    <a href="index.php" class="nav-merk">Blog</a>
    <div class="nav-acties">
        <?php if ($navGebruiker): ?>
            <?= pfpHtml($navGebruiker['pfp'], $navGebruiker['gebruikersnaam'], 28) ?>
            <span class="nav-naam"><?= htmlspecialchars($navGebruiker['gebruikersnaam']) ?></span>
            <a href="profiel.php">Profiel</a>
            <a href="uitloggen.php">Uitloggen</a>
        <?php else: ?>
            <a href="inloggen.php">Inloggen</a>
            <a href="registreren.php">Registreren</a>
        <?php endif; ?>
    </div>
</div>