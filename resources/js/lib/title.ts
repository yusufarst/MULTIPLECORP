// The document title of a page (INFORMATION_ARCHITECTURE §11; H7-10): "{nama layar} · MultipleCorp" — the screen's
// name only, never a business number, name, company, amount or state, because titles enter history and tab lists.
const APPLICATION = 'MultipleCorp';

export function documentTitle(screen: string): string {
    return `${screen} · ${APPLICATION}`;
}
