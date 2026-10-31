import { Boxes, ClipboardCheck, FileText, FolderKanban, House, type LucideIcon, ListChecks, Search, Settings, ShieldCheck, ShoppingCart, Users, Wallet, BarChart3 } from 'lucide-react';

import type { TranslationKey } from './i18n';

// The shell's entries (INFORMATION_ARCHITECTURE §5.2, §5.3, §6, §10, §13; NAVIGATION_CONTRACTS NV-08; TASK-016):
// each present only when the account's capabilities — WS-07's shared hint — open something behind it, absent
// otherwise, never disabled; the server decides every request (AZ-01; H7-03). An entry whose page is not built yet
// is intentionally hidden (ROUTE_CONTRACTS RC-06): each page Task adds its PG- to `BUILT`, and the entry appears.

/** The account's hints the shell reads (WS-07): its display name, effective capabilities and companies in scope. */
export type AccountHints = { name: string | null; capabilities: string[]; companies: { id: string; code: string; name: string }[] };

export type Entry = {
    key: string;
    pg: string;
    label: TranslationKey;
    href: string;
    icon: LucideIcon;
    allowed: (account: AccountHints) => boolean;
    /** A module's later sub-pages: while its first page is not built, the entry opens on the first one that is (§5.3) —
        and that the account reaches, where fewer reach a sub-page than the module. */
    subpages?: { pg: string; href: string; allowed?: (account: AccountHints) => boolean }[];
    /** Who reaches the module's first page, where fewer reach it than the module: the others open on a sub-page. */
    page?: (account: AccountHints) => boolean;
};

/** The pages built so far, by PG-; a page Task adds its own when its route exists. */
// ROUTE-108's logout is TASK-017's: the account menu's "Keluar".
// PG-061 *Penomoran Dokumen* is TASK-021's; PG-060 *Pengguna & Akses* TASK-024's.
export const BUILT: ReadonlySet<string> = new Set<string>(['ROUTE-108', 'PG-010', 'PG-011', 'PG-013', 'PG-014', 'PG-025', 'PG-033', 'PG-035', 'PG-036', 'PG-037', 'PG-038', 'PG-040', 'PG-041', 'PG-042', 'PG-043', 'PG-044', 'PG-045', 'PG-046', 'PG-047', 'PG-050', 'PG-054', 'PG-055', 'PG-056', 'PG-057', 'PG-059', 'PG-060', 'PG-061', 'PG-062', 'PG-063', 'PG-064', 'PG-065', 'ROUTE-118']);

const has =
    (...capabilities: string[]) =>
    (account: AccountHints): boolean =>
        capabilities.some((capability) => account.capabilities.includes(capability));

/** A company-owned module needs a company in scope as well: without a grant an account reads S0 and, with
    `stock.view`, the pool, and nothing more (PERMISSIONS_MATRIX CS-05; §6.3). */
const scoped =
    (...capabilities: string[]) =>
    (account: AccountHints): boolean =>
        account.companies.length > 0 && has(...capabilities)(account);

/** The Owner's landing page and entries follow `review.view`, OWNER_ONLY (NAV-001; OD-14). */
export const isOwner = has('review.view');

const everyAccount = (): boolean => true;

export const ENTRIES = {
    beranda: { key: 'beranda', pg: 'PG-006', label: 'shell.nav.beranda', href: '/beranda', icon: House, allowed: isOwner },
    proyek: { key: 'proyek', pg: 'PG-010', label: 'pages.pg_010.title', href: '/proyek', icon: FolderKanban, allowed: scoped('projects.view') },
    pekerjaan: { key: 'pekerjaan', pg: 'PG-007', label: 'shell.nav.pekerjaan', href: '/pekerjaan', icon: ListChecks, allowed: everyAccount },
    tinjauan: { key: 'tinjauan', pg: 'PG-008', label: 'glossary.gl_193', href: '/tinjauan', icon: ClipboardCheck, allowed: isOwner },
    cari: { key: 'cari', pg: 'PG-009', label: 'shell.nav.cari', href: '/cari', icon: Search, allowed: everyAccount },
} satisfies Record<string, Entry>;

