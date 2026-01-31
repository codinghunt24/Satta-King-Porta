<?php
function getAdCode($pdo, $placement) {
    $stmt = $pdo->prepare("SELECT ad_code, is_active FROM ad_placements WHERE placement_name = ?");
    $stmt->execute([$placement]);
    $ad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ad && $ad['is_active'] && !empty($ad['ad_code'])) {
        return '<div class="ad-container ad-' . htmlspecialchars($placement) . '">' . $ad['ad_code'] . '</div>';
    }
    return '';
}

function displayAd($pdo, $placement) {
    echo getAdCode($pdo, $placement);
}
