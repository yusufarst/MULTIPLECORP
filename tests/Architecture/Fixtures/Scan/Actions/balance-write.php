<?php

// A direct edit of a balance outside its posting service (ARCHITECTURE §7, §19).
use App\Modules\INV\Models\StockLotBalance;

DB::table('stock_lot_balances')->update(['on_hand' => 0]);
StockLotBalance::query()->update(['on_hand' => 0]);
