// The local stack's fixture page of TL-09 and TL-10 (routes/fixtures.php; TASK-014): INT-001's switch beside a
// field, so the browser suite shows the switch keeping the unsent input and the layout (LOCALIZATION §3.7)
// before the sign-in pages exist (TASK-017). Its copy is PG-001's keys.
import { useState } from 'react';

import LanguageSwitch from '@/components/LanguageSwitch';
import { useTranslation } from '@/lib/i18n';

export default function LanguageSwitchFixture() {
    const { t } = useTranslation();
    const [email, setEmail] = useState('');

    return (
        <main className="mx-auto flex max-w-md flex-col gap-6 px-4 py-8">
            <div className="flex justify-end">
                <LanguageSwitch />
            </div>
            <h1 className="text-2xl font-semibold">{t('pages.pg_001.heading')}</h1>
            <label className="flex flex-col gap-2">
                <span>{t('pages.pg_001.email')}</span>
                <input
                    type="email"
                    name="email"
                    value={email}
                    onChange={(event) => {
                        setEmail(event.target.value);
                    }}
                    className="min-h-11 rounded border px-3"
                />
            </label>
            <button type="button" className="min-h-11 rounded border px-4 font-medium">
                {t('pages.pg_001.submit')}
            </button>
        </main>
    );
}