/** *Menu lengkap* — each module opens on its first sub-page (§5.3). */
export const MODULES: Entry[] = [
    { key: 'pembelian', pg: 'PG-015', label: 'glossary.gl_006', href: '/pembelian', icon: ShoppingCart, allowed: scoped('cost.view') },
    {
        key: 'gudang',
        pg: 'PG-018',
        label: 'glossary.gl_007',
        href: '/gudang/stok',
        icon: Boxes,
        allowed: (account) => has('stock.view')(account) || scoped('stock.receive', 'stock.dispatch')(account),
        // PG-025 *Reservasi* (TASK-038): `stock.reserve` with a company in scope (CS-05).
        subpages: [{ pg: 'PG-025', href: '/gudang/reservasi', allowed: scoped('stock.reserve') }],
    },
    { key: 'keuangan', pg: 'PG-041', label: 'glossary.gl_016', href: '/keuangan/invoice', icon: Wallet, allowed: scoped('finance.view'), subpages: [{ pg: 'PG-044', href: '/keuangan/piutang' }, { pg: 'PG-045', href: '/keuangan/pembayaran' }, { pg: 'PG-047', href: '/keuangan/potongan' }, { pg: 'PG-050', href: '/keuangan/mutasi-bank', allowed: scoped('bank_statement.record') }] },
    {
        key: 'dokumen',
        pg: 'PG-035',
        label: 'glossary.gl_014',
        href: '/dokumen',
        icon: FileText,
        allowed: scoped('projects.view', 'evidence.view'),
        page: scoped('projects.view'),
        subpages: [{ pg: 'PG-037', href: '/dokumen/penawaran' }, { pg: 'PG-038', href: '/dokumen/bukti' }, { pg: 'PG-039', href: '/dokumen/administrasi' }, { pg: 'PG-040', href: '/dokumen/kasus' }],
    },
    {
        key: 'laporan',
        pg: 'PG-051',
        label: 'glossary.gl_020',
        href: '/laporan',
        icon: BarChart3,
        allowed: (account) => has('stock.view')(account) || scoped('projects.view', 'finance.view', 'cost.view', 'profit.view', 'reports.consolidated')(account),
    },
    {
        key: 'data-master',
        pg: 'PG-054',
        label: 'glossary.gl_021',
        href: '/data-master/klien',
        icon: Users,
        allowed: everyAccount,
        subpages: [
            { pg: 'PG-055', href: '/data-master/pemasok' },
            { pg: 'PG-056', href: '/data-master/produk' },
            { pg: 'PG-057', href: '/data-master/lokasi-rak' },
        ],
    },
    {
        key: 'aktivitas',
        pg: 'PG-063',
        label: 'shell.nav.aktivitas_keamanan',
        href: '/aktivitas/riwayat',
        icon: ShieldCheck,
        allowed: has('audit.view', 'security_log.view'),
        // TASK-061: *Riwayat Aktivitas* with `audit.view`, *Log Keamanan* with `security_log.view` (OWNER_ONLY each).
        page: has('audit.view'),
        subpages: [{ pg: 'PG-064', href: '/aktivitas/log-keamanan', allowed: has('security_log.view') }],
    },
    {
        key: 'pengaturan',
        pg: 'PG-059',
        label: 'glossary.gl_022',
        href: '/pengaturan/perusahaan',
        icon: Settings,
        allowed: has('companies.manage', 'users.manage', 'permissions.manage', 'tax.configure'),
        subpages: [
            { pg: 'PG-060', href: '/pengaturan/pengguna' },
            { pg: 'PG-061', href: '/pengaturan/penomoran' },
            { pg: 'PG-062', href: '/pengaturan/pajak' },
        ],
    },
];

/** "Buat" — each item with its capability and a company in scope, as every command needs (CS-05); each new root
    names its company in its own form (§10; CS-03). */
export const CREATE: Entry[] = [
    { key: 'proyek', pg: 'PG-013', label: 'glossary.gl_004', href: '/proyek/baru', icon: FolderKanban, allowed: scoped('projects.manage') },
    { key: 'pembelian', pg: 'PG-017', label: 'glossary.gl_006', href: '/pembelian/baru', icon: ShoppingCart, allowed: scoped('purchase.manage') },
    { key: 'barang-masuk', pg: 'PG-021', label: 'glossary.gl_009', href: '/gudang/barang-masuk', icon: Boxes, allowed: scoped('stock.receive') },
    { key: 'pembayaran', pg: 'PG-046', label: 'glossary.gl_018', href: '/keuangan/pembayaran/baru', icon: Wallet, allowed: scoped('payment.record') },
    { key: 'unggah', pg: 'PG-038', label: 'shell.create.upload', href: '/dokumen/bukti', icon: FileText, allowed: scoped('evidence.upload') },
];

