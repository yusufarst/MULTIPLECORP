// One command's form in a sheet (TASK-027; TASK-028; PT-09; IP-03; MSG-35), shared by the master pages: its fields,
// its answer in place, "Batal" and every dismissal asking first with unsent input; it keeps the values and version it
// opened with, and only "Muat Versi Terbaru" takes the record's latest after a stale answer.
import { type ReactNode, useState } from 'react';

import { CommandAnswer } from '@/components/parties';
import { UnsentInputDialog } from '@/components/session-dialogs';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';

export type Payload = Record<string, string | number | boolean | null>;
export type Values = Record<string, string>;

/** One form in a sheet: its fields, its answer in place, "Batal" and every dismissal asking first with unsent input
    (PT-09; MSG-35). It keeps the values and version it opened with: a stale answer leaves the input in place with MSG-03,
    and only "Muat Versi Terbaru" takes the record's latest values and version — never a silent resend over another
    user's change (IP-03; ST-01). */
export function FormSheet({
    title,
    url,
    method,
    initial,
    fixed,
    submitLabel,
    onClose,
    children,
}: {
    title: string;
    url: string;
    method: 'post' | 'put';
    initial: Values;
    fixed: Payload;
    submitLabel: string;
    onClose: () => void;
    children: (values: Values, set: (field: string, value: string) => void, errors: Record<string, string>) => ReactNode;
}) {
    const { t } = useTranslation();
    const [opened, setOpened] = useState({ initial, fixed });
    const [values, setValues] = useState<Values>(initial);
    const [leaving, setLeaving] = useState(false);
    const command = useCommand<Payload>(() => url, method);
    const dirty = JSON.stringify(values) !== JSON.stringify(opened.initial);
    const stale = command.answer?.kind === 'CONFLICT' && command.answer.cause === 'stale';

    return (
        <Sheet open onOpenChange={(open) => { if (!open) { if (dirty) setLeaving(true); else onClose(); } }}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{title}</SheetTitle>
                </SheetHeader>
                <form
                    className="flex min-w-0 flex-col gap-6"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        command.submit({ ...opened.fixed, ...values }, onClose);
                    }}
                >
                    {children(values, (field, value) => { setValues((current) => ({ ...current, [field]: value })); }, command.errors)}
                    <CommandAnswer command={command} />
                    {stale && (
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setOpened({ initial, fixed });
                                    setValues(initial);
                                    command.clear();
                                }}
                            >
                                {t('forms.load_latest')}
                            </Button>
                        </div>
                    )}
                    <div className="flex justify-end gap-3">
                        <Button type="button" variant="ghost" onClick={() => { if (dirty) setLeaving(true); else onClose(); }}>
                            {t('actions.cancel')}
                        </Button>
                        <Button type="submit" pending={command.pending} disabled={command.frozen}>
                            {submitLabel}
                        </Button>
                    </div>
                    <UnsentInputDialog open={leaving} kind="leave" onStay={() => { setLeaving(false); }} onLeave={onClose} />
                </form>
            </SheetContent>
        </Sheet>
    );
}
