// The paper as React elements (SECURITY WS-09; SM-10): the layout template's HTML — every value already escaped by
// the template — parsed in an inert document and rebuilt through an allowlist of the elements and attributes the
// templates write, so no raw HTML ever reaches the page: an element outside the list is dropped with its content, an
// attribute outside it is dropped, and an image's address is only an identity asset's own download route (PJ-19).
import { createElement, type ReactNode } from 'react';

const ELEMENTS = new Set(['div', 'p', 'span', 'strong', 'em', 'h1', 'h2', 'header', 'section', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'img', 'br']);

const ATTRIBUTES: Record<string, string> = { class: 'className', alt: 'alt', 'aria-hidden': 'aria-hidden', 'data-layout': 'data-layout' };

/** Elements whose whitespace-only text is no content: a table's rows and cells only. */
const TABULAR = new Set(['table', 'thead', 'tbody', 'tr']);

/** An identity asset through ROUTE-226, the only image a draft's paper shows. */
const ASSET =/^\/berkas\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\/unduh$/;

function convert(node: Node, key: number, parent: string): ReactNode {
    if (node.nodeType === Node.TEXT_NODE) {
        return TABULAR.has(parent) && (node.textContent ?? '').trim() === '' ? null : node.textContent;
    }

    if (!(node instanceof Element)) {
        return null;
    }

    const tag = node.tagName.toLowerCase();

    if (!ELEMENTS.has(tag)) {
        return null;
    }

    const props: Record<string, string | number> = { key };

    for (const attribute of Array.from(node.attributes)) {
        const name = ATTRIBUTES[attribute.name];

        if (name !== undefined) {
            props[name] = attribute.value;
        } else if (attribute.name === 'src' && tag === 'img' && ASSET.test(attribute.value)) {
            props.src = attribute.value;
        }
    }

    if (tag === 'img') {
        return props.src === undefined ? null : createElement('img', props);
    }

    return createElement(tag, props, ...Array.from(node.childNodes).map((child, index) => convert(child, index, tag)));
}

/** The paper's HTML as React elements through the allowlist. */
export function paperNodes(html: string): ReactNode {
    const parsed = new DOMParser().parseFromString(`<!doctype html><body>${html}</body>`, 'text/html');

    return Array.from(parsed.body.childNodes).map((child, index) => convert(child, index, 'body'));
}
