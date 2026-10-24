<?php

// Fixture: a query that disables the global scopes, which EN-04 prohibits outside query classes.

$rows = Invoice::query()->withoutGlobalScopes()->get();
