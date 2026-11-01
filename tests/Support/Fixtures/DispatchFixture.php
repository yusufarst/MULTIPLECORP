<?php

namespace Tests\Support\Fixtures;

use App\Modules\IAM\Models\User;
use App\Modules\ORG\Enums\SequenceType;
use App\Modules\ORG\Models\Company;
use App\Support\BusinessDate;
use Carbon\CarbonImmutable;
use Database\Factories\DOC\DocumentTypeFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tests\Support\Authorization;
use Tests\Support\Numbering;

/**
 * TL-09–TL-12 (TASK-041): over StockFixture's world, DOC-03's numbering for the Surat Jalan's draft; the signed-in plain account's dispatch capability without the inter-company authority, for the routed refusal (UXS-22).
 */
final class DispatchFixture
{
    public static function create(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $year = substr(BusinessDate::today(), 0, 4);
        Numbering::scheme($company, SequenceType::Doc03, "{$year}-01-01", 'YEAR', 'UJI/SJ/{tahun}/{nomor:4}');
        Numbering::started($company, SequenceType::Doc03, CarbonImmutable::parse(((int) $year - 1).'-12-31 03:00:00', 'UTC'));
        DocumentTypeFactory::ensure('DOC-03');

        return response()->json(['ok' => true]);
    }

    /** A Company-A-only Admin with `stock.dispatch` and no `stock.allocate_intercompany`, signed in from here on. */
    public static function plain(Request $request): JsonResponse
    {
        $company = Company::query()->where('public_id', (string) $request->input('company'))->firstOrFail();
        $user = User::query()->findOrFail(Authorization::admin([$company->id], ['stock.view', 'projects.view', 'stock.dispatch'])->id);
        auth()->login($user);
        $request->session()->regenerate();

        return response()->json(['ok' => true]);
    }
}
