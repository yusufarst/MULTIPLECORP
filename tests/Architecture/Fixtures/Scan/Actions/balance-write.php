<?php

// A direct edit of a balance (ARCHITECTURE §19).
DB::table('stock_lot_balances')->update(['on_hand' => 0]);
