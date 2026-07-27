<?php
/* admin/views/components/modal.php — shared confirm/edit modal shell.
 * $bodyHtml and $actionsHtml are trusted, already-built HTML (not escaped here).
 * $id and $title are escaped.
 */
function render_modal(string $id, string $title, string $bodyHtml, string $actionsHtml): void {
  $safeId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
  $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
  echo '<div id="' . $safeId . '" class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="' . $safeId . '-title">'
    . '<div class="confirm-modal__panel">'
    . '<div class="confirm-modal__header">'
    . '<h3 class="confirm-modal__title" id="' . $safeId . '-title">' . $safeTitle . '</h3>'
    . '<button type="button" class="confirm-modal__close" data-modal-close aria-label="Đóng"><i class="bi bi-x-lg"></i></button>'
    . '</div>'
    . '<div class="confirm-modal__body">' . $bodyHtml . '</div>'
    . '<div class="confirm-modal__footer">' . $actionsHtml . '</div>'
    . '</div>'
    . '</div>';
}
