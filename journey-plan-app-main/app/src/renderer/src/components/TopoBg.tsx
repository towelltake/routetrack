import { useEffect, useMemo, useRef } from 'react';

const CANVAS_W = 2400;
const CANVAS_H = 1600;
const LINE_COUNT = 28;

function catmullRomToSvgPath(points: ReadonlyArray<readonly [number, number]>): string {
  if (points.length < 2) return '';
  const first = points[0]!;
  const parts: string[] = [`M ${first[0].toFixed(1)} ${first[1].toFixed(1)}`];
  for (let i = 0; i < points.length - 1; i++) {
    const p0 = points[Math.max(i - 1, 0)]!;
    const p1 = points[i]!;
    const p2 = points[i + 1]!;
    const p3 = points[Math.min(i + 2, points.length - 1)]!;
    const cp1x = p1[0] + (p2[0] - p0[0]) / 6;
    const cp1y = p1[1] + (p2[1] - p0[1]) / 6;
    const cp2x = p2[0] - (p3[0] - p1[0]) / 6;
    const cp2y = p2[1] - (p3[1] - p1[1]) / 6;
    parts.push(
      `C ${cp1x.toFixed(1)} ${cp1y.toFixed(1)} ${cp2x.toFixed(1)} ${cp2y.toFixed(1)} ${p2[0].toFixed(1)} ${p2[1].toFixed(1)}`,
    );
  }
  return parts.join(' ');
}

function buildContourPaths(): string[] {
  const paths: string[] = [];
  const step = 55;
  for (let i = 0; i < LINE_COUNT; i++) {
    const baseY = (CANVAS_H / (LINE_COUNT + 1)) * (i + 1);
    const seedA = Math.sin(i * 7.13) * 0.5 + 0.5;
    const seedB = Math.cos(i * 4.71) * 0.5 + 0.5;
    const seedC = Math.sin(i * 11.27 + 0.4) * 0.5 + 0.5;
    const phase1 = seedA * Math.PI * 2;
    const phase2 = seedB * Math.PI * 2;
    const phase3 = seedC * Math.PI * 2;
    const amp1 = 28 + seedA * 44;
    const amp2 = 9 + seedB * 14;
    const amp3 = 4 + seedC * 7;
    const freq1 = 0.0030 + seedA * 0.0012;
    const freq2 = 0.014 + seedB * 0.006;
    const freq3 = 0.040 + seedC * 0.010;

    const points: Array<readonly [number, number]> = [];
    for (let x = -step; x <= CANVAS_W + step; x += step) {
      const y =
        baseY +
        Math.sin(x * freq1 + phase1) * amp1 +
        Math.sin(x * freq2 + phase2) * amp2 +
        Math.sin(x * freq3 + phase3) * amp3;
      points.push([x, y] as const);
    }
    paths.push(catmullRomToSvgPath(points));
  }
  return paths;
}

/**
 * Fixed-position topographic contour overlay that drifts a few pixels with
 * the cursor. Sits behind every screen via z-index 0 on a relatively-positioned
 * `.layout` container. Stroke is ink-on-paper at ~4-5% opacity — visible only
 * if you stop and look for it, but adds organic depth to the cream background.
 *
 * No animation loop, no canvas — the SVG is generated once and only its
 * `transform` updates on mousemove (cheap, GPU-composited).
 */
export function TopoBg() {
  const ref = useRef<HTMLDivElement>(null);
  const paths = useMemo(buildContourPaths, []);

  useEffect(() => {
    // Respect OS-level reduced-motion preference. The mousemove parallax is a
    // pure-decoration effect; users who have asked for reduced motion (Windows
    // Settings → Accessibility → Animation effects; macOS System Settings →
    // Accessibility → Display) should get a static background. Subscribe to the
    // change event so toggling the OS setting takes effect without a reload.
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    let pending = 0;
    const onMove = (e: MouseEvent) => {
      if (pending) return;
      pending = window.requestAnimationFrame(() => {
        pending = 0;
        if (!ref.current) return;
        const x = (e.clientX / window.innerWidth - 0.5) * 24;
        const y = (e.clientY / window.innerHeight - 0.5) * 16;
        ref.current.style.transform = `translate3d(${x.toFixed(2)}px, ${y.toFixed(2)}px, 0)`;
      });
    };

    const attach = () => window.addEventListener('mousemove', onMove);
    const detach = () => {
      window.removeEventListener('mousemove', onMove);
      if (pending) {
        window.cancelAnimationFrame(pending);
        pending = 0;
      }
      // Reset the inline transform so a previously-shifted background snaps
      // back to its resting position when the user opts into reduced motion.
      if (ref.current) ref.current.style.transform = '';
    };

    if (!reduceMotion.matches) attach();
    const onPrefChange = (e: MediaQueryListEvent) => {
      if (e.matches) detach();
      else attach();
    };
    reduceMotion.addEventListener('change', onPrefChange);

    return () => {
      reduceMotion.removeEventListener('change', onPrefChange);
      detach();
    };
  }, []);

  return (
    <div
      ref={ref}
      aria-hidden
      className="topo-bg"
    >
      <svg
        viewBox={`0 0 ${CANVAS_W} ${CANVAS_H}`}
        width="100%"
        height="100%"
        preserveAspectRatio="xMidYMid slice"
        xmlns="http://www.w3.org/2000/svg"
      >
        <g fill="none" stroke="#1A1714" strokeOpacity="0.05" strokeWidth="1">
          {paths.map((d, i) => (
            <path key={i} d={d} />
          ))}
        </g>
      </svg>
    </div>
  );
}
