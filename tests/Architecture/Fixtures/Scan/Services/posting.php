<?php

// A posting service editing a balance without GuardUpdate (ARCHITECTURE §7, §19).
DB::table('stock_lot_balances')->update(['on_hand' => 0]);
