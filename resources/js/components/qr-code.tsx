/** The enrolment's QR code (AU-16), encoded by the server and drawn here from its modules — no remote image, no raw
    markup. Shared by PG-004 and PG-065. */
export function QrCode({ rows, label }: { rows: string[]; label: string }) {
    const size = rows.length;
    const quiet = 4;

    return (
        <svg
            role="img"
            aria-label={label}
            viewBox={`${String(-quiet)} ${String(-quiet)} ${String(size + quiet * 2)} ${String(size + quiet * 2)}`}
            className="size-48 self-start bg-white"
            shapeRendering="crispEdges"
        >
            {rows.flatMap((row, y) =>
                Array.from({ length: row.length }, (_, x) =>
                    row.charAt(x) === '1' ? <rect key={`${String(x)}-${String(y)}`} x={x} y={y} width={1} height={1} fill="#111827" /> : null,
                ),
            )}
        </svg>
    );
}
