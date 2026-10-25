import { ChevronDown } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Icon } from '@/components/ui/icon';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useTranslation } from '@/lib/i18n';
import { cn } from '@/lib/utils';

// PT-04 (DESIGN_SYSTEM §9.1; INFORMATION_ARCHITECTURE §7): the company code before a record's number; the relation
// marker "perusahaan lain" — muted plain text, never a link, colour, logo or count —; the working filter
// "Tampilkan: Semua perusahaan", a popover with a checklist of the companies in scope. Each displays the scope the
// server sends and never computes it; the view is a page control kept in the address, never in storage (D-IA-02).
export function CompanyTag({ code, className }: { code: string; className?: string }) {
    return (
        <Badge tone="outline" size="sm" data-code className={cn('font-semibold', className)}>
            {code}
        </Badge>
    );
}

export function RelationMarker({ className }: { className?: string }) {
    const { t } = useTranslation();

    return <span className={cn('text-label text-text-tertiary', className)}>{t('company.other')}</span>;
}

export type CompanyOption = { code: string; name: string };

export function CompanyFilter({ companies, selected, onChange }: { companies: CompanyOption[]; selected: string[]; onChange: (codes: string[]) => void }) {
    const { t } = useTranslation();
    const all = selected.length === 0 || selected.length === companies.length;
    const value = all ? t('company.all') : selected.join(', ');

    return (
        <Popover>
            <PopoverTrigger render={<Button variant="outline" size="md" className="max-w-full justify-between font-medium" />}>
                <span className="min-w-0 truncate">
                    <span className="text-text-secondary">{t('company.show')}: </span>
                    {value}
                </span>
                <Icon icon={ChevronDown} size="sm" className="text-text-tertiary" />
            </PopoverTrigger>
            <PopoverContent className="w-72">
                <ul role="list" className="flex flex-col">
                    {companies.map((company) => {
                        const checked = all || selected.includes(company.code);
                        const id = `company-filter-${company.code}`;

                        return (
                            <li key={company.code} className="flex min-h-select-row items-center gap-3 rounded-sm px-2 hover:bg-row-hover">
                                <Checkbox
                                    id={id}
                                    checked={checked}
                                    onCheckedChange={(next) => {
                                        const current = all ? companies.map((c) => c.code) : selected;
                                        const codes = next ? [...new Set([...current, company.code])] : current.filter((c) => c !== company.code);
                                        onChange(codes.length === companies.length ? [] : codes);
                                    }}
                                />
                                <label htmlFor={id} className="flex min-w-0 flex-1 items-center gap-2 text-body">
                                    <CompanyTag code={company.code} />
                                    <span className="min-w-0 truncate">{company.name}</span>
                                </label>
                            </li>
                        );
                    })}
                </ul>
            </PopoverContent>
        </Popover>
    );
}
