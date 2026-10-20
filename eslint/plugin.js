// The project's own ESLint rules and shared settings (TASK-002).
import surfaceGrey from './rules/surface-grey.js';

// SECURITY WS-09: React escapes all text; raw HTML is never rendered.
export const rawHtml = {
    selector: "JSXAttribute[name.name='dangerouslySetInnerHTML']",
    message: 'Raw HTML is prohibited (SECURITY WS-09).',
};

// DESIGN_SYSTEM GR-04: each grey only on its own surface. TASK-015 adds the tokens and the greys of
// tinted panels and lime rows with the surfaces it gives them.
export const surfaceGreys = { greys: { 'text-muted': ['surface'], 'text-muted-canvas': ['canvas'] } };

export default {
    meta: { name: 'multiplecorp' },
    rules: {
        'surface-grey': surfaceGrey,
    },
};
