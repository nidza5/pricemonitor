<?php

namespace Patagona\Pricemonitor\Core\Sync\ProductExport;

use Exception;
use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\Proxy;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Sync\Filter\Filter;
use Patagona\Pricemonitor\Core\Sync\Filter\FilterRepository;
use Patagona\Pricemonitor\Core\Sync\Queue\Queue;
use Patagona\Pricemonitor\Core\Sync\StatusCheck\Job as StatusCheckJob;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistory;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryDetail;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStatus;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryType;

class Exporter
{
    private $proxy;
    private $contractId;
    private $productService;
    private $mapperService;

    /** @var  TransactionHistory */
    private $transactionHistory;

    public function __construct(Proxy $proxy, $contractId, TransactionHistory $transactionHistory)
    {
        $this->proxy = $proxy;
        $this->contractId = $contractId;
        $this->productService = ServiceRegister::getProductService();
        $this->mapperService = ServiceRegister::getMapperService();
        $this->transactionHistory = $transactionHistory;
    }

    /**
     * Executes product export process
     *
     * @param $transactionId
     * @throws Exception
     */
    public function execute($transactionId)
    {
        try {
            $exportUniqueIdentifier = $this->exportProducts($transactionId);
            $this->enqueueStatusCheckerJob($exportUniqueIdentifier);
        } catch (Exception $ex) {
            $this->transactionHistory->finishTransaction(
                $this->contractId,
                TransactionHistoryStatus::FAILED,
                TransactionHistoryType::EXPORT_PRODUCTS,
                $transactionId,
                null,
                $ex->getMessage()
            );
            throw $ex;
        }
    }

    /**
     * Exports products to Pricemonitor using product service
     *
     * @param $exportId
     * @return array Export product info in format
     *      [
     * 'total' => total_nubmer_of_shop_products,
     * 'failed' => count_of_failed_items.
     * 'taskId' => product_export_task_id
     * ]
     */
    private function exportProducts($exportId)
    {
        $products = [];
        
        /** @var Filter $filter */
        $filter = (new FilterRepository())->getFilter($this->contractId, TransactionHistoryType::EXPORT_PRODUCTS);
        $shopProducts = $this->productService->exportProducts($this->contractId, $filter);

        foreach ($shopProducts as $shopProduct) {
            $product = $this->mapperService->convertToPricemonitor($this->contractId, $shopProduct);
            $this->logProductValidationWarnings($product);
            $products[] = $product;
        }
        
        $taskId = $this->proxy->exportProducts($this->contractId, $products);
        $this->transactionHistory->updateTransaction(
            $this->contractId, 
            TransactionHistoryType::EXPORT_PRODUCTS, 
            $exportId, 
            $this->createTransactionDetails($exportId, $products), 
            $taskId
        );

        return $taskId;
    }

    /**
     * Creates and enqueues StatusCheckJob to the "StatusChecking" queue
     *
     * @param string $exportTaskId Export products task id from Pricemonitor API
     */
    private function enqueueStatusCheckerJob($exportTaskId)
    {
        $queue = new Queue('StatusChecking');
        $queue->enqueue(new StatusCheckJob($this->contractId, $exportTaskId));
    }

    /**
     * Validates product data and logs any validation errors
     *
     * @param array $product Pricemonitor product data to validate
     *
     * @return boolean True if product is valid, false otherwise
     */
    private function logProductValidationWarnings($product)
    {
        $mandatoryAttributes = ['gtin', 'name', 'productId', 'referencePrice', 'minPriceBoundary', 'maxPriceBoundary'];
        $missingMandatoryAttributes = [];
        foreach ($mandatoryAttributes as $mandatoryAttribute) {
            if (
                !isset($product[$mandatoryAttribute]) ||
                is_null($product[$mandatoryAttribute]) ||
                '' === $product[$mandatoryAttribute]
            ) {
                $missingMandatoryAttributes[] = $mandatoryAttribute;
            }
        }

        if (!empty($missingMandatoryAttributes)) {
            $badProduct1Info = json_encode($product);
            $missingAttributesInfo = join(', ', $missingMandatoryAttributes);
            Logger::logWarning(
                "Missing required attributes {$missingAttributesInfo}. Failed to export product {$badProduct1Info}.",
                $this->contractId
            );

            return;
        }

        if (floatval($product['minPriceBoundary']) > floatval($product['maxPriceBoundary'])) {
            $badProduct1Info = json_encode($product);
            Logger::logWarning(
                "Value minPriceBoundary is greater than maxPriceBoundary value. Failed to export product {$badProduct1Info}.",
                $this->contractId
            );

            return;
        }

        if (!is_numeric($product['gtin']) && (intval($product['gtin']) <= 0)) {
            $badProduct1Info = json_encode($product);
            Logger::logWarning(
                "Value {$product['gtin']} for gtin is invalid. Failed to export product {$badProduct1Info}.",
                $this->contractId
            );
        }
    }

    /**
     * @param $transactionId
     * @param $products
     * 
     * @return TransactionHistoryDetail[]
     */
    private function createTransactionDetails($transactionId, $products)
    {
        /** @var TransactionHistoryDetail[] $transactionDetails */
        $transactionDetails = [];
        
        foreach ($products as $product) {
            /** @var TransactionHistoryDetail $transactionDetail */
            $transactionDetail = new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS, 
                new \DateTime(),
                null,
                $transactionId, 
                null,
                isset($product['productId']) ? $product['productId'] : null,
                isset($product['gtin']) ? $product['gtin'] : null,
                isset($product['name']) ? $product['name']: null,
                isset($product['referencePrice']) ? (float)$product['referencePrice'] : null,
                isset($product['minPriceBoundary']) ? (float)$product['minPriceBoundary'] : null,
                isset($product['maxPriceBoundary']) ? (float)$product['maxPriceBoundary'] : null
            );

            $transactionDetail->setUpdatedInShop(false);

            $transactionDetails[] = $transactionDetail; 
        }

        return $transactionDetails;
    }
}