import { ScanBarcode, Search } from 'lucide-react';
import { type ReactNode, type RefObject, useEffect, useId, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Icon } from '@/components/ui/icon';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { useTranslation } from '@/lib/i18n';
import { acceptsScans, BurstDetector, SCAN_TARGET } from '@/lib/scanner';
import { cn } from '@/lib/utils';

// PT-20's scan field (DESIGN_SYSTEM §9.3, §13.2; ADMIN_FLOW IP-15): the input group with the scan icon, the field
// "Pindai atau ketik kode", "Siap Memindai" as plain muted text, "Cari Produk" beside it and the last result line
// below; Enter looks the code up exactly — typed or scanned —, focus stays in the field. The result line is a
// polite live region; a scan error is announced assertively. On the phone the parts take two lines in a dock
// fixed above the action bar (D-DS-08), nothing cut or scrolling sideways at 320 px in both languages.
export function ScanField({
    value,
    onValueChange,
    onLookup,
    onSearch,
    result,
    error,
    dock = false,
    inputRef,
}: {
    value: string;
    onValueChange: (value: string) => void;
    /** The exact lookup of the entered or scanned code (IP-15); the field is cleared and keeps focus. */
    onLookup: (code: string) => void;
    onSearch?: () => void;
    result?: ReactNode;
    error?: string | null;
    dock?: boolean;
    inputRef?: RefObject<HTMLInputElement | null>;
}) {
    const { t } = useTranslation();
    const id = useId();
    const own = useRef<HTMLInputElement>(null);
    const ref = inputRef ?? own;

    return (
        <div
            data-slot="scan-field"
            className={cn(
                'flex min-w-0 flex-col gap-2 bg-surface',
                dock && 'max-md:fixed max-md:inset-x-0 max-md:bottom-[calc(var(--m-action-bar-h)+var(--safe-bottom))] max-md:z-(--z-bar) max-md:border-t max-md:border-border max-md:px-m-gutter max-md:py-3',
            )}
        >
            <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 md:hidden">
                <ReadyText />
                <ResultLine result={result} error={error} />
            </div>
            <div className="flex min-w-0 items-center gap-2">
                <InputGroup className="flex-1 max-md:h-floor">
                    <InputGroupAddon divider>
                        <Icon icon={ScanBarcode} size="row" />
                        <span className="text-label whitespace-nowrap text-text-tertiary max-md:hidden">{t('pt20.ready')}</span>
                    </InputGroupAddon>
                    <InputGroupInput
                        ref={ref}
                        id={id}
                        {...{ [SCAN_TARGET]: 'scan-field' }}
                        aria-label={t('pt20.placeholder')}
                        aria-invalid={error !== undefined && error !== null ? true : undefined}
                        placeholder={t('pt20.placeholder')}
                        autoComplete="off"
                        autoCorrect="off"
                        autoCapitalize="off"
                        spellCheck={false}
                        enterKeyHint="search"
                        maxLength={100}
                        data-code
                        value={value}
                        onChange={(event) => {
                            onValueChange(event.target.value);
                        }}
                        onKeyDown={(event) => {
                            if (event.key !== 'Enter') {
                                return;
                            }

                            event.preventDefault();
                            const code = value.trim();

                            if (code !== '') {
                                onLookup(code);
                                onValueChange('');
                            }

                            ref.current?.focus();
                        }}
                    />
                </InputGroup>
                {onSearch !== undefined && (
                    <Button variant="outline" size="md" className="max-md:h-floor" onClick={onSearch}>
                        <Icon icon={Search} size="sm" />
                        <span>{t('pt20.search')}</span>
                    </Button>
                )}
            </div>
            <div className="max-md:hidden">
                <ResultLine result={result} error={error} />
            </div>
        </div>
    );
}

function ReadyText() {
    const { t } = useTranslation();

    return (
        <span className="flex items-center gap-2 text-label text-text-tertiary">
            <Icon icon={ScanBarcode} size="sm" />
            {t('pt20.ready')}
        </span>
    );
}

function ResultLine({ result, error }: { result?: ReactNode; error?: string | null }) {
    return (
        <>
            <p role="status" aria-live="polite" className="min-w-0 text-body text-text-secondary empty:hidden">
                {error === undefined || error === null ? result : null}
            </p>
            <p role="alert" className="min-w-0 text-body font-medium text-danger-fg empty:hidden">
                {error}
            </p>
        </>
    );
}

/**
 * §13.2's guard for a burst that lands outside a field that accepts scans: its Enter is discarded, a field gets
 * its previous value back and the notice names it; elsewhere nothing is pressed. Returns the notice to announce.
 */
export function useScanGuard({ hasScanField }: { hasScanField: boolean }): { notice: string | null; clear: () => void } {
    const { t } = useTranslation();
    const [notice, setNotice] = useState<string | null>(null);
    const detector = useRef(new BurstDetector());
    const snapshot = useRef<{ element: HTMLInputElement | HTMLTextAreaElement; value: string } | null>(null);
    const strings = useRef({ t, hasScanField });

    useEffect(() => {
        strings.current = { t, hasScanField };
    });

    useEffect(() => {
        const listener = (event: KeyboardEvent) => {
            const target = document.activeElement;

            if (acceptsScans(target) || event.ctrlKey || event.metaKey || event.altKey) {
                detector.current.reset();

                return;
            }

            if (!detector.current.continues(event.timeStamp) && event.key.length === 1) {
                snapshot.current =
                    target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement ? { element: target, value: target.value } : null;
            }

            const burst = detector.current.feed(event.key, event.timeStamp);

            if (burst === null) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            const { t: translate, hasScanField: scanning } = strings.current;
            const field = snapshot.current;

            if (field !== null && field.element === target) {
                restore(field.element, field.value);
                setNotice(translate('scan.into_field', { kolom: fieldName(field.element) }));
            } else {
                setNotice(translate(scanning ? 'scan.ignored_with_field' : 'scan.ignored'));
            }

            snapshot.current = null;
        };

        document.addEventListener('keydown', listener, true);

        return () => {
            document.removeEventListener('keydown', listener, true);
        };
    }, []);

    return { notice, clear: () => {
        setNotice(null);
    } };
}

/** The field's name as its label shows it, without the "(wajib)" mark. */
function fieldName(element: HTMLInputElement | HTMLTextAreaElement): string {
    const label = element.labels?.[0];

    if (label === undefined) {
        return element.getAttribute('aria-label') ?? '';
    }

    const copy = label.cloneNode(true) as HTMLElement;
    copy.querySelectorAll('[data-label-extra]').forEach((extra) => {
        extra.remove();
    });

    return copy.textContent.trim();
}

/** Puts a field's value back through the native setter so React's controlled state follows. */
function restore(element: HTMLInputElement | HTMLTextAreaElement, value: string): void {
    const prototype = element instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(prototype, 'value')?.set?.call(element, value);
    element.dispatchEvent(new Event('input', { bubbles: true }));
}

/** The assertive notice of a discarded scan (§13.2; §15). */
export function ScanNotice({ notice }: { notice: string | null }) {
    return (
        <p role="alert" className="text-body font-medium text-text-primary empty:hidden">
            {notice}
        </p>
    );
}
