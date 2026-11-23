import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

import LanguageSwitch from '@/components/LanguageSwitch';
import { APPLICATION, documentTitle } from '@/lib/title';

// APPR-042's pilot (CR-002 K-5): the sign-in pages' one frame — the wordmark and, before sign-in, the language switch
// above a centred 400 px column; on the desktop the column is a card on the canvas, on the phone the canvas itself. The
// frame holds no state: each page keeps its own authentication behaviour, unchanged (AGENTS.md rule 16).
export default function AuthLayout({
    title,
    heading,
    caption,
    languageSwitch = true,
    head,
    children,
}: {
    /** The screen's name for the document title (H7-10). */
    title: string;
    heading: ReactNode;
    /** A line above the heading, such as a step's place in a sequence. */
    caption?: ReactNode;
    languageSwitch?: boolean;
    head?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-dvh flex-col bg-canvas">
            <Head title={documentTitle(title)}>{head}</Head>
            <header className="mx-auto flex w-full max-w-auth items-center justify-between gap-4 px-m-gutter pt-6 md:px-0 md:pt-10">
                <span className="text-heading-sm font-semibold tracking-tight text-text-primary">{APPLICATION}</span>
                {languageSwitch && <LanguageSwitch />}
            </header>
            <main id="konten" className="mx-auto flex w-full max-w-auth flex-1 flex-col px-m-gutter pt-8 pb-10 md:justify-center md:px-0 md:pt-6">
                <div className="flex flex-col gap-6 md:rounded-xl md:border md:border-border md:bg-surface md:p-8 md:shadow-card">
                    <div className="flex flex-col gap-1">
                        {caption !== undefined && <p className="text-caption font-medium text-text-tertiary">{caption}</p>}
                        <h1 className="text-m-title font-semibold tracking-tight text-text-primary md:text-title">{heading}</h1>
                    </div>
                    {children}
                </div>
            </main>
        </div>
    );
}
