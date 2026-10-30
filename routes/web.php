<?php

use App\Http\BackgroundRequest;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\SystemPageController;
use App\Http\Controllers\UsersAndAccessController;
use App\Modules\ADM\Http\Controllers\RequirementsController;
use App\Modules\CAT\Http\Controllers\ProductsController;
use App\Modules\DOC\Http\Controllers\DocumentsController;
use App\Modules\DOC\Http\Controllers\EvidenceController;
use App\Modules\DOC\Http\Controllers\FileController;
use App\Modules\FIN\Http\Controllers\InvoicesController;
use App\Modules\FUL\Http\Controllers\HandoverController;
use App\Modules\IAM\Authentication\CredentialPageHeaders;
use App\Modules\IAM\Authentication\SessionController;
use App\Modules\IAM\Http\Controllers\ConfirmPasswordController;
use App\Modules\IAM\Http\Controllers\GoogleSignInController;
use App\Modules\IAM\Http\Controllers\OwnAccountController;
use App\Modules\IAM\Http\Controllers\SetPasswordController;
use App\Modules\IAM\Http\Controllers\SignInController;
use App\Modules\IAM\Http\Controllers\TotpEnrolmentController;
use App\Modules\IAM\Http\Controllers\TwoFactorChallengeController;
use App\Modules\INV\Http\Controllers\LocationsController;
use App\Modules\INV\Http\Controllers\ReservationsController;
use App\Modules\OPS\Http\Controllers\CorrectionCasesController;
use App\Modules\OPS\Http\Controllers\LogsController;
use App\Modules\ORG\Http\Controllers\CompaniesController;
use App\Modules\ORG\Http\Controllers\NumberingController;
use App\Modules\ORG\Http\Controllers\TaxController;
use App\Modules\PRJ\Http\Controllers\FulfilmentController;
use App\Modules\PRJ\Http\Controllers\ProjectsController;
use App\Modules\PRJ\Http\Controllers\QuotationsController;
use App\Modules\PTY\Http\Controllers\ClientsController;
use App\Modules\PTY\Http\Controllers\SuppliersController;
use App\Modules\Reporting\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

// Every route is a ROUTE_CONTRACTS row, added by the Task that builds it.

// Sign-in and the session (TASK-017; SECURITY AU-04–AU-15; EN-03's unauthenticated exceptions). Fortify's
// controllers serve ROUTE-101 and ROUTE-108 behind IAM's pipeline; Fortify registers no route of its own (SM-22).
Route::get('/masuk', SignInController::class)->middleware(['guest', 'access:none,login'])->name('login');
Route::post('/masuk', [SessionController::class, 'store'])->middleware(['guest', 'access:none,login'])->name('login.store');
Route::post('/keluar', [SessionController::class, 'destroy'])->middleware(['auth', 'access:self'])->name('logout');
Route::post('/konfirmasi-kata-sandi', ConfirmPasswordController::class)->middleware(['auth', 'access:self'])->name('password.confirm');
Route::get('/atur-kata-sandi', [SetPasswordController::class, 'show'])->middleware(['access:none,credential_link', CredentialPageHeaders::class])->name('password.set.show');
Route::post('/atur-kata-sandi', [SetPasswordController::class, 'store'])->middleware(['access:none,credential_link', 'throttle:credential-link', CredentialPageHeaders::class])->name('password.set');

// The second factor (TASK-018; SECURITY AU-16, AU-18, AU-19): PG-002 for the pending attempt's account, before any
// login (EN-03), and PG-004 for the enrolment-only session alone; AU-05's throttles and its second-factor limit are
// applied by the steps themselves, counting failures alone. Fortify's own two-factor routes stay unregistered (TM-65).
Route::get('/masuk/verifikasi', [TwoFactorChallengeController::class, 'show'])->middleware(['guest', 'access:none,two_factor_challenge'])->name('two-factor.challenge');
Route::post('/masuk/verifikasi', [TwoFactorChallengeController::class, 'verify'])->middleware(['guest', 'access:none,two_factor_challenge'])->name('two-factor.verify');
Route::post('/masuk/verifikasi/pemulihan', [TwoFactorChallengeController::class, 'recovery'])->middleware(['guest', 'access:none,two_factor_challenge'])->name('two-factor.recovery');
Route::get('/verifikasi-dua-langkah', [TotpEnrolmentController::class, 'show'])->middleware(['auth', 'access:enrolment', CredentialPageHeaders::class])->name('two-factor.enrolment');
Route::post('/verifikasi-dua-langkah', [TotpEnrolmentController::class, 'confirm'])->middleware(['auth', 'access:enrolment', CredentialPageHeaders::class])->name('two-factor.enrolment.confirm');

