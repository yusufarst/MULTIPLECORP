<?php

// MAX(number) + 1 (ARCHITECTURE §19).
$next = DB::table('documents')->max('number') + 1;
