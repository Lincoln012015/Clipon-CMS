    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= $error ?>
        </div>
    <?php endif; ?>
    <form method="POST" action="setup.php?step=3">
        <?= Csrf::inputField(); ?>
        <div class="panel panel-muted">
            <div class="form-group">
                <label class="form-label"><?= $trans['name'] ?></label>
                <input type="text" name="name" class="input-field" required value="<?= htmlspecialchars($request->post('name', '')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= $trans['login'] ?></label>
                <input type="text" name="login" class="input-field" required value="<?= htmlspecialchars($request->post('login', '')) ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><?= $trans['password'] ?></label>
                <input type="password" name="password" class="input-field" required>
            </div>
            <div class="form-group">
                <label class="form-label"><?= $trans['confirm_password'] ?></label>
                <input type="password" name="confirm" class="input-field" required>
            </div>
        </div>
        <div class="step-actions">
            <button type="submit" name="register_admin" class="btn"><?= $trans['register'] ?></button>
        </div>
    </form>
