<?php

namespace Tests\Support;

/**
 * The data-path coverage matrix of PERMISSIONS_MATRIX §11 as an executable register (TASK-012; SM-01): each
 * path with the tests that prove its projection and its denial where the path exists, and, where it does
 * not exist yet, the Tasks that build it and add those tests. A test holds the register to the document.
 * TASK-069 completed it once every building Task was DONE: each path names its proving tests and no Task.
 */
final class DataPaths
{
    /**
     * DP id => [the tests proving it now, the Tasks that build the rest]
     *
     * @var array<string, array{0: list<string>, 1: list<string>}>
     */
    public const PATHS = [
        'DP-01' => [['tests/Integration/Authorization/CompanyScopeTest.php'], []],
        'DP-02' => [['tests/Integration/Audit/LogQueriesTest.php', 'tests/Integration/Authorization/CompanyScopeTest.php', 'tests/Authorization/CompanyViewTest.php', 'tests/Feature/SearchRouteTest.php', 'tests/Feature/Reporting/ReportsPagesTest.php'], []],
        'DP-03' => [['tests/Integration/Authorization/CompanyScopeTest.php', 'tests/Integration/Authorization/RevocationRaceTest.php', 'tests/Integration/Projection/ScopedReferencesTest.php', 'tests/Authorization/PurchasesAccessTest.php', 'tests/Authorization/ReceivingAccessTest.php'], []],
        'DP-04' => [['tests/Routes/RouteAuditTest.php', 'tests/Routes/RouteContractsTest.php'], []],
        'DP-05' => [['tests/Integration/Reporting/ExportJobTest.php', 'tests/Authorization/FileAccessTest.php', 'tests/Authorization/EvidenceAccessTest.php', 'tests/Authorization/ImportAccessTest.php', 'tests/Feature/Files/FileRoutesTest.php'], []],
        'DP-06' => [['tests/Integration/Reporting/SearchTest.php', 'tests/Feature/SearchRouteTest.php'], []],
        'DP-07' => [['tests/Integration/Reporting/ExportJobTest.php', 'tests/Authorization/ReportsAccessTest.php'], []],
        'DP-08' => [['tests/Integration/Projection/ScopedAggregatesTest.php', 'tests/Authorization/CompanyViewTest.php', 'tests/Feature/Reporting/HomePagesTest.php'], []],
        'DP-09' => [['tests/Integration/Reporting/ExportJobTest.php'], []],
        'DP-10' => [['tests/Architecture/CacheUseTest.php'], []],
        'DP-11' => [['tests/Feature/Reporting/HomePagesTest.php', 'tests/Interaction/LogsTest.php'], []],
        'DP-12' => [['tests/Authorization/LogQueriesTest.php'], []],
        'DP-13' => [['tests/Feature/DOC/EvidenceTest.php', 'tests/Authorization/PaymentsAccessTest.php', 'tests/Authorization/DeductionsAccessTest.php'], []],
        'DP-14' => [['tests/Integration/Authorization/RevocationRaceTest.php', 'tests/Feature/CapabilityRoutesTest.php', 'tests/Feature/CommandReplayTest.php'], []],
        'DP-15' => [['tests/Feature/ProjectedPropsTest.php', 'tests/Interaction/CapabilityHintsTest.php', 'tests/Interaction/ShellTest.php'], []],
        'DP-16' => [['tests/Feature/CommandReplayTest.php', 'tests/Integration/Command/EnvelopeTest.php', 'tests/Authorization/PaymentsAccessTest.php'], []],
        'DP-17' => [['tests/Authorization/FileAccessTest.php', 'tests/Integration/Jobs/RenditionPipelineTest.php'], []],
        'DP-18' => [['tests/Authorization/ImportAccessTest.php', 'tests/Feature/Import/OpeningImportPagesTest.php'], []],
        'DP-19' => [['tests/Browser/E2E/reports.spec.ts', 'tests/Integration/Authorization/CompanyScopeTest.php', 'tests/Authorization/FileAccessTest.php'], []],
        'DP-20' => [['tests/Integration/Authorization/CompanyScopeTest.php', 'tests/Feature/SystemAnswersTest.php'], []],
    ];
}