// Google sign-in (TASK-019; SECURITY AU-22): existing accounts only, always followed by PG-002's challenge;
// throttled per client address (AU-05); off with no client configured (GLC-09).
// ROUTE-102 starts a SIGN_IN attempt for a guest and, with `tujuan=tautkan`, PG-065's LINK attempt for the holder's own
// session established with TOTP (AU-23; TASK-023): each purpose checks its own session state.
Route::get('/masuk/google', [GoogleSignInController::class, 'redirect'])->middleware(['access:none,google_redirect', 'throttle:google'])->name('google.redirect');
Route::get('/masuk/google/callback', [GoogleSignInController::class, 'callback'])->middleware(['access:none,google_callback', 'throttle:google'])->name('google.callback');

// PG-061 *Penomoran Dokumen* (TASK-021; ROUTE-061, ROUTE-136, ROUTE-137; NAV-061): the Owner's numbering area and its
// command family NX-01, `companies.manage` (OWNER_ONLY; OD-03) — no step-up, which neither ROUTE-136 nor ROUTE-137 names
// (SECURITY AU-15; DIR-098 reading 1).
Route::get('/pengaturan/penomoran', [NumberingController::class, 'show'])->middleware(['auth', 'capability:companies.manage'])->name('org.numbering');
Route::put('/pengaturan/penomoran/{type}', [NumberingController::class, 'configure'])->middleware(['auth', 'capability:companies.manage'])->name('org.numbering.configure');
Route::post('/pengaturan/penomoran/{type}/mulai', [NumberingController::class, 'start'])->middleware(['auth', 'capability:companies.manage'])->name('org.numbering.start');
// PG-059 *Perusahaan* and PG-062 *Pajak* (TASK-025; ROUTE-059, ROUTE-062, ROUTE-131–ROUTE-135, ROUTE-138; NAV-059,
// NAV-062): the Owner's company master and tax configuration (OD-03; OD-04), each NX-01 command carrying its
// `command_id`; the bank accounts and identity assets behind the step-up, a legal-data change when it touches an
// identity asset (AU-15); an Admin's request answers as a missing page (IP-21; AZ-11).
Route::get('/pengaturan/perusahaan', [CompaniesController::class, 'show'])->middleware(['auth', 'capability:companies.manage'])->name('org.companies');
Route::post('/pengaturan/perusahaan', [CompaniesController::class, 'store'])->middleware(['auth', 'capability:companies.manage'])->name('org.companies.store');
Route::put('/pengaturan/perusahaan/{id}', [CompaniesController::class, 'update'])->middleware(['auth', 'capability:companies.manage'])->name('org.companies.update');
Route::post('/pengaturan/perusahaan/{id}/nonaktifkan', [CompaniesController::class, 'deactivate'])->middleware(['auth', 'capability:companies.manage'])->name('org.companies.deactivate');
Route::post('/pengaturan/perusahaan/{id}/rekening', [CompaniesController::class, 'accounts'])->middleware(['auth', 'capability:companies.manage', 'step-up'])->name('org.companies.accounts');
Route::post('/pengaturan/perusahaan/{id}/aset-identitas', [CompaniesController::class, 'asset'])->middleware(['auth', 'capability:companies.manage', 'step-up'])->name('org.companies.asset');
Route::get('/pengaturan/pajak', [TaxController::class, 'show'])->middleware(['auth', 'capability:tax.configure'])->name('org.tax');
Route::put('/pengaturan/pajak', [TaxController::class, 'configure'])->middleware(['auth', 'capability:tax.configure'])->name('org.tax.configure');

