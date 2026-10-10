<?php
/**
 * Clean inline SVG icon library for ExamSeat Admin
 * Guaranteed to load instantly with zero external font/CDN dependencies.
 */
function svg_icon(string $name, string $class = '', int $size = 20, float $strokeWidth = 2): string {
    $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class) . '"' : '';
    $styleAttr = 'width="' . $size . '" height="' . $size . '"';

    $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'speedometer2' => '<path d="M12 14v-4"/><path d="M3.34 17a10 10 0 1 1 17.32 0"/><circle cx="12" cy="14" r="2"/>',
        
        // Students / People
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'people' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'people-fill' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        
        // Rooms / Halls
        'building' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
        'door-open' => '<path d="M4 21h16"/><path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><circle cx="14" cy="12" r="1.25"/>',
        'door-open-fill' => '<path d="M4 21h16"/><path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><circle cx="14" cy="12" r="1.25"/>',
        
        // Capacity / Grid
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
        'grid-3x3-gap-fill' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
        
        // Seats Allocated / Card Checklist / Badges
        'card-checklist' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        
        // Calendar / Exams
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'calendar-event' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        
        // Magic / Generate
        'magic' => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/><path d="M5 3v4"/><path d="M3 5h4"/><path d="M19 17v4"/><path d="M17 19h4"/>',
        'sparkles' => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/><path d="M5 3v4"/><path d="M3 5h4"/><path d="M19 17v4"/><path d="M17 19h4"/>',
        
        // Brand & System: Minimalist Geometric Seating Grid (3x3 desks with 1 allocated seat)
        'seat-grid' => '<rect x="4" y="4" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="10" y="4" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="16" y="4" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="4" y="10" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="10" y="10" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="16" y="10" width="4" height="4" rx="1" fill="currentColor" stroke="none"/><rect x="4" y="16" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="10" y="16" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="16" y="16" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/>',
        'cap' => '<rect x="4" y="4" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="10" y="4" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="16" y="4" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="4" y="10" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="10" y="10" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="16" y="10" width="4" height="4" rx="1" fill="currentColor" stroke="none"/><rect x="4" y="16" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="10" y="16" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/><rect x="16" y="16" width="4" height="4" rx="1" fill="currentColor" fill-opacity="0.35" stroke="none"/>',
        'box-arrow-up-right' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
        'box-arrow-right' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        
        // Actions
        'eye' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'printer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        
        // Utilities & Search
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'filter' => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'check' => '<polyline points="20 6 9 17 4 12"/>',
        'alert-triangle' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'arrow-up-right' => '<line x1="7" y1="17" x2="17" y2="7"/><polyline points="7 7 17 7 17 17"/>',
        'sort' => '<path d="m7 15 5 5 5-5"/><path d="m7 9 5-5 5 5"/>',
        'sort-asc' => '<path d="m7 10 5-5 5 5"/><line x1="12" y1="5" x2="12" y2="19"/>',
        'sort-desc' => '<path d="m7 14 5 5 5-5"/><line x1="12" y1="5" x2="12" y2="19"/>',
        'move' => '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
        'swap' => '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
        'arrow-left-right' => '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
    ];

    $inner = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';

    return '<svg xmlns="http://www.w3.org/2000/svg" ' . $styleAttr . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' . $strokeWidth . '" stroke-linecap="round" stroke-linejoin="round"' . $classAttr . ' aria-hidden="true">' . $inner . '</svg>';
}
