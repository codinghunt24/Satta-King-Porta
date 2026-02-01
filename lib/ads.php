<?php
function getAdCode($pdo, $placement) {
    $stmt = $pdo->prepare("SELECT ad_code, is_active FROM ad_placements WHERE placement_name = ?");
    $stmt->execute([$placement]);
    $ad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ad && $ad['is_active'] && !empty($ad['ad_code'])) {
        $containerClass = 'ad-container ad-responsive ad-' . htmlspecialchars($placement);
        return '<div class="' . $containerClass . '" style="text-align:center;margin:20px auto;overflow:hidden;max-width:100%;">' . $ad['ad_code'] . '</div>';
    }
    return '';
}

function displayAd($pdo, $placement) {
    echo getAdCode($pdo, $placement);
}

function displayResponsiveAd($pdo, $placement, $customStyle = '') {
    $stmt = $pdo->prepare("SELECT ad_code, is_active FROM ad_placements WHERE placement_name = ?");
    $stmt->execute([$placement]);
    $ad = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ad && $ad['is_active'] && !empty($ad['ad_code'])) {
        $style = 'text-align:center;margin:20px auto;overflow:hidden;max-width:100%;' . $customStyle;
        echo '<div class="ad-container ad-responsive ad-' . htmlspecialchars($placement) . '" style="' . $style . '">';
        echo $ad['ad_code'];
        echo '</div>';
    }
}
