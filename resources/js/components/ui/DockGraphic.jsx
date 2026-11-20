const columns = [
    [
        { y: 0, height: 56, tone: 'brand-500' },
        { y: 62, height: 92, tone: 'brand-700' },
        { y: 160, height: 44, tone: 'brand-500' },
    ],
    [
        { y: 36, height: 68, tone: 'brand-700' },
        { y: 110, height: 94, tone: 'brand-300' },
    ],
    [
        { y: 10, height: 104, tone: 'brand-500' },
        { y: 120, height: 74, tone: 'brand-800' },
    ],
    [
        { y: 0, height: 44, tone: 'brand-800' },
        { y: 50, height: 110, tone: 'brand-500' },
        { y: 166, height: 54, tone: 'brand-800' },
    ],
    [
        { y: 60, height: 62, tone: 'brand-300' },
        { y: 128, height: 56, tone: 'brand-500' },
    ],
    [
        { y: 30, height: 70, tone: 'brand-700' },
        { y: 106, height: 78, tone: 'brand-800' },
    ],
    [{ y: 0, height: 204, tone: 'free' }],
    [
        { y: 66, height: 60, tone: 'brand-50' },
        { y: 132, height: 56, tone: 'brand-300' },
    ],
    [
        { y: 0, height: 96, tone: 'brand-300' },
        { y: 102, height: 40, tone: 'brand-50' },
    ],
    [
        { y: 76, height: 40, tone: 'brand-50' },
        { y: 122, height: 70, tone: 'brand-300' },
    ],
];

export default function DockGraphic({ className = '' }) {
    return (
        <svg viewBox="0 0 400 220" fill="none" aria-hidden="true" focusable="false" className={className}>
            {columns.map((blocks, column) =>
                blocks.map((block) => (
                    <rect
                        key={`${column}-${block.y}`}
                        x={column * 40}
                        y={block.y}
                        width={32}
                        height={block.height}
                        rx={8}
                        fill={block.tone === 'free' ? 'none' : `var(--color-${block.tone})`}
                        stroke={block.tone === 'free' ? 'var(--color-brand-300)' : undefined}
                        strokeWidth={block.tone === 'free' ? 2 : undefined}
                        strokeDasharray={block.tone === 'free' ? '6 6' : undefined}
                    />
                ))
            )}
        </svg>
    );
}
