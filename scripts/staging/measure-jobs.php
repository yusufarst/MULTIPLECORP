<?php

// PFM-16's measurement on staging (TASK-068 for TASK-079; PERFORMANCE_TARGETS PFM-16; QB-15; QB-17; PF-28): the durations of
// the renditions and exports the operator has just run through the product — issue to READY for a rendition (30 s at 1x),
// waiting and generation for an export (100,000 rows within 60 s at 10x) —, read from their durable rows. Read only: it
// generates no data and writes nothing; the operator runs the export of the size PFM-16 names and states its rows. In the
// `app` container:
//
//   php scripts/staging/measure-jobs.php [how many of each, 10 by default]

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$limit = max(1, (int) ($argv[1] ?? 10));

printf("PFM-16 on %s — the latest %d of each\n", gethostname(), $limit);
printf("renditions, issue to READY (QB-17: 30 s at 1x):\n");

// From the version's first attempt — its issue — to the attempt that reached READY: a retry is a new row of its own, so
// the failed attempts and their back-off count in the time (QB-17).
foreach (DB::select("select r.document_version_id, r.attempt_no, extract(epoch from r.updated_at - f.recorded_at) as seconds from document_renditions r join lateral (select min(x.recorded_at) as recorded_at from document_renditions x where x.document_version_id = r.document_version_id) f on true where r.state = 'READY' order by r.id desc limit ?", [$limit]) as $row) {
    printf("  version %d, READY at attempt %d: %.1f s from its issue\n", $row->document_version_id, $row->attempt_no, $row->seconds);
}

printf("exports, waiting and generation (QB-15: 100,000 rows within 60 s at 10x):\n");

foreach (DB::select("select e.id, e.report_code, extract(epoch from e.claimed_at - e.recorded_at) as waited, extract(epoch from f.recorded_at - e.claimed_at) as generated, f.size_bytes from export_requests e join file_objects f on f.id = e.file_object_id where e.state = 'READY' order by e.recorded_at desc limit ?", [$limit]) as $row) {
    printf("  export %s (%s): waited %.1f s, generated in %.1f s, %d bytes\n", $row->id, $row->report_code, $row->waited, $row->generated, $row->size_bytes);
}
