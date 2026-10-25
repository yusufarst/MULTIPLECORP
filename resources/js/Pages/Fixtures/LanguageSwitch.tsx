// The local stack's fixture page of TL-09 and TL-10 (routes/fixtures.php; TASK-014): INT-001's switch beside a
// field, so the browser suite shows the switch keeping the unsent input and the layout (LOCALIZATION §3.7)
// before the sign-in pages exist (TASK-017). Its copy is PG-001's keys.
import { useState } from 'react';

import LanguageSwitch from '@/components/LanguageSwitch';
import { Button } from '@/components/ui/button';
import { Field, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/lib/i18n';

export default function LanguageSwitchFixture() {
    const { t } = useTranslation();
    const [email, setEmail] = useState('');

    return (
        <main className="mx-auto flex w-full max-w-sheet-sm flex-col gap-6 px-m-gutter py-8">
            <div className="flex justify-end">
                <LanguageSwitch />
            </div>
            <h1 className="text-m-title font-semibold text-text-primary md:text-title">{t('pages.pg_001.heading')}</h1>
            <Field>
                <FieldLabel htmlFor="email">{t('pages.pg_001.email')}</FieldLabel>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    value={email}
                    onChange={(event) => {
                        setEmail(event.target.value);
                    }}
                />
            </Field>
            <Button variant="primary" size="lg">
                {t('pages.pg_001.submit')}
            </Button>
        </main>
    );
}
