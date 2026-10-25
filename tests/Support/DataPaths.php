<?php

namespace Tests\Support;

/**
 * The data-path coverage matrix of PERMISSIONS_MATRIX §11 as an executable register (TASK-012; SM-01): each
 * path with the tests that prove its projection and its denial where the path exists, and, where it does
 * not exist yet, the Tasks that build it and add those tests. A test holds the register to the document.
 */
final class DataPaths
{
    /**
     * DP id => [the tests proving it now, the Tasks that build the rest of the path]
     *
     * @var array<string, array{0: list<string>, 1: list<string>}>
     */
    public const PATHS = [
        'DP-01' => [['tests/Integration/Authorization/CompanyScopeTest.php'], []],
        'DP-02' => [['tests/Integration/Audit/LogQueriesTest.php', 'tests/Integration/Authorization/CompanyScopeTest.php', 'tests/Authorization/CompanyViewTest.php', 'tests/Feature/SearchRouteTest.php'], []],
        'DP-03' => [['tests/Integration/Authorization/CompanyScopeTest.php', 'tests/Integration/Authorization/RevocationRaceTest.php', 'tests/Integration/Projection/ScopedReferencesTest.php'], ['TASK-013']],
        'DP-04' => [['tests/Routes/RouteAuditTest.php'], []],
        'DP-05' => [[], ['TASK-022', 'TASK-029', 'TASK-030', 'TASK-057', 'TASK-060']],
        'DP-06' => [['tests/Integration/Reporting/SearchTest.php'], ['TASK-059']],
        'DP-07' => [[], ['TASK-022', 'TASK-060']],
        'DP-08' => [['tests/Integration/Projection/ScopedAggregatesTest.php', 'tests/Authorization/CompanyViewTest.php'], ['TASK-058']],
        'DP-09' => [[], ['TASK-017', 'TASK-060']],
        'DP-10' => [[], ['TASK-058']],
        'DP-11' => [[], ['TASK-058', 'TASK-061']],
        'DP-12' => [['tests/Authorization/LogQueriesTest.php'], ['TASK-061']],
        'DP-13' => [[], ['TASK-022', 'TASK-030', 'TASK-051', 'TASK-052']],
        'DP-14' => [['tests/Integration/Authorization/RevocationRaceTest.php', 'tests/Feature/CapabilityRoutesTest.php'], ['TASK-017']],
        'DP-15' => [['tests/Feature/ProjectedPropsTest.php', 'tests/Interaction/CapabilityHintsTest.php', 'tests/Interaction/ShellTest.php'], []],
        'DP-16' => [[], ['TASK-013', 'TASK-051', 'TASK-069']],
        'DP-17' => [[], ['TASK-022', 'TASK-025', 'TASK-029']],
        'DP-18' => [[], ['TASK-057', 'TASK-069']],
        'DP-19' => [[], ['TASK-029', 'TASK-043', 'TASK-060', 'TASK-061']],
        'DP-20' => [['tests/Integration/Authorization/CompanyScopeTest.php', 'tests/Feature/SystemAnswersTest.php'], []],
    ];
}
