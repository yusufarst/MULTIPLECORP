<?php

// A batched job: what JB-07 forbids (ARCHITECTURE §11).

use Illuminate\Support\Facades\Bus;

Bus::batch([])->dispatch();