// PG-054 *Klien* and PG-055 *Pemasok* (TASK-026; ROUTE-054, ROUTE-055, ROUTE-141–ROUTE-144; NAV-054, NAV-055): the
// shared party masters, every active account's for their S0 names, each other part under its own read class (PJ-09;
// PJ-10); their create, edit and archive `parties.manage`, each NX-01 command with its `command_id` (CI-01).
Route::get('/data-master/klien', [ClientsController::class, 'show'])->middleware(['auth', 'access:s0'])->name('pty.clients');
Route::post('/data-master/klien', [ClientsController::class, 'store'])->middleware(['auth', 'capability:parties.manage'])->name('pty.clients.store');
Route::put('/data-master/klien/{id}', [ClientsController::class, 'update'])->middleware(['auth', 'capability:parties.manage'])->name('pty.clients.update');
Route::get('/data-master/pemasok', [SuppliersController::class, 'show'])->middleware(['auth', 'access:s0'])->name('pty.suppliers');
Route::post('/data-master/pemasok', [SuppliersController::class, 'store'])->middleware(['auth', 'capability:parties.manage'])->name('pty.suppliers.store');
Route::put('/data-master/pemasok/{id}', [SuppliersController::class, 'update'])->middleware(['auth', 'capability:parties.manage'])->name('pty.suppliers.update');

// PG-056 *Produk & Jasa* (TASK-027; ROUTE-056, ROUTE-145–ROUTE-147; NAV-056): the catalogue, every active account's for
// its S0 identity, price defaults by family and stock figures with `stock.view` (PJ-14); its create, edits and the
// barcode registration `products.edit`, each NX-01 command with its `command_id` (CI-01); "Cetak Label" client-only.
Route::get('/data-master/produk', [ProductsController::class, 'show'])->middleware(['auth', 'access:s0'])->name('cat.products');
Route::post('/data-master/produk', [ProductsController::class, 'store'])->middleware(['auth', 'capability:products.edit'])->name('cat.products.store');
Route::put('/data-master/produk/{id}', [ProductsController::class, 'update'])->middleware(['auth', 'capability:products.edit'])->name('cat.products.update');
Route::post('/data-master/produk/{id}/barcode', [ProductsController::class, 'barcode'])->middleware(['auth', 'capability:products.edit'])->name('cat.products.barcode');

