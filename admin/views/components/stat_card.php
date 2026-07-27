<?php
function render_stat_card(string $label, string $value, string $delta, string $deltaDir, string $icon): void {
  $dir = $deltaDir === 'down' ? 'down' : 'up';
  $arrow = $dir === 'up' ? '▲' : '▼';
  echo '<div class="card kpi"><div class="top"><span class="label">'
    . htmlspecialchars($label) . '</span><span class="ico"><i class="bi ' . htmlspecialchars($icon) . '"></i></span></div>'
    . '<div class="val num">' . htmlspecialchars($value) . '</div>'
    . '<div class="delta ' . $dir . '">' . $arrow . ' ' . htmlspecialchars($delta) . '</div></div>';
}
