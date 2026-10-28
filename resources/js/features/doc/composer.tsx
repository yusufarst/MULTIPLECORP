// The Document composer and paper (DESIGN_SYSTEM §10, §14.3; D-DS-23; GR-10; DC-11; INT-012): one component for every
// page that composes a generated document — PG-036 here, PG-014, PG-033, PG-042 and PG-043 with their Tasks. From
// 1,025 px the form sits on the left and the live preview on the right, side by side, never a tab — the preview at
// VD-07's share, 40 % to 1,279 px with the shell's rail, 45 % from 1,280 px (`--composer-preview`) —, sticky with "Pas lebar", "100%" and
// "Layar penuh"; below 1,025 px "Rincian | Pratinjau"; on the phone Edit → Preview → Finalize, the preview
// full-screen. The paper is a named region — never a live region, so typing is not announced —, after the form in
// reading and focus order, its scrolling frame reachable by keyboard (§15). "Cetak Draf" prints the composed paper as
// a marked draft (§17); nothing is written.
import { Expand, Printer } from 'lucide-react';
import { type ReactNode, useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

import { type Paper, renderPaper } from '@/features/doc/paper';
import { paperNodes } from '@/features/doc/paper-nodes';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Icon } from '@/components/ui/icon';
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/components/ui/tabs';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/** The paper's natural width, A4's 210 mm at CSS's 96 px to the inch. */
const PAPER_PX = (210 / 25.4) * 96;

type Band = 'desktop' | 'tablet' | 'phone';
type Zoom = 'fit' | 'actual';
type Step = 'edit' | 'preview' | 'finalize';

// The bands of DESIGN_SYSTEM §6: `--breakpoint-lg` (1,025 px) and `--breakpoint-md`.
const DESKTOP = '(width >= 64.0625rem)';
const PHONE = '(width < 48rem)';

function bandOf(): Band {
    return window.matchMedia(DESKTOP).matches ? 'desktop' : window.matchMedia(PHONE).matches ? 'phone' : 'tablet';
}

function useBand(): Band {
    const [band, setBand] = useState<Band>(bandOf);

    useEffect(() => {
        const queries = [window.matchMedia(DESKTOP), window.matchMedia(PHONE)];
        const update = () => {
            setBand(bandOf());
        };

        for (const query of queries) {
            query.addEventListener('change', update);
        }

        return () => {
            for (const query of queries) {
                query.removeEventListener('change', update);
            }
        };
    }, []);

    return band;
}

/** The composed paper, fitted to its frame's width or at its own size: the template's elements through the allowlist. */
function PaperFrame({ nodes, zoom, label, className }: { nodes: ReactNode; zoom: Zoom; label: string; className?: string }) {
    const frame = useRef<HTMLDivElement>(null);
    const sheet = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const outer = frame.current;
        const inner = sheet.current;

        if (outer === null || inner === null) return;

        const fit = () => {
            const scale = zoom === 'fit' ? Math.min(1, (outer.clientWidth - 2) / PAPER_PX) : 1;
            inner.style.transform = scale === 1 ? '' : `scale(${String(scale)})`;
            inner.style.transformOrigin = 'top left';
            // The scaled sheet keeps its layout box; the frame scrolls only as far as the scaled paper reaches.
            inner.style.marginBottom = scale === 1 ? '' : `${String(-(1 - scale) * inner.offsetHeight)}px`;
            inner.style.marginRight = scale === 1 ? '' : `${String(-(1 - scale) * inner.offsetWidth)}px`;
        };
        const observer = new ResizeObserver(fit);
        observer.observe(outer);
        fit();

        return () => {
            observer.disconnect();
        };
    }, [zoom, nodes]);

    return (
        // The paper's scrolling frame is reachable by keyboard (§15; 2.1.1).
        <div ref={frame} role="region" aria-label={label} tabIndex={0} data-slot="paper-frame" className={cn('min-w-0 overflow-auto rounded-md border border-border-subtle bg-surface-sunken p-px focus-visible:outline-2 focus-visible:outline-focus', className)}>
            <div ref={sheet} className="w-max shadow-raised">
                {nodes}
            </div>
        </div>
    );
}

function PreviewTools({ zoom, onZoom, onFull, onPrint }: { zoom: Zoom; onZoom: (zoom: Zoom) => void; onFull?: () => void; onPrint: () => void }) {
    const { t } = useTranslation();

    return (
        <div className="flex min-w-0 flex-wrap items-center gap-2">
            <ToggleGroup
                aria-label={t('composer.preview')}
                value={[zoom]}
                onValueChange={(value: unknown[]) => {
                    const next = value[0];

                    if (next === 'fit' || next === 'actual') onZoom(next);
                }}
            >
                <ToggleGroupItem value="fit">{t('composer.fit_width.fit')}</ToggleGroupItem>
                <ToggleGroupItem value="actual">{t('composer.fit_width.actual')}</ToggleGroupItem>
            </ToggleGroup>
            {onFull !== undefined && (
                <Button variant="ghost" size="sm" onClick={onFull}>
                    <Icon icon={Expand} size="sm" />
                    {t('composer.full_screen')}
                </Button>
            )}
            <Button variant="ghost" size="sm" onClick={onPrint}>
                <Icon icon={Printer} size="sm" />
                {t('documents.action.print_draft')}
            </Button>
        </div>
    );
}

