<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;

use Patagona\Pricemonitor\Core\Infrastructure\ServiceRegister;
use Patagona\Pricemonitor\Core\Interfaces\LoggerService;
use Patagona\Pricemonitor\Core\Interfaces\TransactionHistoryStorage;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionFailedDTO;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistory;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryDetail;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryMaster;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStatus;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStorageDTO;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStorageException;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryType;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\ValidationException;
use PHPUnit\Framework\TestCase;

class TransactionHistoryTest extends TestCase
{

    const CONTRACT_ID_1 = 1;
    const CONTRACT_ID_2 = 2;

    /** @var  TransactionHistory */
    protected $transactionHistory;

    /** @var  TestLoggerService */
    protected $loggerService;

    /** @var  TestTransactionHistoryStorage*/
    protected $transactionHistoryStorage;

    public function setUp()
    {
        parent::setUp();
        
        $this->loggerService = new TestLoggerService();

        new ServiceRegister([
            TransactionHistoryStorage::class => new TestTransactionHistoryStorage(),
        ],[
            LoggerService::class => $this->loggerService,
        ]);

        $this->transactionHistoryStorage = ServiceRegister::getTransactionHistoryStorage();
        $this->transactionHistory = new TransactionHistory();
    }
    
    public function testStartTransactionIsAddedAndHasProperFields()
    {
        $newId = $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES);
        $newId2 = $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, TransactionHistoryType::EXPORT_PRODUCTS);

        $this->assertEquals($newId, 0);
        $this->assertEquals($newId2, 1);
        $this->assertEquals(2, count($this->transactionHistoryStorage->transactionHistoryAddedMasterData));
        /** @var TransactionHistoryMaster $transactionHistoryMasterData1 */
        $transactionHistoryMasterData1 = $this->transactionHistoryStorage->transactionHistoryAddedMasterData[0];
        $this->assertEquals(0, $transactionHistoryMasterData1->getId());
        $this->assertEquals(0, $transactionHistoryMasterData1->getTotalCount());
        $this->assertEquals(0, $transactionHistoryMasterData1->getSuccessCount());
        $this->assertEquals(0, $transactionHistoryMasterData1->getFailedCount());
        $this->assertEquals(TransactionHistoryStatus::IN_PROGRESS, $transactionHistoryMasterData1->getStatus());
        $this->assertEquals(TransactionHistoryType::IMPORT_PRICES, $transactionHistoryMasterData1->getType());
        $this->assertEmpty($transactionHistoryMasterData1->getNote());
        $this->assertEquals(0, count($this->transactionHistoryStorage->transactionHistoryAddedDetails));

        /** @var TransactionHistoryMaster $transactionHistoryMasterData2 */
        $transactionHistoryMasterData2 = $this->transactionHistoryStorage->transactionHistoryAddedMasterData[1];
        $this->assertEquals(1, $transactionHistoryMasterData2->getId());
        $this->assertEquals(0, $transactionHistoryMasterData2->getTotalCount());
        $this->assertEquals(0, $transactionHistoryMasterData2->getSuccessCount());
        $this->assertEquals(0, $transactionHistoryMasterData2->getFailedCount());
        $this->assertEquals(TransactionHistoryStatus::IN_PROGRESS, $transactionHistoryMasterData2->getStatus());
        $this->assertEquals(TransactionHistoryType::EXPORT_PRODUCTS, $transactionHistoryMasterData2->getType());
        $this->assertEmpty($transactionHistoryMasterData2->getNote());
        $this->assertEquals(0, count($this->transactionHistoryStorage->transactionHistoryAddedDetails));
    }
    
    public function testStartTransactionInvokedWithInvalidType()
    {
        try {
            $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, 'Test');
        } catch (\Exception $e) {
            $this->assertStringStartsWith(
                'Type of transaction master must be value in this set of values:',
                $e->getMessage()
            );
        }

    }

    public function testCreationOfTransactionHistoryDetailWithInvalidStatus()
    {
        $newId = $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES);
        $exceptionMessage = '';

        try {
            new TransactionHistoryDetail('test',  new \DateTime(), null, $newId, null, 12345, 20, 10, 30);
        } catch (\Exception $e) {
            $exceptionMessage = $e->getMessage();
        }

        $this->assertStringStartsWith('Status of transaction must be value in this set of values:', $exceptionMessage);
    }


    public function testUpdateTransactionWhenTransactionNotStarted()
    {
        $exceptionMessage = '';

        try {
            $this->transactionHistory->updateTransaction(
                self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, 1, [], null
            );
        } catch (TransactionHistoryStorageException $e) {
            $exceptionMessage = $e->getMessage();
        }

        $this->assertNotEmpty($exceptionMessage);
    }

    public function testAddTransactionDetailsInvokedWithValidData()
    {
        $newId = $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES);
        
        $transactionDetails = [
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12345,
                20,
                10,
                30
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12343,
                23,
                13,
                33
            ),
        ];

        /** @var TransactionHistoryStorageDTO $updatedTransaction */
        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $transactionDetails, null
        );

        /** @var TransactionHistoryMaster $updateMasterTransaction */
        $updateMasterTransaction = $this->transactionHistoryStorage->transactionHistoryAddedMasterData[$newId];

        $this->assertNotEmpty($updatedTransactionDetails);
        $this->assertEquals($updateMasterTransaction->getId(), $newId);
        $this->assertEquals($updateMasterTransaction->getFailedCount(), 0);
        $this->assertEquals($updateMasterTransaction->getSuccessCount(), 0);
        $this->assertEquals($updateMasterTransaction->getTotalCount(), count($transactionDetails));
        $this->assertEquals($updateMasterTransaction->getStatus(), TransactionHistoryStatus::IN_PROGRESS);
        $this->assertEquals($updateMasterTransaction->getType(), TransactionHistoryType::IMPORT_PRICES);
        $this->assertEquals($updateMasterTransaction->getContractId(), self::CONTRACT_ID_1);
        $this->assertEmpty($updateMasterTransaction->getNote());
        $this->assertNull($updateMasterTransaction->getUniqueIdentifier());
        $this->assertEquals(count($updatedTransactionDetails), count($transactionDetails));
        $this->assertEquals($updatedTransactionDetails[0]->getId(), 0);
        $this->assertEquals($updatedTransactionDetails[0]->getMasterId(), $newId);
        $this->assertEquals($updatedTransactionDetails[0]->getStatus(), TransactionHistoryStatus::IN_PROGRESS);
        $this->assertEmpty($updatedTransactionDetails[0]->getNote());
        $this->assertEmpty($updatedTransactionDetails[0]->isUpdatedInShop());
        $this->assertEquals($updatedTransactionDetails[1]->getId(), 1);
        $this->assertEquals($updatedTransactionDetails[1]->getStatus(), TransactionHistoryStatus::IN_PROGRESS);
        $this->assertEmpty($updatedTransactionDetails[1]->getNote());
        $this->assertEmpty($updatedTransactionDetails[1]->isUpdatedInShop());
        $this->assertEquals($updatedTransactionDetails[1]->getMasterId(), $newId);
    }

    public function testAddTransactionDetailsInvokedWithInvalidData()
    {
        $newId = $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES);

        $transactionDetails = [
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12345,
                20,
                10,
                30
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12343,
                23,
                13,
                33
            ),
        ];

        $exceptionMessage = '';

        try {
            $this->transactionHistory->updateTransaction(
                self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, null, $transactionDetails, null
            );
        } catch (\Exception $e) {
           $exceptionMessage = $e->getMessage();
        }

        $this->assertStringStartsWith('Transaction id and uniqueIdentifier can not both be null', $exceptionMessage);
    }

    public function testUpdateTransactionDetailsInvokedWithTransactionIdAllDetailsSuccessful()
    {
        $newId = $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES);

        $transactionDetails = [
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12345,
                20,
                10,
                30
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12343,
                23,
                13,
                33
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                11,
                22,
                13,
                33
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12,
                22,
                13,
                33
            ),
        ];

        /** @var TransactionHistoryStorageDTO $updatedTransaction */
        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $transactionDetails, null
        );

        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $updatedTransactionDetails, null
        );

        /** @var TransactionHistoryMaster $updateMasterTransaction */
        $updateMasterTransaction = $this->transactionHistoryStorage->transactionHistoryAddedMasterData[$newId];

        $this->assertNotEmpty($updatedTransactionDetails);
        $this->assertEquals($updateMasterTransaction->getId(), $newId);
        $this->assertEquals($updateMasterTransaction->getFailedCount(), 0);
        $this->assertEquals($updateMasterTransaction->getSuccessCount(), count($transactionDetails));
        $this->assertEquals($updateMasterTransaction->getTotalCount(), count($transactionDetails));
        $this->assertEquals($updateMasterTransaction->getStatus(), TransactionHistoryStatus::IN_PROGRESS);
        $this->assertEquals($updateMasterTransaction->getType(), TransactionHistoryType::IMPORT_PRICES);
        $this->assertEquals($updateMasterTransaction->getContractId(), self::CONTRACT_ID_1);
        $this->assertEmpty($updateMasterTransaction->getNote());
        $this->assertNull($updateMasterTransaction->getUniqueIdentifier());

        $counter = 0;

        foreach ($updatedTransactionDetails as $updatedTransactionDetail) {
            $this->assertEquals($updatedTransactionDetail->getId(), $counter++);
            $this->assertEquals($updatedTransactionDetail->getMasterId(), $newId);
            $this->assertEquals($updatedTransactionDetail->getStatus(), TransactionHistoryStatus::FINISHED);
            $this->assertEmpty($updatedTransactionDetail->getNote());
            $this->assertTrue($updatedTransactionDetail->isUpdatedInShop());
            $this->assertNull($updatedTransactionDetail->getMasterUniqueIdentifier());
        }
    }

    public function testUpdateTransactionDetailsInvokedWithTransactionUniqueIdAllDetailsSuccessful()
    {
        $newId = $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES);
        $transactionUniqueIdentifier = 11;

        $transactionDetails = [
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12345,
                20,
                10,
                30
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12343,
                23,
                13,
                33
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                11,
                22,
                13,
                33
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12,
                22,
                13,
                33
            ),
        ];

        /** @var TransactionHistoryStorageDTO $updatedTransaction */
        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $transactionDetails, $transactionUniqueIdentifier
        );

        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, null, $updatedTransactionDetails, $transactionUniqueIdentifier
        );

        /** @var TransactionHistoryMaster $updateMasterTransaction */
        $updateMasterTransaction = $this->transactionHistoryStorage->transactionHistoryAddedMasterData[$newId];

        $this->assertNotEmpty($updatedTransactionDetails);
        $this->assertEquals($updateMasterTransaction->getId(), $newId);
        $this->assertEquals($updateMasterTransaction->getFailedCount(), 0);
        $this->assertEquals($updateMasterTransaction->getSuccessCount(), count($transactionDetails));
        $this->assertEquals($updateMasterTransaction->getTotalCount(), count($transactionDetails));
        $this->assertEquals($updateMasterTransaction->getStatus(), TransactionHistoryStatus::IN_PROGRESS);
        $this->assertEquals($updateMasterTransaction->getType(), TransactionHistoryType::IMPORT_PRICES);
        $this->assertEquals($updateMasterTransaction->getContractId(), self::CONTRACT_ID_1);
        $this->assertEmpty($updateMasterTransaction->getNote());
        $this->assertEquals($transactionUniqueIdentifier, $updateMasterTransaction->getUniqueIdentifier());

        $counter = 0;

        foreach ($updatedTransactionDetails as $updatedTransactionDetail) {
            $this->assertEquals($updatedTransactionDetail->getId(), $counter++);
            $this->assertEquals($updatedTransactionDetail->getMasterId(), $newId);
            $this->assertEquals($updatedTransactionDetail->getStatus(), TransactionHistoryStatus::FINISHED);
            $this->assertEmpty($updatedTransactionDetail->getNote());
            $this->assertTrue($updatedTransactionDetail->isUpdatedInShop());
            $this->assertEquals($transactionUniqueIdentifier, $updatedTransactionDetail->getMasterUniqueIdentifier());
        }
    }

    public function testUpdateTransactionDetailsInvokedWithTransactionUniqueIdOneDetailFailed()
    {
        $newId = $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES);
        $transactionUniqueIdentifier = 11;

        $transactionDetailsSuccessful = [
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12345,
                20,
                10,
                30
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12343,
                23,
                13,
                33
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                11,
                22,
                13,
                33
            ),
        ];

        $transactionDetailsFailed = [
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12,
                22,
                13,
                33
            ),
        ];

        $allTransactions = array_merge($transactionDetailsSuccessful, $transactionDetailsFailed);

        $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $allTransactions, $transactionUniqueIdentifier
        );

        $failedItems = [
            new TransactionFailedDTO(
                12, 'Failed to update item with product id 12.', TransactionHistoryStatus::FAILED, null, null, null, null
            )
        ];

        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, null, [], $transactionUniqueIdentifier, $failedItems
        );

        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $updatedTransactionDetails, null, $failedItems
        );

        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $updatedTransactionDetails, $transactionUniqueIdentifier, $failedItems
        );

        /** @var TransactionHistoryMaster $updateMasterTransaction */
        $updateMasterTransaction = $this->transactionHistoryStorage->transactionHistoryAddedMasterData[$newId];
        $failedItems = $this->findFailedItemsFromUpdatedDetails($updatedTransactionDetails);

        $this->assertNotEmpty($updatedTransactionDetails);
        $this->assertEquals($updateMasterTransaction->getId(), $newId);
        $this->assertEquals($updateMasterTransaction->getFailedCount(), count($transactionDetailsFailed));
        $this->assertEquals($updateMasterTransaction->getSuccessCount(), count($transactionDetailsSuccessful));
        $this->assertEquals($updateMasterTransaction->getTotalCount(), count($allTransactions));
        $this->assertEquals($updateMasterTransaction->getStatus(), TransactionHistoryStatus::IN_PROGRESS);
        $this->assertEquals($updateMasterTransaction->getType(), TransactionHistoryType::IMPORT_PRICES);
        $this->assertEquals($updateMasterTransaction->getContractId(), self::CONTRACT_ID_1);
        $this->assertNotEmpty($updateMasterTransaction->getNote());
        $this->assertEquals($transactionUniqueIdentifier, $updateMasterTransaction->getUniqueIdentifier());
        $this->assertEquals(1, count($failedItems));
        $this->assertFalse($failedItems[0]->isUpdatedInShop());
        $this->assertEquals('Failed to update item with product id 12.', $failedItems[0]->getNote());
        $this->assertEquals(TransactionHistoryStatus::FAILED, $failedItems[0]->getStatus());
    }

    public function testUpdateTransactionDetailsInvokedWithUniqueIdentifierOneDetailFailed()
    {
        $newId = $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES);

        $transactionDetailsSuccessful = [
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12345,
                20,
                10,
                30
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                12343,
                23,
                13,
                33
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS,
                new \DateTime(),
                null,
                $newId,
                null,
                11,
                22,
                13,
                33
            ),
        ];

        $transactionDetailsFailed = [
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS, new \DateTime(), null, $newId, null, 12, 22, 13, 33
            ),
        ];

        $allTransactions = array_merge($transactionDetailsSuccessful, $transactionDetailsFailed);

        /** @var TransactionHistoryStorageDTO $updatedTransaction */
        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $allTransactions
        );

        $failedItems = [
            new TransactionFailedDTO(
                12, 'Failed to update item with product id 12.', TransactionHistoryStatus::FAILED, null, null, null, null
            )
        ];

        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $updatedTransactionDetails, null, $failedItems
        );

        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $updatedTransactionDetails, null, $failedItems
        );

        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $updatedTransactionDetails, null, $failedItems
        );

        /** @var TransactionHistoryMaster $updateMasterTransaction */
        $updateMasterTransaction = $this->transactionHistoryStorage->transactionHistoryAddedMasterData[$newId];
        $failedItems = $this->findFailedItemsFromUpdatedDetails($updatedTransactionDetails);

        $this->assertNotEmpty($updatedTransactionDetails);
        $this->assertEquals($updateMasterTransaction->getId(), $newId);
        $this->assertEquals($updateMasterTransaction->getFailedCount(), count($transactionDetailsFailed));
        $this->assertEquals($updateMasterTransaction->getSuccessCount(), count($transactionDetailsSuccessful));
        $this->assertEquals($updateMasterTransaction->getTotalCount(), count($allTransactions));
        $this->assertEquals($updateMasterTransaction->getStatus(), TransactionHistoryStatus::IN_PROGRESS);
        $this->assertEquals($updateMasterTransaction->getType(), TransactionHistoryType::IMPORT_PRICES);
        $this->assertEquals($updateMasterTransaction->getContractId(), self::CONTRACT_ID_1);
        $this->assertNotEmpty($updateMasterTransaction->getNote());
        $this->assertNull($updateMasterTransaction->getUniqueIdentifier());
        $this->assertEquals(1, count($failedItems));
        $this->assertFalse($failedItems[0]->isUpdatedInShop());
        $this->assertEquals('Failed to update item with product id 12.', $failedItems[0]->getNote());
        $this->assertEquals(TransactionHistoryStatus::FAILED, $failedItems[0]->getStatus());
    }

    public function testFinishTransactionWhenTransactionFailed()
    {
        $newId = $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES);
        $transactionUniqueIdentifier = 11;

        $transactionDetails = [
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS, new \DateTime(), null, $newId, null, 12345, 20, 10, 30
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS, new \DateTime(), null, $newId, null, 12343, 23, 13, 33
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS, new \DateTime(), null, $newId, null, 11, 22, 13, 33
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS, new \DateTime(), null, $newId, null, 12, 22, 13, 33
            ),
        ];

        /** @var TransactionHistoryStorageDTO $updatedTransaction */
        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $transactionDetails, $transactionUniqueIdentifier
        );

       $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, null, $updatedTransactionDetails, $transactionUniqueIdentifier
        );

        $this->transactionHistory->finishTransaction(
            self::CONTRACT_ID_1,
            TransactionHistoryStatus::FAILED,
            TransactionHistoryType::IMPORT_PRICES,
            $newId,
            null,
            'failed'
        );

        /** @var TransactionHistoryMaster $updateMasterTransaction */
        $updateMasterTransaction = $this->transactionHistoryStorage->transactionHistoryAddedMasterData[$newId];

        $this->assertEquals(TransactionHistoryStatus::FAILED, $updateMasterTransaction->getStatus());
        $this->assertEquals('failed', trim($updateMasterTransaction->getNote()));
    }

    public function testFinishTransactionWhenTransactionFinished()
    {
        $newId = $this->transactionHistory->startTransaction(self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES);
        $transactionUniqueIdentifier = 11;

        $transactionDetails = [
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS, new \DateTime(), null, $newId, null, 12345, 20, 10, 30
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS, new \DateTime(), null, $newId, null, 12343, 23, 13, 33
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS, new \DateTime(), null, $newId, null, 11, 22, 13, 33
            ),
            new TransactionHistoryDetail(
                TransactionHistoryStatus::IN_PROGRESS, new \DateTime(), null, $newId, null, 12, 22, 13, 33
            ),
        ];

        /** @var TransactionHistoryStorageDTO $updatedTransaction */
        $updatedTransactionDetails = $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, $newId, $transactionDetails, $transactionUniqueIdentifier
        );

        $this->transactionHistory->updateTransaction(
            self::CONTRACT_ID_1, TransactionHistoryType::IMPORT_PRICES, null, $updatedTransactionDetails, $transactionUniqueIdentifier
        );

        $this->transactionHistory->finishTransaction(
            self::CONTRACT_ID_1,
            TransactionHistoryStatus::FINISHED,
            TransactionHistoryType::IMPORT_PRICES,
            $newId
        );

        /** @var TransactionHistoryMaster $updateMasterTransaction */
        $updateMasterTransaction = $this->transactionHistoryStorage->transactionHistoryAddedMasterData[$newId];

        $this->assertEquals(TransactionHistoryStatus::FINISHED, $updateMasterTransaction->getStatus());
        $this->assertEmpty($updateMasterTransaction->getNote());
    }

    public function testMasterCleanupNumberOfDaysInvalid()
    {
        $exceptionMessage = '';
        try {
            $this->transactionHistory->cleanupMaster('aaa');
        } catch (ValidationException $e) {
            $exceptionMessage = $e->getMessage();
        }

        $this->assertNotEmpty($exceptionMessage);
    }

    public function testDetailsCleanupNumberOfDaysInvalid()
    {
        $exceptionMessage = '';
        try {
            $this->transactionHistory->cleanupDetails('aaa');
        } catch (ValidationException $e) {
            $exceptionMessage = $e->getMessage();
        }

        $this->assertNotEmpty($exceptionMessage);
    }

    public function testMasterCleanupSuccess()
    {
        $numberOfDays = 3;

        $this->transactionHistory->cleanupMaster($numberOfDays);

        $this->assertNotEmpty($this->transactionHistoryStorage->cleanupCallStack['cleanupMaster']);
        $this->assertEquals($numberOfDays, $this->transactionHistoryStorage->cleanupCallStack['cleanupMaster']);
    }

    public function testDetailsCleanupSuccess()
    {
        $numberOfDays = 3;

        $this->transactionHistory->cleanupDetails($numberOfDays);

        $this->assertNotEmpty($this->transactionHistoryStorage->cleanupCallStack['cleanupDetails']);
        $this->assertEquals($numberOfDays, $this->transactionHistoryStorage->cleanupCallStack['cleanupDetails']);
    }

    private function findFailedItemsFromUpdatedDetails($updatedTransactionDetails)
    {
        $failedItems = [];
        
        /** @var TransactionHistoryDetail $transactionDetail */
        foreach ($updatedTransactionDetails as $transactionDetail) {
            if ($transactionDetail->getStatus() === TransactionHistoryStatus::FAILED) {
                $failedItems[] = $transactionDetail;
            }
        }
        
        return $failedItems;
    }
}