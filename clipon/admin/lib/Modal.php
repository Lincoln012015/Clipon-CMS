<?php
/**
 * Клас для генерації модальних вікон (Clipon CMS Clean UI)
 */
class Modal {
    /**
     * Починає вивід модального вікна
     */
    public static function start($id, $title, $maxWidth = '500px') {
        $safeId = htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');
        $safeMaxWidth = htmlspecialchars((string)$maxWidth, ENT_QUOTES, 'UTF-8');
        $titleId = $safeId . '-title';
        ?>
        <div id="<?= $safeId ?>" class="modal cms-modal shadow-lg" role="dialog" aria-modal="true" aria-labelledby="<?= $titleId ?>">
            <div class="modal-content cms-modal-content" style="max-width: <?= $safeMaxWidth ?>;">
                <div class="modal-header cms-modal-header">
                    <h3 id="<?= $titleId ?>"><?= $safeTitle ?></h3>
                    <button type="button" class="close-modal cms-modal-close" onclick="AdminUI.closeModal('<?= $safeId ?>')" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body cms-modal-body">
        <?php
    }

    /**
     * Закінчує вивід модального вікна
     */
    public static function end($hasFooter = false) {
        if (!$hasFooter) {
            echo '</div>'; // close modal-body
        }
        echo '</div></div>'; // close modal-content and modal
    }

    /**
     * Вивід футера модального вікна
     */
    public static function footer($primaryLabel = null, $secondaryLabel = null, $primaryId = null) {
        $secondaryLabel = $secondaryLabel ?: __('cancel');
        $safeSecondary = htmlspecialchars((string)$secondaryLabel, ENT_QUOTES, 'UTF-8');
        $safePrimary = $primaryLabel !== null ? htmlspecialchars((string)$primaryLabel, ENT_QUOTES, 'UTF-8') : null;
        $safePrimaryId = $primaryId !== null ? htmlspecialchars((string)$primaryId, ENT_QUOTES, 'UTF-8') : null;
        ?>
                </div> <!-- close modal-body -->
                <div class="modal-footer cms-modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="AdminUI.closeModal(this.closest('.modal').id)"><?= $safeSecondary ?></button>
                    <?php if ($primaryLabel): ?>
                        <button type="submit" <?= $safePrimaryId ? "form=\"$safePrimaryId\"" : "" ?> class="btn btn-primary"><?= $safePrimary ?></button>
                    <?php endif; ?>
                </div>
        <?php
    }
}
