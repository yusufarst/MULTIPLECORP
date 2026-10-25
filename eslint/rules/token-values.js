/**
 * DESIGN_SYSTEM §5 and SLOP-: the tokens are the only values (TASK-015 acceptance criterion 1). Tailwind's default
 * namespaces are cleared in resources/css/tokens.css, so a utility can name only a token; what is left to check
 * is a class that carries its own value. The rule reports, in a JSX `className` and in the string arguments of
 * `cn()` and `cva()`:
 * - an arbitrary value or variant in brackets that holds a number but no `var(--…)` — `w-[13px]`, `max-[400px]:`,
 *   `bg-[#fff]` —, a token being referenced as `(--name)` or inside the brackets;
 * - a spacing utility — padding, margin, gap, space, inset and scroll spacing — off §5.3's 4 px scale.
 * A `style` prop with a literal value is reported too: a value belongs in a token.
 */

// §5.3's spacing steps in Tailwind's 0.25 rem unit (`--space-1`–`--space-18`), the hairline `px` and the 2 px half step.
export const SPACING_STEPS = new Set(['0', 'px', '0.5', '1', '2', '3', '4', '5', '6', '8', '10', '12', '14', '16', '18']);

const SPACING = /^-?(p|px|py|pt|pr|pb|pl|ps|pe|m|mx|my|mt|mr|mb|ml|ms|me|gap|gap-x|gap-y|space-x|space-y|inset|inset-x|inset-y|top|right|bottom|left|start|end|scroll-m[trblxyse]?|scroll-p[trblxyse]?)-([0-9.]+|px)$/;

function strings(node, found = []) {
    if (!node) {
        return found;
    }

    switch (node.type) {
        case 'Literal':
            if (typeof node.value === 'string') {
                found.push({ node, text: node.value });
            }
            break;
        case 'TemplateLiteral':
            found.push({ node, text: node.quasis.map((quasi) => quasi.value.cooked ?? '').join(' ') });
            break;
        case 'JSXExpressionContainer':
            strings(node.expression, found);
            break;
        case 'ConditionalExpression':
            strings(node.consequent, found);
            strings(node.alternate, found);
            break;
        case 'LogicalExpression':
            strings(node.left, found);
            strings(node.right, found);
            break;
        case 'ArrayExpression':
            node.elements.forEach((element) => strings(element, found));
            break;
        case 'ObjectExpression':
            node.properties.forEach((property) => property.type === 'Property' && strings(property.value, found));
            break;
        case 'CallExpression':
            node.arguments.forEach((argument) => strings(argument, found));
            break;
        default:
            break;
    }

    return found;
}

/** The problem with one class, or null. */
export function problem(token) {
    for (const [, inside] of token.matchAll(/\[([^\]]*(?:\[[^\]]*\][^\]]*)*)\]/g)) {
        if ((/\d/.test(inside) || /#[0-9a-f]{3,8}\b/i.test(inside)) && !inside.includes('var(--')) {
            return 'arbitrary';
        }
    }

    const utility = token.slice(token.lastIndexOf(':') + 1).replace(/^!|!$/g, '');
    const spacing = SPACING.exec(utility);

    if (spacing && !SPACING_STEPS.has(spacing[2])) {
        return 'spacing';
    }

    return null;
}

export default {
    meta: {
        type: 'problem',
        docs: { description: 'The design tokens are the only values (DESIGN_SYSTEM §5; SLOP-).' },
        schema: [],
        messages: {
            arbitrary: 'DESIGN_SYSTEM §5: `{{token}}` carries its own value; use a token, or reference one with var(--…).',
            spacing: 'DESIGN_SYSTEM §5.3: `{{token}}` is off the spacing scale (0, 0.5, 1–6, 8, 10, 12, 14, 16, 18).',
            style: 'DESIGN_SYSTEM §5: a literal style value; put it in a token and use its class.',
        },
    },

    create(context) {
        const check = (nodes) => {
            for (const { node, text } of nodes) {
                for (const token of text.split(/\s+/).filter(Boolean)) {
                    const messageId = problem(token);

                    if (messageId !== null) {
                        context.report({ node, messageId, data: { token } });
                    }
                }
            }
        };

        return {
            JSXAttribute(node) {
                const name = node.name.type === 'JSXIdentifier' ? node.name.name : null;

                if (name === 'className' || name === 'frameClassName') {
                    check(strings(node.value));
                }

                if (name === 'style' && node.value?.type === 'JSXExpressionContainer' && node.value.expression.type === 'ObjectExpression') {
                    const literal = node.value.expression.properties.some((property) => property.type === 'Property' && property.value.type === 'Literal');

                    if (literal) {
                        context.report({ node, messageId: 'style' });
                    }
                }
            },
            CallExpression(node) {
                if (node.callee.type === 'Identifier' && (node.callee.name === 'cn' || node.callee.name === 'cva')) {
                    // A JSX className's own call is checked through its attribute.
                    if (node.parent?.type !== 'JSXExpressionContainer' || node.parent.parent?.type !== 'JSXAttribute') {
                        check(strings(node));
                    }
                }
            },
        };
    },
};
