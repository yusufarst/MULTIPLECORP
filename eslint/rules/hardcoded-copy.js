/**
 * LOCALIZATION §3.6 and CAP-14: no component holds a hardcoded user-facing string — every string is a key of the
 * catalogue with both texts, resolved through `t()` (TASK-014). The rule reports, wherever it holds a letter:
 * JSX text; a string or template literal rendered as a JSX child; and the value of an attribute a person reads
 * or hears — `aria-label`, `title`, `placeholder`, `alt` and their kin. Punctuation and symbols alone, such as
 * the switch's separator, are not copy.
 */

const LETTER = /\p{L}/u;

const READ_ATTRIBUTES = new Set([
    'alt',
    'aria-description',
    'aria-label',
    'aria-placeholder',
    'aria-roledescription',
    'aria-valuetext',
    'label',
    'placeholder',
    'title',
]);

function literalText(node) {
    if (!node) {
        return null;
    }

    if (node.type === 'Literal' && typeof node.value === 'string') {
        return node.value;
    }

    if (node.type === 'TemplateLiteral') {
        return node.quasis.map((quasi) => quasi.value.cooked ?? '').join('');
    }

    if (node.type === 'JSXExpressionContainer') {
        return literalText(node.expression);
    }

    return null;
}

export default {
    meta: {
        type: 'problem',
        docs: { description: 'User-facing copy is a key of the catalogue, never written in a component (LOCALIZATION §3.6).' },
        schema: [],
        messages: {
            hardcoded: 'User-facing copy is a key of the catalogue resolved through t(), never written in a component (LOCALIZATION §3.6; CAP-14).',
        },
    },
    create(context) {
        const report = (node, text) => {
            if (text !== null && LETTER.test(text)) {
                context.report({ node, messageId: 'hardcoded' });
            }
        };

        return {
            JSXText(node) {
                report(node, node.value);
            },
            JSXExpressionContainer(node) {
                if (node.parent?.type === 'JSXElement' || node.parent?.type === 'JSXFragment') {
                    report(node, literalText(node.expression));
                }
            },
            JSXAttribute(node) {
                const name = node.name.type === 'JSXIdentifier' ? node.name.name : null;

                if (name !== null && READ_ATTRIBUTES.has(name)) {
                    report(node, literalText(node.value));
                }
            },
        };
    },
};