/** A search result as ROUTE-281 answers it. */
export type SearchResult = { type: string; id: string; code: string | null; label: string; detail?: string; company?: string };

/** Where a search result opens (INFORMATION_ARCHITECTURE §8, §10): a product or a serial its stock (PG-019) — the
    catalogue's list without `stock.view` —, a project its journey, an invoice its page, a client — a unit or PIC
    through its organization — and a supplier their master list; null while that page is not built. */
export function targetOf(result: SearchResult, account: AccountHints, built: ReadonlySet<string> = BUILT): string | null {
    const stock = account.capabilities.includes('stock.view');
    const targets: Record<string, { pg: string; href: string }> = {
        product: stock ? { pg: 'PG-019', href: `/gudang/stok/${result.id}` } : { pg: 'PG-056', href: `/data-master/produk?produk=${result.id}` },
        serial: { pg: 'PG-019', href: `/gudang/stok/${result.id}` },
        project: { pg: 'PG-011', href: `/proyek/${result.id}` },
        document: { pg: 'PG-036', href: `/dokumen/${result.id}` },
        invoice: { pg: 'PG-043', href: `/keuangan/invoice/${result.id}` },
        client: { pg: 'PG-054', href: `/data-master/klien?klien=${result.id}` },
        unit: { pg: 'PG-054', href: `/data-master/klien?klien=${result.id}` },
        pic: { pg: 'PG-054', href: `/data-master/klien?klien=${result.id}` },
        supplier: { pg: 'PG-055', href: `/data-master/pemasok?pemasok=${result.id}` },
    };
    const target = targets[result.type];

    return target !== undefined && built.has(target.pg) ? target.href : null;
}

export function present(entries: Entry[], account: AccountHints, built: ReadonlySet<string> = BUILT): Entry[] {
    return entries.flatMap((entry) => {
        if (!entry.allowed(account)) {
            return [];
        }

        if (built.has(entry.pg) && (entry.page === undefined || entry.page(account))) {
            return [entry];
        }

        const page = entry.subpages?.find((subpage) => built.has(subpage.pg) && (subpage.allowed === undefined || subpage.allowed(account)));

        return page === undefined ? [] : [{ ...entry, pg: page.pg, href: page.href }];
    });
}

/** §5.2: the Owner's *Beranda* · *Proyek* · *Pekerjaan* · *Tinjauan*; an Admin's *Pekerjaan* · *Proyek*. */
export function primaryEntries(account: AccountHints, built?: ReadonlySet<string>): Entry[] {
    const order = isOwner(account) ? [ENTRIES.beranda, ENTRIES.proyek, ENTRIES.pekerjaan, ENTRIES.tinjauan] : [ENTRIES.pekerjaan, ENTRIES.proyek];

    return present(order, account, built);
}

/** §5.2's phone bar: the landing page, *Proyek* and *Cari* — *Lainnya* is the shell's own, always last. */
export function bottomEntries(account: AccountHints, built?: ReadonlySet<string>): Entry[] {
    const order = isOwner(account) ? [ENTRIES.beranda, ENTRIES.proyek] : [ENTRIES.pekerjaan, ENTRIES.proyek];

    return present(order, account, built);
}

/** *Lainnya*'s *Kerja* group: what the bottom bar leaves out of the primary entries — the Owner's *Pekerjaan* and *Tinjauan*. */
export function workEntries(account: AccountHints, built?: ReadonlySet<string>): Entry[] {
    const shown = new Set(bottomEntries(account, built).map((entry) => entry.key));

    return primaryEntries(account, built).filter((entry) => !shown.has(entry.key));
}

/** The current entry, by the address's first segment — a module's sub-pages and a record's pages included —, for
    `aria-current="page"` (§6.1). */
export function currentKey(entries: Entry[], pathname: string): string | null {
    const segment = pathname.split('/')[1] ?? '';

    return entries.find((entry) => entry.href.split('/')[1] === segment)?.key ?? null;
}
