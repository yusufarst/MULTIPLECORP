import { type ComponentProps, createContext, type ReactNode, useContext, useId } from 'react';
import { ResponsiveContainer } from 'recharts';

import { cn } from '@/lib/utils';

// shadcn/ui's Chart composition (DESIGN_SYSTEM §10; MIT, recharts 3.8.0), kept to what V1 uses: the ageing bar of
// *Beranda* and the report — a chart of defined values with direct labels and always its data table (VP-6; GR-23;
// SLOP-34). No tooltip or legend: nothing is reachable only by hover. A series colour comes from the design tokens
// (`--color-chart-age-1`…`-5`); the style element that names them carries the request's CSP nonce (SECURITY WS-08).

export type ChartConfig = Record<string, { label?: ReactNode; color?: string }>;

const ChartContext = createContext<{ config: ChartConfig } | null>(null);

export function useChart(): { config: ChartConfig } {
    const context = useContext(ChartContext);

    if (context === null) {
        throw new Error('useChart must be used within a <ChartContainer />');
    }

    return context;
}

const INITIAL = { width: 320, height: 200 } as const;

export function ChartContainer({ id, className, children, config, ...props }: ComponentProps<'div'> & {
    config: ChartConfig;
    children: ComponentProps<typeof ResponsiveContainer>['children'];
}) {
    const unique = useId();
    const chartId = `chart-${id ?? unique.replace(/:/g, '')}`;

    return (
        <ChartContext.Provider value={{ config }}>
            <div
                data-slot="chart"
                data-chart={chartId}
                className={cn(
                    'flex aspect-video justify-center text-xs [&_.recharts-cartesian-axis-tick_text]:fill-muted-foreground [&_.recharts-layer]:outline-hidden [&_.recharts-surface]:outline-hidden',
                    className,
                )}
                {...props}
            >
                <ChartStyle id={chartId} config={config} />
                <ResponsiveContainer initialDimension={INITIAL}>{children}</ResponsiveContainer>
            </div>
        </ChartContext.Provider>
    );
}

/** The series colours as `--color-<key>` under the chart's own selector; nothing when no series names a colour. */
export function ChartStyle({ id, config }: { id: string; config: ChartConfig }) {
    const colors = Object.entries(config).filter(([, item]) => item.color !== undefined);

    if (colors.length === 0) {
        return null;
    }

    const nonce = typeof document === 'undefined' ? undefined : document.querySelector<HTMLMetaElement>('meta[property="csp-nonce"]')?.nonce;
    const css = `[data-chart=${id}] {\n${colors.map(([key, item]) => `  --color-${key}: ${String(item.color)};`).join('\n')}\n}`;

    return <style nonce={nonce}>{css}</style>;
}
