// IP-16 inside a form (PG-046's proof; PG-050's statement; TASK-051): the file streamed by ROUTE-228 outside any command, then the evidence registered by ROUTE-229 for this company, the form behind it keeping its input (NV-04). A visible
// duplicate is IP-05's warning, an identity already registered named in the answer; the evidence's full handling stays PG-038's.
import { useState } from 'react';

import { FormField, FormSection } from '@/components/form';
import { UploadField } from '@/components/notices';
import { CommandAnswer } from '@/components/parties';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { type TranslationKey, useTranslation } from '@/lib/i18n';
import { useCommand } from '@/lib/use-command';
import { useUpload } from '@/lib/use-upload';

const ACCEPT = 'application/pdf,image/jpeg,image/png,image/webp';

export function ProofSheet({ open, onOpenChange, company, types, today, back, onRegistered }: { open: boolean; onOpenChange: (open: boolean) => void; company: string; types: string[]; today: string; back: string; onRegistered: (evidence: string) => void }) {
    const { t } = useTranslation();
    const upload = useUpload((): Record<string, string> => ({ purpose: 'bukti', company }));
    const [type, setType] = useState(types[0] ?? '');
    const [issuer, setIssuer] = useState('');
    const [reference, setReference] = useState('');
    const [date, setDate] = useState(today);
    const register = useCommand<Record<string, string | boolean | string[] | null>>(() => '/bukti', 'post');

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent width="lg" closeLabel={t('actions.close')}>
                <SheetHeader>
                    <SheetTitle>{t('shell.create.upload')}</SheetTitle>
                    <SheetDescription>{t('evidence.upload_line')}</SheetDescription>
                </SheetHeader>
                <form
                    className="flex min-w-0 flex-col gap-6"
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (upload.file === null) return;

                        register.submit({ file: upload.file, company, pool: false, pool_confirmed: false, type, issuer, reference: reference === '' ? null : reference, date, amount: null, deliverer: null, received: null, acknowledged: [], reason: null, kembali: back }, (state) => {
                            upload.reset();

                            if (typeof state.registered === 'string') onRegistered(state.registered);
                        });
                    }}
                >
                    <FormSection title={t('evidence.section.file')}>
                        <UploadField label={t('glossary.gl_123')} accept={ACCEPT} limits={t('evidence.limits')} state={upload.state} onChoose={upload.choose} onCancel={upload.cancel} onRemove={upload.reset} onRetry={upload.retry} />
                    </FormSection>
                    <FormSection title={t('evidence.section.identity')}>
                        <FormField label={t('evidence.field.type')} required error={register.errors.type}>
                            {(control) => (
                                <NativeSelect {...control} value={type} onChange={(event) => { setType(event.target.value); }}>
                                    {types.map((option) => <option key={option} value={option}>{t(`evidence.type.${option}` as TranslationKey)}</option>)}
                                </NativeSelect>
                            )}
                        </FormField>
                        <FormField label={t('evidence.field.issuer')} required error={register.errors.issuer}>
                            {(control) => <Input {...control} value={issuer} maxLength={200} onChange={(event) => { setIssuer(event.target.value); }} />}
                        </FormField>
                        <FormField label={t('evidence.field.reference')} help={t('evidence.field.reference_help')} error={register.errors.reference}>
                            {(control) => <Input {...control} value={reference} maxLength={120} onChange={(event) => { setReference(event.target.value); }} />}
                        </FormField>
                        <FormField label={t('evidence.field.date')} required error={register.errors.date}>
                            {(control) => <Input {...control} type="date" value={date} onChange={(event) => { setDate(event.target.value); }} />}
                        </FormField>
                    </FormSection>
                    {register.answer !== null && <CommandAnswer command={register} />}
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" onClick={() => { onOpenChange(false); }}>{t('actions.cancel')}</Button>
                        <Button type="submit" pending={register.pending} disabled={upload.file === null}>{t('evidence.action.register')}</Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}
