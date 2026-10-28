import { DateTimeText } from '@/components/values';
import { type TranslationKey, useTranslation } from '@/lib/i18n';

const relationName = (relation: string): TranslationKey => `timeline.relation.${relation.toLowerCase()}` as TranslationKey;

/** One correction a record's trail names (OPS CorrectionTrail): the relation, the other record, its reason, actor and time. */
export type CorrectionLink = { relation: string; type: string; id: string; reason: string | null; by: string | null; at: string };

// The correction's presentation every record page uses (ADMIN_FLOW §11; BR-CR-02; TASK-031): "Mengoreksi" — the
// originals this record corrects — and "Dikoreksi oleh" — the records that correct it —, each with its reason, actor and
// time; both records stay visible, and nothing here offers deleting a committed fact. The page passes the href of each
// linked record it may open; one it may not open is named without a link.
export function CorrectionTrail({ corrects, correctedBy, hrefOf }: { corrects: CorrectionLink[]; correctedBy: CorrectionLink[]; hrefOf: (link: CorrectionLink) => string | null }) {
    const { t } = useTranslation();

    if (corrects.length === 0 && correctedBy.length === 0) return null;

    const list = (title: string, links: CorrectionLink[]) =>
        links.length === 0 ? null : (
            <div className="flex min-w-0 flex-col gap-1">
                <p className="text-body font-medium text-text-primary">{title}</p>
                <ul className="flex min-w-0 flex-col gap-1 text-body text-text-secondary">
                    {links.map((link) => {
                        const href = hrefOf(link);

                        return (
                            <li key={`${link.relation}-${link.id}-${link.at}`} className="flex min-w-0 flex-wrap gap-x-2">
                                {href === null ? <span>{t(relationName(link.relation))}</span> : <a href={href} className="text-accent-olive underline-offset-2 hover:underline">{t(relationName(link.relation))}</a>}
                                {link.reason !== null && <span>{link.reason}</span>}
                                {link.by !== null && <span>{link.by}</span>}
                                <DateTimeText value={link.at} />
                            </li>
                        );
                    })}
                </ul>
            </div>
        );

    return (
        <div data-slot="correction-trail" className="flex min-w-0 flex-col gap-3">
            {list(t('timeline.corrects'), corrects)}
            {list(t('timeline.corrected_by'), correctedBy)}
        </div>
    );
}
