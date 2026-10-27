// The local stack's stand-ins of TASK-026 (routes/fixtures.php) for PG-013's and PG-017's party fields, before those
// forms exist (TASK-032, TASK-037): each form's own input beside the client or supplier picker, whose "Buat {Objek}"
// creates inline and returns with the new value chosen and the input kept (ROUTE-141; ROUTE-143; IP-19) — so the
// browser suite proves J-MD-02 and J-MD-03 inline. Not shipped in a production build (TL-13).
import { usePage } from '@inertiajs/react';
import { useState } from 'react';

import { FormField, FormSection } from '@/components/form';
import type { LookupResult } from '@/components/lookup';
import { PageHeader } from '@/components/page-header';
import { ClientCreateSheet, type CreatedClient, PartyLookup, SupplierCreateSheet } from '@/components/parties';
import { Input } from '@/components/ui/input';
import AppShell from '@/layouts/AppShell';
import { useTranslation } from '@/lib/i18n';
import type { AccountHints } from '@/lib/navigation';

export default function PartyFormsFixture() {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth: AccountHints }>().props;
    const canCreate = auth.capabilities.includes('parties.manage');
    const [projectNote, setProjectNote] = useState('');
    const [purchaseNote, setPurchaseNote] = useState('');
    const [client, setClient] = useState<LookupResult | null>(null);
    const [created, setCreated] = useState<CreatedClient | null>(null);
    const [supplier, setSupplier] = useState<LookupResult | null>(null);
    const [creatingClient, setCreatingClient] = useState<{ name: string; key: number } | null>(null);
    const [creatingSupplier, setCreatingSupplier] = useState<{ name: string; key: number } | null>(null);
    const chosen = [created?.organization.name, created?.unit?.label, created?.address?.label, created?.contact?.label].filter((part): part is string => part !== undefined);

    return (
        <AppShell title={t('forms.project')}>
            <div className="flex min-w-0 flex-col gap-6">
                <PageHeader title={t('forms.project')} />
                <form className="flex min-w-0 flex-col gap-4" data-testid="project-form" onSubmit={(event) => { event.preventDefault(); }}>
                    <FormSection title={t('forms.project')}>
                        <FormField label={t('forms.more_details')}>
                            {(control) => <Input {...control} value={projectNote} onChange={(event) => { setProjectNote(event.target.value); }} />}
                        </FormField>
                        <PartyLookup type="client" label={t('glossary.gl_048')} value={client} onChoose={(next) => { setClient(next); setCreated(null); }} canCreate={canCreate} onCreate={(name) => { setCreatingClient({ name, key: Date.now() }); }} />
                        <ul role="list" data-testid="client-chosen" className="flex flex-col gap-1">
                            {(chosen.length > 0 ? chosen : client === null ? [] : [client.label]).map((label) => (
                                <li key={label} className="text-body text-text-primary">
                                    {t('parties.inline.chosen', { nama: label })}
                                </li>
                            ))}
                        </ul>
                    </FormSection>
                </form>
                <form className="flex min-w-0 flex-col gap-4" data-testid="purchase-form" onSubmit={(event) => { event.preventDefault(); }}>
                    <FormSection title={t('glossary.gl_006')}>
                        <FormField label={t('forms.more_details')}>
                            {(control) => <Input {...control} value={purchaseNote} onChange={(event) => { setPurchaseNote(event.target.value); }} />}
                        </FormField>
                        <PartyLookup type="supplier" label={t('forms.supplier')} value={supplier} onChoose={setSupplier} canCreate={canCreate} onCreate={(name) => { setCreatingSupplier({ name, key: Date.now() }); }} />
                        <p data-testid="supplier-chosen" className="text-body text-text-primary">
                            {supplier === null ? '' : t('parties.inline.chosen', { nama: supplier.label })}
                        </p>
                    </FormSection>
                </form>
            </div>
            {creatingClient !== null && (
                <ClientCreateSheet
                    key={creatingClient.key}
                    open
                    inline
                    onOpenChange={(open) => { if (!open) setCreatingClient(null); }}
                    name={creatingClient.name}
                    onCreated={(value) => {
                        if (value === null) return;
                        setCreated(value);
                        setClient({ id: value.organization.id, code: '', label: value.organization.name });
                    }}
                />
            )}
            {creatingSupplier !== null && (
                <SupplierCreateSheet
                    key={creatingSupplier.key}
                    open
                    inline
                    onOpenChange={(open) => { if (!open) setCreatingSupplier(null); }}
                    name={creatingSupplier.name}
                    onCreated={(value) => { if (value !== null) setSupplier({ id: value.id, code: '', label: value.name }); }}
                />
            )}
        </AppShell>
    );
}
