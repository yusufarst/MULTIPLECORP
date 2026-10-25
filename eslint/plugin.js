// The project's own ESLint rules and shared settings (TASK-002).
import hardcodedCopy from './rules/hardcoded-copy.js';
import surfaceGrey from './rules/surface-grey.js';
import tokenValues from './rules/token-values.js';

// SECURITY WS-09: React escapes all text; raw HTML is never rendered.
export const rawHtml = {
    selector: "JSXAttribute[name.name='dangerouslySetInnerHTML']",
    message: 'Raw HTML is prohibited (SECURITY WS-09).',
};

// DESIGN_SYSTEM GR-04 and §5.1: each grey only on its own surface — muted on white, on the canvas and on the
// tinted panels, tertiary on a selected lime row (TASK-015).
const TINTS = ['olive', 'blue', 'peach', 'mint', 'red', 'lavender', 'tan', 'lime', 'neutral', 'blue-strong', 'peach-strong'].map((tint) => `tint-${tint}`);

export const surfaceGreys = {
    greys: { 'text-muted': ['surface'], 'text-muted-canvas': ['canvas'], 'text-muted-tint': TINTS, 'text-tertiary-lime': ['lime-150'] },
};

export default {
    meta: { name: 'multiplecorp' },
    rules: {
        'hardcoded-copy': hardcodedCopy,
        'surface-grey': surfaceGrey,
        'token-values': tokenValues,
    },
};