// The project hub (TASK-032; WF-PRJ-01; J-PRJ-01): PG-010 *Proyek* and PG-011 *Perjalanan Proyek* to `projects.view`
// (ROUTE-010, ROUTE-011; NAV-010, NAV-011), PG-013 *Formulir Proyek* and its commands "Buat Proyek", "Ubah proyek" and
// "Buang Draf" to `projects.manage` (ROUTE-013, ROUTE-151–ROUTE-153; NAV-013), each NX-03 command with its `command_id`
// (CI-01); the company of each command checked against the actor's scope by the command itself (CS-03; AZ-11).
Route::get('/proyek', [ProjectsController::class, 'index'])->middleware(['auth', 'capability:projects.view'])->name('prj.projects');
Route::get('/proyek/baru', [ProjectsController::class, 'create'])->middleware(['auth', 'capability:projects.manage'])->name('prj.projects.create');
Route::post('/proyek', [ProjectsController::class, 'store'])->middleware(['auth', 'capability:projects.manage'])->name('prj.projects.store');
Route::get('/proyek/{id}', [ProjectsController::class, 'show'])->whereUuid('id')->middleware(['auth', 'capability:projects.view'])->name('prj.projects.show');
Route::get('/proyek/{id}/ubah', [ProjectsController::class, 'edit'])->whereUuid('id')->middleware(['auth', 'capability:projects.manage'])->name('prj.projects.edit');
Route::put('/proyek/{id}', [ProjectsController::class, 'update'])->whereUuid('id')->middleware(['auth', 'capability:projects.manage'])->name('prj.projects.update');
Route::post('/proyek/{id}/hapus-draf', [ProjectsController::class, 'discard'])->whereUuid('id')->middleware(['auth', 'capability:projects.manage'])->name('prj.projects.discard');
// The commercial confirmation and fulfilment planning (TASK-034; WF-PRJ-02; WF-FUL-01): "Konfirmasi Proyek" without a quotation
// (ROUTE-158) to `projects.confirm`, "Batalkan Sisa" (ROUTE-159) to `projects.cancel_scope` and "Ubah Cara Pemenuhan" (ROUTE-160) to
// `projects.manage`, each AX-13 or AX-14 command with its `command_id`, answered in place on PG-011.
Route::post('/proyek/{id}/konfirmasi', [FulfilmentController::class, 'confirm'])->whereUuid('id')->middleware(['auth', 'capability:projects.confirm'])->name('prj.projects.confirm');
Route::post('/proyek/{id}/batalkan-sisa', [FulfilmentController::class, 'cancel'])->whereUuid('id')->middleware(['auth', 'capability:projects.cancel_scope'])->name('prj.projects.cancel-scope');
Route::post('/proyek/{id}/ubah-pemenuhan', [FulfilmentController::class, 'replan'])->whereUuid('id')->middleware(['auth', 'capability:projects.manage'])->name('prj.projects.replan');
// The quotation cycle (TASK-033; WF-QUO-01; J-QUO-01): PG-014 *Penawaran* (ROUTE-014; NAV-014) to `quotation.manage` with
// `finance.view`, or for the client's answer `projects.confirm` — checked by its query beyond `projects.view` —, PG-037
// *Daftar Penawaran* (ROUTE-037; NAV-037) to `projects.view`; the draft, the issue and the revision `quotation.manage`
// (ROUTE-154–ROUTE-156), the client's answer by its own capability in its command (ROUTE-157), each with its `command_id`.
Route::get('/proyek/{id}/penawaran', [QuotationsController::class, 'show'])->whereUuid('id')->middleware(['auth', 'capability:projects.view'])->name('prj.quotations.show');
Route::post('/proyek/{id}/penawaran', [QuotationsController::class, 'save'])->whereUuid('id')->middleware(['auth', 'capability:quotation.manage'])->name('prj.quotations.save');
Route::post('/proyek/{id}/penawaran/terbitkan', [QuotationsController::class, 'issue'])->whereUuid('id')->middleware(['auth', 'capability:quotation.manage'])->name('prj.quotations.issue');
Route::post('/proyek/{id}/penawaran/revisi', [QuotationsController::class, 'revise'])->whereUuid('id')->middleware(['auth', 'capability:quotation.manage'])->name('prj.quotations.revise');
Route::post('/proyek/{id}/penawaran/jawaban', [QuotationsController::class, 'answerClient'])->whereUuid('id')->middleware(['auth', 'capability:projects.view'])->name('prj.quotations.answer');

// PG-057 *Lokasi Rak* (TASK-028; ROUTE-057, ROUTE-148; NAV-057): the shared warehouse's racks and the products' minimum
// stock, pool records of no company to `stock.view` (CS-05); their one command `warehouse.manage`, NX-01 with its
// `command_id` (CI-01).
Route::get('/data-master/lokasi-rak', [LocationsController::class, 'show'])->middleware(['auth', 'capability:stock.view'])->name('inv.locations');
Route::post('/data-master/lokasi-rak', [LocationsController::class, 'save'])->middleware(['auth', 'capability:warehouse.manage'])->name('inv.locations.save');

// TASK-038 — PG-025 *Reservasi* and its commands (WF-INV-02; AX-01; AX-02): ROUTE-025, ROUTE-184 and ROUTE-185, `stock.reserve`
// with its prerequisites `stock.view` and `projects.view` (PERMISSIONS_MATRIX §4).
Route::get('/gudang/reservasi', [ReservationsController::class, 'index'])->middleware(['auth', 'capability:stock.reserve'])->name('inv.reservations');
Route::post('/gudang/reservasi', [ReservationsController::class, 'store'])->middleware(['auth', 'capability:stock.reserve'])->name('inv.reservations.reserve');
Route::post('/gudang/reservasi/{id}/kurangi', [ReservationsController::class, 'reduce'])->where('id', '[0-9a-f]{32}')->middleware(['auth', 'capability:stock.reserve'])->name('inv.reservations.reduce');

