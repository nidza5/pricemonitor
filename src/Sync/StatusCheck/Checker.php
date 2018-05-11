<?php

namespace Patagona\Pricemonitor\Core\Sync\StatusCheck;

use Exception;
use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\Proxy;
use Patagona\Pricemonitor\Core\Sync\Queue\DelayedJob;
use Patagona\Pricemonitor\Core\Sync\Queue\Queue;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionFailedDTO;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistory;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStatus;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryType;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\ValidationException;

class Checker
{

    const RECHECK_DELAY_PERIOD = 120;

    private $proxy;
    protected $contractId;

    /** @var TransactionHistory  */
    private $transactionHistory;

    public function __construct(Proxy $proxy, $contractId, TransactionHistory $transactionHistory)
    {
        $this->proxy = $proxy;
        $this->contractId = $contractId;
        $this->transactionHistory = $transactionHistory;
    }

    /**
     * Executes status checking for concrete export task
     *
     * @param $taskId
     * @throws Exception
     * @throws \Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStorageException
     */
    public function execute($taskId)
    {
        $failedItemPricemonitorMessages = [];
        $failedItems = [];
        $exportStatus = $this->proxy->getExportStatus($this->contractId, $taskId);

        if (!empty($exportStatus['failures'])) {
            foreach ($exportStatus['failures'] as $failureIndex => $failure) {
                if (!isset($failure['attributes']['gtin'])) {
                    throw new Exception('Get export status API response "failures" field is in bad format. Gtin not set.');
                }

                if (!isset($failedItemPricemonitorMessages[$failure['attributes']['gtin']]) ||
                    (isset($failure['messageId']) &&
                    !in_array($failure['messageId'], $failedItemPricemonitorMessages[$failure['attributes']['gtin']]))
                ) {
                    $failedItemPricemonitorMessages[$failure['attributes']['gtin']][] = $failure['messageId'];
                    $failedItems[$failure['attributes']['gtin']][] =
                        'Export failed for product with GTIN: ' . $failure['attributes']['gtin'] . '. ' .
                        'Failure message: ' . $failure['messageId'];
                }
            }
        }

        if (!empty($failedItems)) {
            $this->logErrors($failedItems);
        }

        $this->transactionHistory->updateTransaction(
            $this->contractId,
            TransactionHistoryType::EXPORT_PRODUCTS,
            null,
            [],
            $taskId,
            $this->createFailedProducts($failedItemPricemonitorMessages)
        );
        
        if ($exportStatus['state'] === 'pending' || $exportStatus['state'] === 'executing') {
            $queue = new Queue('StatusChecking');
            $queue->enqueue(new DelayedJob(
                new Job($this->contractId, $taskId),
                $queue->getName(),
                self::RECHECK_DELAY_PERIOD
            ));
        } else {
            $status = TransactionHistoryStatus::FAILED;

            if ($exportStatus['state'] === 'succeeded') {
                $status = TransactionHistoryStatus::FINISHED;
            }

            $this->transactionHistory->finishTransaction(
                $this->contractId,
                $status,
                TransactionHistoryType::EXPORT_PRODUCTS,
                null,
                $taskId
            );
        }
    }

    /**
     * @param array $failedItems
     *
     * @return TransactionFailedDTO[]
     *
     * @throws ValidationException
     */
    private function createFailedProducts(array $failedItems)
    {
        $failedProducts = array();
        foreach ($failedItems as $failedItemKey => $failedItem) {
            if (count($failedItems[$failedItemKey]) > 1) {
                $failedItems[$failedItemKey][0] = '*' . $failedItems[$failedItemKey][0];
            }

            $failedProduct = new TransactionFailedDTO(
                $failedItemKey,
                implode(" *", $failedItems[$failedItemKey]),
                TransactionHistoryStatus::FAILED,
                isset($failedItem['attributes']['name']) ? $failedItem['attributes']['name'] : null,
                isset($failedItem['attributes']['referencePrice']) ? $failedItem['attributes']['referencePrice'] : null,
                isset($failedItem['attributes']['minPriceBoundary']) ? $failedItem['attributes']['minPriceBoundary'] : null,
                isset($failedItem['attributes']['maxPriceBoundary']) ? $failedItem['attributes']['maxPriceBoundary'] : null
            );
            $failedProducts[] = $failedProduct;
        }

        return $failedProducts;
    }

    /**
     * Log status checking errors using Logger
     *
     * @param $failedItems
     */
    private function logErrors($failedItems)
    {
        foreach ($failedItems as $errors) {
            foreach ($errors as $errorMessage) {
                Logger::logError($errorMessage, $this->contractId);
            }
        }
    }

}