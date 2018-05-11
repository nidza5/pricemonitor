<?php

namespace Patagona\Pricemonitor\Core\Sync\PriceImport;

use Patagona\Pricemonitor\Core\Infrastructure\Logger;
use Patagona\Pricemonitor\Core\Infrastructure\Proxy;
use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Sync\Filter\Condition;
use Patagona\Pricemonitor\Core\Sync\Filter\Expression;
use Patagona\Pricemonitor\Core\Sync\Filter\Filter;
use Patagona\Pricemonitor\Core\Sync\Filter\FilterRepository;
use Patagona\Pricemonitor\Core\Sync\Filter\Group;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionFailedDTO;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistory;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryDetail;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStatus;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryType;

class Importer
{
    const BATCH_SIZE = 1000;

    private $proxy;
    private $contractId;
    private $priceService;
    private $configService;
    private $mapperService;
    private $productService;
    private $filterRepository;

    /** @var TransactionHistory  */
    private $transactionHistory;

    public function __construct(Proxy $proxy, $contractId, TransactionHistory $transactionHistory)
    {
        $this->proxy = $proxy;
        $this->contractId = $contractId;
        $this->priceService = ServiceRegister::getPriceService();
        $this->configService = ServiceRegister::getConfigService();
        $this->mapperService = ServiceRegister::getMapperService();
        $this->productService = ServiceRegister::getProductService();
        $this->transactionHistory = $transactionHistory;
        $this->filterRepository = new FilterRepository();
    }

    /**
     * Executes price import process
     *
     * @param int $transactionId
     *
     * @throws \Exception
     */
    public function execute($transactionId)
    {
        $importStartedAtTimestamp = (new \DateTime())->getTimestamp();

        try {
            $errors = $this->updatePrices($transactionId, $this->getLastImportDate());
            if (!empty($errors)) {
                $this->logErrors($errors);
            }

            $this->configService->set("contract_{$this->contractId}.lastPriceImportTimestamp", $importStartedAtTimestamp);
        } catch (\Exception $ex) {
            $this->transactionHistory->finishTransaction(
                $this->contractId,
                TransactionHistoryStatus::FAILED,
                TransactionHistoryType::IMPORT_PRICES,
                $transactionId,
                null,
                $ex->getMessage()
            );
            throw $ex;
        }
    }

    /**
     * Updates price in batches using price integration service
     *
     * @param $transactionId
     * @param \DateTime|null $lastImportDate Date time of last price import (leave empty or set null if there was no imports)
     * @return array List of import errors from price service
     */
    private function updatePrices($transactionId, \DateTime $lastImportDate = null)
    {
        $start = 0;
        $allNotImportedPrices = [];

        $prices = $this->proxy->importPrices($this->contractId, $start, self::BATCH_SIZE, $lastImportDate);

        while (!empty($prices)) {
            /** @var TransactionHistoryDetail[] $formattedTransactionDetails */
            $transactionDetails = $this->transactionHistory->updateTransaction(
                $this->contractId,
                TransactionHistoryType::IMPORT_PRICES,
                $transactionId,
                $this->createImportDetailsBasedOnPrices($prices, $transactionId),
                null
            );

            $batchNotImportedPrices = $this->createNotImportedPrices($prices, $transactionDetails);
            $allNotImportedPrices = array_merge($allNotImportedPrices, $batchNotImportedPrices);

            $failedItems = $this->createFailedItems($batchNotImportedPrices);

            $this->transactionHistory->updateTransaction(
                $this->contractId,
                TransactionHistoryType::IMPORT_PRICES,
                $transactionId,
                $transactionDetails,
                null,
                $failedItems
            );

            $start += self::BATCH_SIZE;
            $prices = $this->proxy->importPrices($this->contractId, $start, self::BATCH_SIZE, $lastImportDate);
        }

        $this->transactionHistory->finishTransaction(
            $this->contractId,
            TransactionHistoryStatus::FINISHED,
            TransactionHistoryType::IMPORT_PRICES,
            $transactionId,
            null
        );

        return $allNotImportedPrices;
    }

