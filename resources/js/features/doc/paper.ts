// The draft preview's producer (DOCUMENT_PREVIEW_PRODUCER option A, normative in DESIGN_SYSTEM §14.3): the layout's one
// template — resources/documents/DOC-nn.mustache with its partials and paper.css, the files TASK-022's renderer reads
// for the issued rendition — composed in the browser from the page's props, every value escaped. Nothing is written:
// no rendition, file, number, counter or snapshot (D-DS-20; GAP-053).
import Mustache from 'mustache';

import '../../../documents/paper.css';

const sources = import.meta.glob<string>('../../../documents/**/*.mustache', { query: '?raw', import: 'default', eager: true });

const layouts: Record<string, string> = {};
const partials: Record<string, string> = {};

for (const [path, source] of Object.entries(sources)) {
    const name = /([^/]+)\.mustache$/.exec(path)?.[1] ?? '';

    if (path.includes('/partials/')) {
        partials[name] = source;
    } else {
        layouts[name] = source;
    }
}

/** A paper as the server composes it (app/Modules/DOC/Services/DocumentPaper.php) — the template's one view. */
export type Paper = Record<string, unknown> & { layout: string; draft: boolean; date: string };

/** The layout's paper as HTML; its values escaped by the template, so no value becomes markup (SM-10). */
export function renderPaper(paper: Paper): string {
    const template = layouts[paper.layout];

    if (template === undefined) {
        throw new RangeError(`No template is written for the layout ${paper.layout}.`);
    }

    return Mustache.render(template, paper, partials);
}

export function hasLayout(layout: string): boolean {
    return layouts[layout] !== undefined;
}
