"""The document renderer of SECURITY FL-08 (D-SEC-11; DIR-099 C1), started by the application as an external
process with an argument array, never a shell string.

It reads one HTML document — an issued payload already escaped by its template — from standard input and
writes its PDF to standard output. WeasyPrint has no JavaScript engine. Every resource the document names goes
through this fetcher: a file inside one of the allowlisted directories given as arguments, or a `data:` URL;
anything else — the network, any other file — stops the rendering, so no SSRF and no file read outside the
assets is possible. The address space is limited before WeasyPrint is imported; the caller limits the time.
"""

import os
import resource
import sys
from pathlib import Path
from urllib.parse import unquote, urlsplit


def main() -> int:
    allowed = [Path(argument).resolve(strict=True) for argument in sys.argv[1:]]
    if not allowed:
        print('no asset directory given', file=sys.stderr)
        return 2

    limit = int(os.environ.get('RENDERER_MEMORY_BYTES', '0'))
    if limit > 0:
        resource.setrlimit(resource.RLIMIT_AS, (limit, limit))

    from weasyprint import HTML
    from weasyprint.urls import FatalURLFetchingError, URLFetcher, URLFetcherResponse

    class Refused(FatalURLFetchingError):
        pass

    class AssetsOnly(URLFetcher):
        def fetch(self, url, headers=None):
            scheme = urlsplit(url).scheme.lower()
            if scheme == 'data':
                return super().fetch(url, headers)
            if scheme != 'file':
                raise Refused(f'refused: {scheme or "relative"} resource')
            path = Path(unquote(urlsplit(url).path)).resolve(strict=True)
            if not any(path == root or root in path.parents for root in allowed):
                raise Refused('refused: a file outside the allowlisted assets')
            if not path.is_file():
                raise Refused('refused: not a regular file')
            return URLFetcherResponse(url, path.read_bytes(), {})

    document = sys.stdin.buffer.read().decode('utf-8')
    pdf = HTML(string=document, base_url=allowed[0].as_uri() + '/', url_fetcher=AssetsOnly()).write_pdf()
    sys.stdout.buffer.write(pdf)
    return 0


if __name__ == '__main__':
    sys.exit(main())
