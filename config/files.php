<?php

// TASK-022: the private files, the document renderer and the jobs that derive work from them (ARCHITECTURE
// §11–§12; SECURITY FL-01–FL-11; CONCURRENCY_IDEMPOTENCY §15). The values P9 sizes are read here (HO-22).
return [

    // The private disk of config/filesystems.php that holds every business file (FL-07).
    'disk' => env('FILES_DISK', 'files'),

    // FL-11's optional scanner stays off until TASK-079's capacity decision adopts one (H9-05; P9Q-06).
    'scanner' => null,

    // The renderer of FL-08 (DIR-099 C1): WeasyPrint through the project's own script, which refuses the network
    // and every file outside the allowlisted assets; the fonts it embeds live under `assets`.
    'renderer' => [
        'python' => env('RENDERER_PYTHON', '/usr/bin/python3'),
        'script' => resource_path('renderer/render.py'),
        'assets' => resource_path('renderer/assets'),
        'timeout_seconds' => (int) env('RENDERER_TIMEOUT', 90),
        'memory_megabytes' => (int) env('RENDERER_MEMORY_MB', 768),
    ],

    'renditions' => [
        // JB-01: the job's timeout and the unique-job lock's lifetime above it plus the queue wait it tolerates.
        'timeout_seconds' => 120,
        'unique_for_seconds' => (int) env('RENDITION_UNIQUE_SECONDS', 600),
        'attempts' => 5,
    ],

];