// TASK-049 — the invoice (WF-FIN-01; WF-FIN-08; AX-16; AX-22; AX-31): PG-041 and PG-043 to `finance.view`, PG-042 and "Simpan Draf" to `invoice.issue` — with `finance.view`, checked by
// its command —, "Terbitkan Invoice" `invoice.issue`, the corrections `invoice.correct` (ADM+), the Kuitansi `documents.issue` and its link `payment.record`, each with its `command_id`.
Route::get('/keuangan/invoice', [InvoicesController::class, 'index'])->middleware(['auth', 'capability:finance.view'])->name('fin.invoices');
Route::get('/keuangan/invoice/baru', [InvoicesController::class, 'create'])->middleware(['auth', 'capability:invoice.issue'])->name('fin.invoices.create');
Route::get('/keuangan/invoice/{id}', [InvoicesController::class, 'show'])->whereUuid('id')->middleware(['auth', 'capability:finance.view'])->name('fin.invoices.show');
Route::post('/keuangan/invoice', [InvoicesController::class, 'save'])->middleware(['auth', 'capability:invoice.issue'])->name('fin.invoices.save');
Route::post('/keuangan/invoice/{id}/terbitkan', [InvoicesController::class, 'issue'])->whereUuid('id')->middleware(['auth', 'capability:invoice.issue'])->name('fin.invoices.issue');
Route::post('/keuangan/invoice/{id}/koreksi', [InvoicesController::class, 'correct'])->whereUuid('id')->middleware(['auth', 'capability:invoice.correct'])->name('fin.invoices.correct');
Route::post('/keuangan/invoice/{id}/kuitansi', [InvoicesController::class, 'kuitansi'])->whereUuid('id')->middleware(['auth', 'capability:documents.issue'])->name('fin.invoices.kuitansi');
Route::post('/keuangan/kuitansi/{id}/tautkan', [InvoicesController::class, 'link'])->whereUuid('id')->middleware(['auth', 'capability:payment.record'])->name('fin.kuitansi.link');

// TASK-047 — PG-033 *Serah Terima Jasa* and its commands (WF-FUL-04; AX-09): ROUTE-033 and ROUTE-208 `handover.record`, ROUTE-209
// `fulfillment.correct` (ADM+), each with its prerequisite `projects.view` (PERMISSIONS_MATRIX §4); the BAST through ROUTE-221 and ROUTE-222.
Route::get('/proyek/{id}/serah-terima', [HandoverController::class, 'show'])->whereUuid('id')->middleware(['auth', 'capability:handover.record'])->name('ful.handovers.show');
Route::post('/proyek/{id}/serah-terima', [HandoverController::class, 'store'])->whereUuid('id')->middleware(['auth', 'capability:handover.record'])->name('ful.handovers.record');
Route::post('/proyek/{id}/serah-terima/{ho}/balikkan', [HandoverController::class, 'reverse'])->whereUuid('id')->whereUuid('ho')->middleware(['auth', 'capability:fulfillment.correct'])->name('ful.handovers.reverse');