    private function createNotImportedPrices($prices, &$transactionDetails)
    {
        $filterPricesStart = 0;
        $filterPricesBatchSize = 10;
        $notImportedPrices = [];
        $productIdentifierCode = $this->productService->getProductIdentifier();

        do {
            $batchPrices = array_slice($prices, $filterPricesStart, $filterPricesBatchSize);
            $filteredShopProducts = [];

            $filter = $this->createPriceImportFilter($batchPrices, $productIdentifierCode);

            if ($filter !== null) {
                $filteredShopProducts = $this->productService->exportProducts(
                    $this->contractId,
                    $this->createPriceImportFilter($batchPrices, $productIdentifierCode),
                    array($productIdentifierCode)
                );
            }
            
            $allProducts = $this->productService->exportProducts(
                $this->contractId,
                $this->createAllProductsFilter($batchPrices, $productIdentifierCode),
                array($productIdentifierCode, $productIdentifierCode)
            );

            $batchPrices = $this->fillBatchPricesEmptyFields($batchPrices, $allProducts);

            if ($filter !== null && count($filteredShopProducts) === 0) {
                foreach ($batchPrices as $batchPrice) {
                    $notImportedPrices[] = [
                        'productId' => $batchPrice['identifier'],
                        'errors' => [],
                        'name' => $batchPrice['name'],
                        'status' => TransactionHistoryStatus::FILTERED_OUT
                    ];
                }
            } else {
                $notImportedPrices = array_merge(
                    $notImportedPrices,
                    $this->createNotImportedFilteredPrices($batchPrices, $filteredShopProducts, $transactionDetails)
                );
            }

            $batchPrices = $this->removeFilteredPricesFromBatch($batchPrices, $notImportedPrices);

            $notImportedPrices = array_merge(
                $notImportedPrices,
                $this->priceService->updatePrices($this->contractId, $batchPrices)
            );
            $filterPricesStart += $filterPricesBatchSize;

        } while ($filterPricesStart < count($prices));

        return $notImportedPrices;
    }

    private function fillBatchPricesEmptyFields($batchPrices, $allProducts)
    {
        $productIdentifierCode = $this->productService->getProductIdentifier();

        foreach ($batchPrices as $batchPriceIndex => &$batchPrice) {
            $batchPrice['name'] = !empty($batchPrice['name']) ? $batchPrice['name'] : '';
            foreach ($allProducts as $product) {
                $pricemonitorProduct = $this->mapperService->convertToPricemonitor($this->contractId, $product);
                if ($product[$productIdentifierCode] === $batchPrice['identifier'] && empty($batchPrice['name'])) {
                    $batchPrice['name'] = !empty($pricemonitorProduct['name']) ? $pricemonitorProduct['name'] : '';
                    break;
                }
            }
        }

        return $batchPrices;
    }

    /**
     * @param $batchPrices
     * @param $filteredShopProducts
     * @param TransactionHistoryDetail[] $transactionDetails
     * @return array
     */
    private function createNotImportedFilteredPrices($batchPrices, $filteredShopProducts, &$transactionDetails)
    {
        $notImportedPrices = [];

        foreach ($batchPrices as $batchPriceIndex => &$batchPrice) {
            foreach ($transactionDetails as &$transactionDetail) {
                if ($batchPrice['identifier'] == $transactionDetail->getProductId()) {
                    $transactionDetail->setProductName($batchPrice['name']);
                }
            }
        }

        if (count($filteredShopProducts) === 0) {
            return [];
        }

        foreach ($batchPrices as $batchPriceIndex => &$batchPrice) {
            $productShouldBeImported = false;
            
            foreach ($filteredShopProducts as $filteredProduct) {
                if (!isset($batchPrice['identifier'])) {
                    continue;
                }

                $ids = is_array($filteredProduct) ? array_values($filteredProduct) : null;
                $productId = !empty($ids[0]) ? $ids[0] : null;

                $filteredProduct = $this->mapperService->convertToPricemonitor($this->contractId, $filteredProduct);

                if (!empty($filteredProduct['productId'])) {
                    $productId = $filteredProduct['productId'];
                }

                if ($batchPrice['identifier'] == $productId) {
                    $productShouldBeImported = true;
                    break;
                }
            }

            if (!$productShouldBeImported) {
                $notImportedPrices[] = [
                    'productId' => $batchPrice['identifier'],
                    'errors' => [],
                    'name' => $batchPrice['name'],
                    'status' => TransactionHistoryStatus::FILTERED_OUT
                ];
            }
        }

        return $notImportedPrices;
    }

