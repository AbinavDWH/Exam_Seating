/**
 * Unified ExamSeat Brand Logo Mark
 * Minimalist geometric seating grid: 3x3 array of desks with 1 highlighted allocated seat.
 * Anti-AI design: strictly on integer pixel grid, 2 colors max, no graduation caps, no shadows.
 */
export default function BrandLogo({ size = 20, className = '' }) {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-hidden="true"
    >
      {/* Row 1 */}
      <rect x="4" y="4" width="4" height="4" rx="1" fill="currentColor" fillOpacity="0.35" />
      <rect x="10" y="4" width="4" height="4" rx="1" fill="currentColor" fillOpacity="0.35" />
      <rect x="16" y="4" width="4" height="4" rx="1" fill="currentColor" fillOpacity="0.35" />

      {/* Row 2: Desk 3 is YOUR allocated seat */}
      <rect x="4" y="10" width="4" height="4" rx="1" fill="currentColor" fillOpacity="0.35" />
      <rect x="10" y="10" width="4" height="4" rx="1" fill="currentColor" fillOpacity="0.35" />
      <rect x="16" y="10" width="4" height="4" rx="1" fill="currentColor" />

      {/* Row 3 */}
      <rect x="4" y="16" width="4" height="4" rx="1" fill="currentColor" fillOpacity="0.35" />
      <rect x="10" y="16" width="4" height="4" rx="1" fill="currentColor" fillOpacity="0.35" />
      <rect x="16" y="16" width="4" height="4" rx="1" fill="currentColor" fillOpacity="0.35" />
    </svg>
  );
}