// PG-065 *Akun Saya* (TASK-023; ROUTE-065, ROUTE-111–ROUTE-118; NAV-065): the account's own self-service, SELF on its
// own row (AU-13; PJ-17), none carrying a `command_id` (CI-09; RB-03); the sensitive steps behind the step-up (AU-15),
// the code-taking ones under AU-05's throttles inside their steps, the password change under WS-12's 5 per 15 minutes.
Route::get('/akun', [OwnAccountController::class, 'show'])->middleware(['auth', 'access:self'])->name('account.show');
Route::put('/akun/kata-sandi', [OwnAccountController::class, 'password'])->middleware(['auth', 'access:self', 'throttle:password-change'])->name('account.password');
Route::post('/akun/verifikasi-dua-langkah', [OwnAccountController::class, 'enrol'])->middleware(['auth', 'access:self', 'step-up'])->name('account.totp');
Route::delete('/akun/verifikasi-dua-langkah', [OwnAccountController::class, 'disableTotp'])->middleware(['auth', 'access:self', 'step-up'])->name('account.totp.disable');
Route::post('/akun/kode-pemulihan', [OwnAccountController::class, 'viewCodes'])->middleware(['auth', 'access:self', 'step-up'])->name('account.recovery-codes');
Route::post('/akun/kode-pemulihan/baru', [OwnAccountController::class, 'regenerateCodes'])->middleware(['auth', 'access:self', 'step-up'])->name('account.recovery-codes.regenerate');
Route::post('/akun/google', [OwnAccountController::class, 'linkGoogle'])->middleware(['auth', 'access:self', 'step-up'])->name('account.google');
Route::delete('/akun/google', [OwnAccountController::class, 'unlinkGoogle'])->middleware(['auth', 'access:self', 'step-up'])->name('account.google.unlink');
Route::put('/akun/bahasa', [OwnAccountController::class, 'locale'])->middleware(['auth', 'access:self'])->name('account.locale');