    private function removeFilteredPricesFromBatch($batchPrices, $notImportedPrices)
    {
        foreach ($batchPrices as $batchPriceIndex => $batchPrice) {
            foreach ($notImportedPrices as $notImportedPrice) {
                if ($batchPrice['identifier'] == $notImportedPrice['productId']) {
                    unset($batchPrices[$batchPriceIndex]);
                    break;
                }
            }
        }

        return $batchPrices;
    }

    /**
     * @param $batchPrices
     * @param $productIdentifierCode
     *
     * @return null|\Patagona\Pricemonitor\Core\Sync\Filter\Filter
     *
     * @throws \Exception
     */
    private function createPriceImportFilter($batchPrices, $productIdentifierCode)
    {
        $filter = $this->filterRepository->getFilter($this->contractId, TransactionHistoryType::IMPORT_PRICES);

        if ($filter === null || count($filter->getExpressions()) === 0) {
            return null;
        }

        $group = new Group('Group for prices');

        foreach ($batchPrices as $batchPrice) {
            $group->addExpression(new Expression(
                $productIdentifierCode,
                Condition::EQUAL,
                'string',
                [$batchPrice['identifier']]
            ));
        }

        if (count($group->getExpressions()) > 0) {
            $filter->addExpression($group);
        }

        return $filter;
    }

    private function createAllProductsFilter($batchPrices, $productIdentifierCode)
    {
        $group = new Group('Group for prices');

        foreach ($batchPrices as $batchPrice) {
            $group->addExpression(new Expression(
                $productIdentifierCode,
                Condition::EQUAL,
                'string',
                [$batchPrice['identifier']]
            ));
        }
        
        $filter = new Filter('Filter', TransactionHistoryType::IMPORT_PRICES);

        if (count($group->getExpressions()) > 0) {
            $filter->addExpression($group);
        }

        return $filter;
    }

    private function createImportDetailsBasedOnPrices($prices, $importId)
    {
        $importDetails = [];

        foreach ($prices as $price) {
            $importDetail = new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $importId,
                null,
                $price['identifier'],
                $price['gtin']
            );

            $importDetail->setUpdatedInShop(false);
            $importDetails[] = $importDetail;
        }

        return $importDetails;
    }

    private function createFailedItems($errorList)
    {
        $failedItems = [];

        foreach ($errorList as $errorProduct) {
            $failedItem = new TransactionFailedDTO(
                $errorProduct['productId'],
                implode(' ', $errorProduct['errors']),
                $errorProduct['status'],
                isset($errorProduct['name']) ? $errorProduct['name'] : null,
                null,
                null,
                null
            );
            $failedItems[] = $failedItem;
        }

        return $failedItems;
    }
    /**
     * Log price service errors using Logger
     *
     * @param array $errors
     */
    private function logErrors($errors)
    {
        foreach ($errors as $error) {
            foreach ($error['errors'] as $errorMessage) {
                Logger::logError($errorMessage, $this->contractId);
            }
        }
    }

    /**
     * Gets last import date from config or null if there still was no imports
     *
     * @return \DateTime|null Last price import date
     */
    private function getLastImportDate()
    {
        $lastImportDate = null;

        $lastImportTimestamp = $this->configService->get("contract_{$this->contractId}.lastPriceImportTimestamp");
        if (!empty($lastImportTimestamp)) {
            $lastImportDate = new \DateTime("@{$lastImportTimestamp}");
        }

        return $lastImportDate;
    }
}