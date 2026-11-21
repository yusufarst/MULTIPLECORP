<?php

// Go-live's store (TASK-077; INFRASTRUCTURE §4.2, DIR-101; HO-32): one file on the deployment's persistent private
// storage — the `storage` volume every web and worker process mounts —, created once by `numbering:go-live`.
return [
    'go_live_file' => env('NUMBERING_GO_LIVE_FILE', storage_path('app/private/numbering-go-live')),
];