export function DocumentComposer({ name, paper, form, finalize }: { name: string; paper: Paper; form: ReactNode; finalize: ReactNode }) {
    const { t } = useTranslation();
    const band = useBand();
    const [zoom, setZoom] = useState<Zoom>('fit');
    const [full, setFull] = useState(false);
    const [step, setStep] = useState<Step>('edit');
    const nodes = useMemo(() => paperNodes(renderPaper(paper)), [paper]);
    const label = t('composer.preview_label', { jenis: name });
    const print = () => {
        window.print();
    };
    const tools = <PreviewTools zoom={zoom} onZoom={setZoom} onFull={() => { setFull(true); }} onPrint={print} />;
    const fullScreen = (
        <Dialog open={full} onOpenChange={setFull}>
            <DialogContent phone="full" closeLabel={t('composer.close_full')} className="md:h-[calc(100dvh-var(--space-8))] md:w-[min(100vw-var(--space-8),60rem)]">
                <DialogTitle>{label}</DialogTitle>
                <PreviewTools zoom={zoom} onZoom={setZoom} onPrint={print} />
                <PaperFrame nodes={nodes} zoom={zoom} label={label} className="min-h-0 flex-1" />
            </DialogContent>
        </Dialog>
    );
    // The browser prints this copy alone (resources/css/app.css; §17): the composed paper, marked on every page.
    const printed = createPortal(<div id="document-print">{nodes}</div>, document.body);

    if (band === 'desktop') {
        return (
            <div data-slot="document-composer" data-band="desktop" className="grid min-w-0 grid-cols-[minmax(0,1fr)_var(--composer-preview-narrow)] items-start gap-6 composer:grid-cols-[minmax(0,1fr)_var(--composer-preview)]">
                <div className="flex min-w-0 flex-col gap-6">
                    {form}
                    {finalize}
                </div>
                <div className="sticky top-[calc(var(--header-height)+var(--space-4))] flex max-h-[calc(100dvh-var(--header-height)-var(--space-8))] min-w-0 flex-col gap-3">
                    {tools}
                    <PaperFrame nodes={nodes} zoom={zoom} label={label} className="min-h-0 flex-1" />
                </div>
                {fullScreen}
                {printed}
            </div>
        );
    }

    if (band === 'tablet') {
        return (
            <div data-slot="document-composer" data-band="tablet" className="flex min-w-0 flex-col gap-4">
                <Tabs defaultValue="details">
                    <TabsList>
                        <TabsTab value="details">{t('composer.details')}</TabsTab>
                        <TabsTab value="preview">{t('composer.preview')}</TabsTab>
                    </TabsList>
                    <TabsPanel value="details" className="flex min-w-0 flex-col gap-6 pt-4">
                        {form}
                        {finalize}
                    </TabsPanel>
                    <TabsPanel value="preview" className="flex min-w-0 flex-col gap-3 pt-4">
                        {tools}
                        <PaperFrame nodes={nodes} zoom={zoom} label={label} className="max-h-(--composer-frame-h)" />
                    </TabsPanel>
                </Tabs>
                {fullScreen}
                {printed}
            </div>
        );
    }

    const steps: Step[] = ['edit', 'preview', 'finalize'];

    return (
        <div data-slot="document-composer" data-band="phone" className="flex min-w-0 flex-col gap-4">
            <ol aria-label={t('composer.steps')} className="flex min-w-0 gap-2 text-caption text-text-secondary">
                {steps.map((each, index) => (
                    <li key={each} aria-current={each === step ? 'step' : undefined} className={cn('flex-1 border-t-2 pt-1', each === step ? 'border-primary font-semibold text-text-primary' : 'border-border-subtle')}>
                        {`${String(index + 1)}. ${t(`composer.step.${each}`)}`}
                    </li>
                ))}
            </ol>
            {step === 'edit' && (
                <>
                    {form}
                    <Button onClick={() => { setStep('preview'); }}>{t('composer.preview')}</Button>
                </>
            )}
            {step === 'preview' && (
                <Dialog open onOpenChange={(open) => { if (!open) setStep('edit'); }}>
                    <DialogContent phone="full" closeLabel={t('composer.close_full')}>
                        <DialogTitle>{label}</DialogTitle>
                        <PreviewTools zoom={zoom} onZoom={setZoom} onPrint={print} />
                        <PaperFrame nodes={nodes} zoom={zoom} label={label} className="min-h-0 flex-1" />
                        <div className="grid grid-cols-2 gap-2">
                            <Button variant="outline" onClick={() => { setStep('edit'); }}>{t('composer.back')}</Button>
                            <Button onClick={() => { setStep('finalize'); }}>{t('composer.next')}</Button>
                        </div>
                    </DialogContent>
                </Dialog>
            )}
            {step === 'finalize' && (
                <>
                    {finalize}
                    <Button variant="outline" onClick={() => { setStep('edit'); }}>{t('composer.back')}</Button>
                </>
            )}
            {printed}
        </div>
    );
}
