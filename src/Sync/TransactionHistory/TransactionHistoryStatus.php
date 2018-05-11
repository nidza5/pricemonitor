<?php

namespace Patagona\Pricemonitor\Core\Sync\TransactionHistory;


abstract class TransactionHistoryStatus
{
    const IN_PROGRESS = 'in_progress';
    const FINISHED = 'finished';
    const FAILED = 'failed';
    const FILTERED_OUT = 'filtered_out';
}