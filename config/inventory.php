<?php

return [

    /*
    | Products whose stock is BELOW this number are treated as "low stock".
    | Change it in .env (LOW_STOCK_THRESHOLD) or pass ?threshold=5 to the API.
    */
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 10),

];
