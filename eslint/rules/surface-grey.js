/**
 * DESIGN_SYSTEM Annex B GR-04: each muted grey is used only on the surface it was measured on.
 *
 * Options map a grey token to its surfaces, by the token names of DESIGN_SYSTEM §5.1 without
 * `--color-`, e.g. { greys: { 'text-muted-canvas': ['canvas'] } }. The rule reads the classes of
 * each JSX element — `text-<grey>` and `bg-<surface>`, with or without variants, or as
 * `[var(--color-…)]` — and reports a grey on a surface the options do not give it, the surface
 * being the element's own or its nearest JSX ancestor's.
 */

const VARIABLE = /^(text|bg)-\[var\(--color-([a-z0-9-]+)\)\]$/;

function classTokens(node, tokens = []) {
    if (!node) {
        return tokens;
    }

    switch (node.type) {
        case 'Literal':
            if (typeof node.value === 'string') {
                tokens.push(...node.value.split(/\s+/).filter(Boolean));
            }
            break;
        case 'TemplateLiteral':
            for (const quasi of node.quasis) {
                tokens.push(...quasi.value.cooked.split(/\s+/).filter(Boolean));
            }
            break;
        case 'JSXExpressionContainer':
            classTokens(node.expression, tokens);
            break;
        case 'CallExpression':
            node.arguments.forEach((argument) => classTokens(argument, tokens));
            break;
        case 'ConditionalExpression':
            classTokens(node.consequent, tokens);
            classTokens(node.alternate, tokens);
            break;
        case 'LogicalExpression':
            classTokens(node.right, tokens);
            break;
        case 'ArrayExpression':
            node.elements.forEach((element) => classTokens(element, tokens));
            break;
        default:
            break;
    }

    return tokens;
}

function parse(token) {
    const utility = token.slice(token.lastIndexOf(':') + 1).replace(/^!/, '');
    const variable = VARIABLE.exec(utility);

    if (variable) {
        return { kind: variable[1], name: variable[2] };
    }

    if (utility.startsWith('text-')) {
        return { kind: 'text', name: utility.slice('text-'.length) };
    }

    if (utility.startsWith('bg-')) {
        return { kind: 'bg', name: utility.slice('bg-'.length) };
    }

    return null;
}

function classesOf(element) {
    const attribute = element.openingElement.attributes.find(
        (item) => item.type === 'JSXAttribute' && item.name.name === 'className',
    );

    return attribute ? classTokens(attribute.value).map(parse).filter(Boolean) : [];
}

export default {
    meta: {
        type: 'problem',
        docs: { description: 'Each muted grey only on its own surface (DESIGN_SYSTEM GR-04).' },
        schema: [
            {
                type: 'object',
                properties: {
                    greys: {
                        type: 'object',
                        additionalProperties: { type: 'array', items: { type: 'string' } },
                    },
                },
                additionalProperties: false,
            },
        ],
        messages: {
            wrongSurface: 'GR-04: the grey `{{grey}}` is measured on {{allowed}}, not on `{{surface}}`.',
        },
    },

    create(context) {
        const greys = context.options[0]?.greys ?? {};
        const surfaces = new Set(Object.values(greys).flat());

        function surfaceOf(element) {
            for (let node = element; node; node = node.parent) {
                if (node.type !== 'JSXElement') {
                    continue;
                }

                const surface = classesOf(node).find((item) => item.kind === 'bg' && surfaces.has(item.name));

                if (surface) {
                    return surface.name;
                }
            }

            return null;
        }

        return {
            JSXElement(element) {
                for (const item of classesOf(element)) {
                    const allowed = item.kind === 'text' ? greys[item.name] : undefined;

                    if (!allowed) {
                        continue;
                    }

                    const surface = surfaceOf(element);

                    if (surface && !allowed.includes(surface)) {
                        context.report({
                            node: element.openingElement,
                            messageId: 'wrongSurface',
                            data: {
                                grey: item.name,
                                allowed: allowed.map((name) => `\`${name}\``).join(' or '),
                                surface,
                            },
                        });
                    }
                }
            },
        };
    },
};
