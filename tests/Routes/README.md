# TL-07 — Route integrity

SM-02's route audit and the route crawl of [TEST_STRATEGY §8](../../docs/08-testing/TEST_STRATEGY.md#8-route-navigation-and-interaction-integrity), run by `scripts/verify.sh` as the layer *TL-07 Route integrity*:

- `RouteAuditTest` — every registered route declares one requirement EN-03 accepts, no Fortify route registered (TASK-011).
- `RouteContractsTest` — every registered route and every ROUTE- row of ROUTE_CONTRACTS one to one (GAP-035; TASK-069).
- `RouteCrawlTest` — every registered route requested as an actor allowed to use it, its record from `tests/Support/CrawlWorld.php`; no unexpected 404, 405 or 500, its counts in `storage/logs/verify/route-crawl.json` (TASK-069).
- `RouteDeclarationsTest` — S0, SELF and the enrolment-only session behave as declared (SM-02; TASK-069).

SM-04's refusal and admission at every capability route is the authorization layer's (`tests/Authorization/RouteDenialsTest.php`).
