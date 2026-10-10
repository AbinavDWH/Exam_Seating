import markSvg from '../assets/deskmap-mark.svg';
import fullSvg from '../assets/deskmap-full.svg';

/**
 * DeskMap Brand Logo Component
 * - variant="mark" (default): Small mark (brush circle + "D") for sidebar, nav bar, and tabs.
 * - variant="full": Full logo (brush circle with "DeskMap" inside) for login page, student portal header, and print sheets.
 */
export default function BrandLogo({
  variant = 'mark',
  size = 32,
  width,
  height,
  className = '',
  alt = 'DeskMap',
  style = {},
}) {
  const src = variant === 'full' ? fullSvg : markSvg;
  const w = width || (variant === 'full' ? size * 1.04 : size);
  const h = height || size;

  return (
    <img
      src={src}
      width={w}
      height={h}
      alt={alt}
      className={`deskmap-logo deskmap-logo-${variant} ${className}`}
      style={{
        display: 'inline-block',
        verticalAlign: 'middle',
        objectFit: 'contain',
        ...style,
      }}
      loading="eager"
    />
  );
}
