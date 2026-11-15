"""TASK-065's composer tolerance (TEST_STRATEGY §9; DESIGN_SYSTEM §14.3; D-DS-23): an issued paper laid out by the
renderer's own engine — WeasyPrint, as resources/renderer/render.py runs it, with the same bundled assets — and each
block's border box written to standard output as JSON, in millimetres from its page's top-left corner, so the browser's
draft preview can be compared with the issued layout within 1 mm. Test support only: it reads one HTML document from
standard input and the files of the asset directory given as its argument, nothing else.
"""

import json
import sys
from pathlib import Path
from urllib.parse import unquote, urlsplit

MM = 25.4 / 96


def main() -> int:
    assets = Path(sys.argv[1]).resolve(strict=True)

    from weasyprint import HTML
    from weasyprint.urls import FatalURLFetchingError, URLFetcher, URLFetcherResponse

    class AssetsOnly(URLFetcher):
        def fetch(self, url, headers=None):
            parts = urlsplit(url)
            if parts.scheme.lower() == 'data':
                return super().fetch(url, headers)
            path = Path(unquote(parts.path)).resolve(strict=False)
            if parts.scheme.lower() != 'file' or assets not in path.parents or not path.is_file():
                raise FatalURLFetchingError('refused: a resource outside the bundled assets')
            return URLFetcherResponse(url, path.read_bytes(), {})

    html = sys.stdin.buffer.read().decode('utf-8')
    document = HTML(string=html, base_url=assets.as_uri() + '/', url_fetcher=AssetsOnly()).render()
    blocks = []
    seen = set()

    def walk(box, page):
        element = getattr(box, 'element', None)
        children = getattr(box, 'children', [])
        # A table's wrapper block carries the table's element: its table box, the browser's table rectangle, is taken.
        wrapper = bool(children) and type(children[0]).__name__ == 'TableBox' and getattr(children[0], 'element', None) is element
        if element is not None and not wrapper and id(element) not in seen:
            seen.add(id(element))
            classes = (element.get('class') or '').split()
            key = next((name for name in classes if name.startswith('paper-')), 'tr' if getattr(box, 'element_tag', '') == 'tr' else None)
            if key is not None and 'paper' not in classes:
                blocks.append({
                    'key': key,
                    'page': page,
                    'x': round(box.border_box_x() * MM, 2),
                    'y': round(box.border_box_y() * MM, 2),
                    'w': round(box.border_width() * MM, 2),
                    'h': round(box.border_height() * MM, 2),
                })
        for child in children:
            walk(child, page)

    for number, page in enumerate(document.pages, start=1):
        walk(page._page_box, number)

    first = document.pages[0]
    json.dump({'pages': len(document.pages), 'width': round(first.width * MM, 2), 'height': round(first.height * MM, 2), 'blocks': blocks}, sys.stdout)
    return 0


if __name__ == '__main__':
    sys.exit(main())