// PG-060 *Pengguna & Akses* (TASK-024; ROUTE-060, ROUTE-121–ROUTE-128, ROUTE-130; NAV-060): the Owner's accounts,
// grants, credential links and sessions (OD-01, OD-02), each command of family NX-02 carrying its `command_id` behind
// the step-up (AU-15); an Admin's request for any of them answers as a missing page (IP-21; AZ-11). ROUTE-129 stays
// withdrawn: the key-compromise command is the operator's console command (AU-21).
Route::get('/pengaturan/pengguna', [UsersAndAccessController::class, 'show'])->middleware(['auth', 'capability:users.manage'])->name('users.show');
Route::post('/pengaturan/pengguna', [UsersAndAccessController::class, 'store'])->middleware(['auth', 'capability:users.manage', 'step-up'])->name('users.store');
Route::put('/pengaturan/pengguna/{id}', [UsersAndAccessController::class, 'update'])->middleware(['auth', 'capability:users.manage', 'step-up'])->name('users.update');
Route::post('/pengaturan/pengguna/{id}/status', [UsersAndAccessController::class, 'status'])->middleware(['auth', 'capability:users.manage', 'step-up'])->name('users.status');
Route::put('/pengaturan/pengguna/{id}/akses', [UsersAndAccessController::class, 'access'])->middleware(['auth', 'capability:permissions.manage', 'step-up'])->name('users.access');
Route::post('/pengaturan/pengguna/{id}/tautan', [UsersAndAccessController::class, 'link'])->middleware(['auth', 'capability:users.manage', 'step-up'])->name('users.link');
Route::post('/pengaturan/pengguna/{id}/tautan/cabut', [UsersAndAccessController::class, 'revokeLink'])->middleware(['auth', 'capability:users.manage', 'step-up'])->name('users.link.revoke');
Route::post('/pengaturan/pengguna/{id}/reset-verifikasi', [UsersAndAccessController::class, 'reset'])->middleware(['auth', 'capability:users.manage', 'step-up'])->name('users.reset');
Route::post('/pengaturan/pengguna/{id}/lepas-google', [UsersAndAccessController::class, 'unlink'])->middleware(['auth', 'capability:users.manage', 'step-up'])->name('users.unlink');
Route::post('/pengaturan/pengguna/{id}/sesi/{sesi}/akhiri', [UsersAndAccessController::class, 'endSession'])->middleware(['auth', 'capability:users.manage', 'step-up'])->name('users.session.end');
// PG-038 *Bukti* and the evidence (TASK-030; ROUTE-038, ROUTE-228–ROUTE-231; NAV-038): the page by `evidence.view`, each
// family inside its query (PJ-08); the one upload of every upload field, each purpose under its own capability in the
// action (PJ-08; PJ-20), without `command_id` behind WS-12's upload limiter (RB-03); the registration, a version and the
// links by `evidence.upload`, each NX-07 or NX-14 command carrying its `command_id`.
Route::get('/dokumen/bukti', [EvidenceController::class, 'show'])->middleware(['auth', 'capability:evidence.view'])->name('doc.evidence');
Route::post('/berkas', [EvidenceController::class, 'upload'])->middleware(['auth', 'access:s0', 'throttle:uploads'])->name('doc.files.store');
Route::post('/bukti', [EvidenceController::class, 'register'])->middleware(['auth', 'capability:evidence.upload'])->name('doc.evidence.register');
Route::post('/bukti/{id}/versi', [EvidenceController::class, 'version'])->middleware(['auth', 'capability:evidence.upload'])->name('doc.evidence.version');
Route::post('/bukti/{id}/tautan', [EvidenceController::class, 'links'])->middleware(['auth', 'capability:evidence.upload'])->name('doc.evidence.links');
// PG-035 *Dokumen* and PG-036 *Detail Dokumen* with the document engine (TASK-029; ROUTE-035, ROUTE-036,
// ROUTE-221–ROUTE-225; NAV-035, NAV-036): the list by `projects.view`, each family inside its query (PJ-15); the draft
// and the issue by `documents.issue`, revision and void by `documents.correct`, each NX-12 or AX-15 command carrying
// its `command_id`; the rendition inline through its document at request time like a download (FL-07; DP-05).
Route::get('/dokumen', [DocumentsController::class, 'index'])->middleware(['auth', 'capability:projects.view'])->name('doc.documents');
Route::get('/dokumen/penawaran', [QuotationsController::class, 'index'])->middleware(['auth', 'capability:projects.view'])->name('prj.quotations');
// PG-040 *Kasus Koreksi* (TASK-031; ROUTE-040, ROUTE-271; NAV-040): the cases by `projects.view` of their company, money
// by family inside the query (PJ-16); "Tutup Kasus" by `cases.close`, its NX-06 command carrying its `command_id`.
// PG-039 *Dokumen Administrasi* and the administrative checklist (TASK-035; ROUTE-039, ROUTE-164–ROUTE-169; NAV-039;
// J-ADM-01): the page by `projects.view`; adding, tightening and linking by `requirements.manage`, "Tandai Tidak
// Berlaku" by `requirements.waive`, the Owner's eligibility and relaxation by their OWNER_ONLY capabilities, each NX-03
// command carrying its `command_id`; a decision superseded by `decisions.supersede` (CM-27; OD-15).
Route::get('/dokumen/administrasi', [RequirementsController::class, 'index'])->middleware(['auth', 'capability:projects.view'])->name('adm.documents');
Route::post('/proyek/{id}/persyaratan', [RequirementsController::class, 'store'])->whereUuid('id')->middleware(['auth', 'capability:requirements.manage'])->name('adm.requirements.store');
Route::post('/proyek/{id}/persyaratan/{req}/tautkan', [RequirementsController::class, 'link'])->whereUuid('id')->where('req', '[0-9a-f]{32}')->middleware(['auth', 'capability:requirements.manage'])->name('adm.requirements.link');
Route::post('/proyek/{id}/persyaratan/{req}/tidak-berlaku', [RequirementsController::class, 'waive'])->whereUuid('id')->where('req', '[0-9a-f]{32}')->middleware(['auth', 'capability:requirements.waive'])->name('adm.requirements.waive');
Route::post('/proyek/{id}/persyaratan/{req}/kelayakan', [RequirementsController::class, 'eligibility'])->whereUuid('id')->where('req', '[0-9a-f]{32}')->middleware(['auth', 'capability:requirements.set_waiver_eligibility'])->name('adm.requirements.eligibility');
Route::post('/proyek/{id}/persyaratan/{req}/longgarkan', [RequirementsController::class, 'relax'])->whereUuid('id')->where('req', '[0-9a-f]{32}')->middleware(['auth', 'capability:requirements.relax'])->name('adm.requirements.relax');
Route::post('/keputusan/{id}/ganti', [RequirementsController::class, 'supersede'])->where('id', '[0-9a-f]{32}')->middleware(['auth', 'capability:decisions.supersede'])->name('adm.decisions.supersede');
Route::get('/dokumen/kasus', [CorrectionCasesController::class, 'index'])->middleware(['auth', 'capability:projects.view'])->name('ops.cases');
Route::post('/dokumen/kasus/{id}/tutup', [CorrectionCasesController::class, 'close'])->whereUuid('id')->middleware(['auth', 'capability:cases.close'])->name('ops.cases.close');

