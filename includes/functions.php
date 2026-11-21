<?php
/**
 * Small helpers shared by every page (frontend).
 * Include with: require_once __DIR__ . '/../includes/functions.php';
 */

// Escape text before printing it in HTML (prevents XSS).
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Format a number as Philippine peso: peso(24500) -> ₱24,500
function peso($amount, int $decimals = 0): string {
    return '₱' . number_format((float)$amount, $decimals);
}

// Maps a status text to a badge colour class (see .badge-* in style.css).
function badge_class(string $status): string {
    $map = [
        'pending'     => 'badge-amber',
        'in progress' => 'badge-blue',
        'completed'   => 'badge-green',
        'delivered'   => 'badge-green',
        'fully paid'  => 'badge-green',
        'partial'     => 'badge-pink',
        'low stock'   => 'badge-pink',
        'in stock'    => 'badge-green',
    ];
    return $map[strtolower($status)] ?? 'badge-pink';
}

// Inline SVG icons (stroke icons, they take the text colour).
function icon(string $name): string {
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'orders'    => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'costing'   => '<path d="M6 3h12l4 6-10 12L2 9z"/><path d="M2 9h20"/>',
        'inventory' => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'payments'  => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/><path d="M16 15h2"/>',
        'reports'   => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'wallet'    => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'box'       => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5"/>',
        'chevron'   => '<path d="m6 9 6 6 6-6"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'x'         => '<path d="M18 6 6 18M6 6l12 12"/>',
        'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'upload'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
        'check'     => '<path d="m5 12 5 5L20 7"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'menu'      => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'arrow'     => '<path d="M7 17 17 7M8 7h9v9"/>',
        'edit'      => '<path d="m4 20 4.5-1L19 8.5 15.5 5 5 15.5 4 20z"/><path d="m13.5 7 3.5 3.5"/>',
        'alert'     => '<path d="M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'sliders'   => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3"/><path d="M2 14h4M10 8h4M18 16h4"/>',
        'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
        'document'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true">' . $p . '</svg>';
}