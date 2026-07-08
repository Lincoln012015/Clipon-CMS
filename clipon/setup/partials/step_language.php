    <form method="POST">
        <?= Csrf::inputField(); ?>
        <div class="lang-grid">
            <?php foreach ($availableLangs as $l): ?>
                <button type="submit" name="set_lang" value="<?= $l ?>" class="lang-opt <?= $setupLang === $l ? 'active' : '' ?>">
                    <?= strtoupper($l) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </form>
