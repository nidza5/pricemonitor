<?php

namespace Patagona\Pricemonitor\Core\Sync\TransactionHistory;


abstract class TransactionHistoryType
{
    const IMPORT_PRICES = 'import_prices';
    const EXPORT_PRODUCTS = 'export_products';
}