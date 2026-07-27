<?php
function render_table(array $headers, string $rowsHtml): void {
  echo '<div class="table-scroll"><table><thead><tr>';
  foreach ($headers as $h) { echo '<th>' . htmlspecialchars($h) . '</th>'; }
  echo '</tr></thead><tbody>' . $rowsHtml . '</tbody></table></div>';
}