// TASK-061: the Owner's two log pages, read-only (ROUTE-063; ROUTE-064; OD-14).
Route::get('/aktivitas/riwayat', [LogsController::class, 'history'])->middleware(['auth', 'capability:audit.view'])->name('ops.activity');
Route::get('/aktivitas/log-keamanan', [LogsController::class, 'security'])->middleware(['auth', 'capability:security_log.view'])->name('ops.security-log');
Route::post('/dokumen', [DocumentsController::class, 'save'])->middleware(['auth', 'capability:documents.issue'])->name('doc.documents.save');
Route::get('/dokumen/{id}', [DocumentsController::class, 'show'])->whereUuid('id')->middleware(['auth', 'capability:projects.view'])->name('doc.documents.show');
Route::post('/dokumen/{id}/terbitkan', [DocumentsController::class, 'issue'])->whereUuid('id')->middleware(['auth', 'capability:documents.issue'])->name('doc.documents.issue');
Route::post('/dokumen/{id}/revisi', [DocumentsController::class, 'revise'])->whereUuid('id')->middleware(['auth', 'capability:documents.correct'])->name('doc.documents.revise');
Route::post('/dokumen/{id}/batalkan', [DocumentsController::class, 'void'])->whereUuid('id')->middleware(['auth', 'capability:documents.correct'])->name('doc.documents.void');
Route::get('/berkas/{id}/tampil', [DocumentsController::class, 'rendition'])->middleware(['auth', 'access:s0'])->name('doc.files.rendition');
// Private files and polled states (TASK-022; ROUTE-226, ROUTE-227, ROUTE-285): every active account reaches the
// route, and the owning record or the page's query class decides at request time (DP-05; FL-07; PJ-08; PJ-15); a
// denied and an unknown record answer alike (AZ-11). The download is the user's own request (RB-04); the thumbnail
// and the status are background by their routes, reading the session and never extending it (RB-05; RV-06).
Route::get('/berkas/{id}/unduh', [FileController::class, 'download'])->middleware(['auth', 'access:s0'])->name('doc.files.download');
Route::get('/berkas/{id}/gambar-kecil', ['uses' => FileController::class.'@thumbnail', BackgroundRequest::ROUTE_FLAG => true])
    ->middleware(['auth', 'access:s0'])
    ->name('doc.files.thumbnail');
Route::get('/status/{kind}/{id}', ['uses' => StatusController::class, BackgroundRequest::ROUTE_FLAG => true])
    ->middleware(['auth', 'access:s0'])
    ->name('status.show');
// ROUTE-281 `Reporting Search` (TASK-016): the shell's type-ahead, PG-009 and every lookup inside a form; every
// active account (S0), each result under its own view capability (PJ-10); read-only, no `command_id`; WS-12's
// search limiter. It reads the session and never writes it back, so a search beside a command never erases its answer.
Route::post('/cari', ['uses' => SearchController::class, BackgroundRequest::READS_SESSION_ONLY => true])
    ->middleware(['auth', 'access:s0', 'throttle:search'])
    ->name('reporting.search');

// ROUTE-005, PG-005 (TASK-016): the exception handler's answer to an address no route resolves, through the web
// group so that a signed-in account sees it in the shell — every request it answers (AZ-11), in EN-03's
// authenticated group like every other route; a guest's document request goes to sign-in.
Route::fallback(SystemPageController::class)
    ->middleware(['auth', 'access:s0'])
    ->name('system.page');
