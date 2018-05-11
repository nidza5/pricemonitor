<?php

namespace Patagona\Pricemonitor\Core\Tests\TestComponents;


use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistory;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryDetail;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryMaster;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStatus;
use Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryStorageDTO;

class TestTransactionHistory extends TransactionHistory
{
    const TRANSACTION_ID = 1;
    
    private static $instance = null;
    
    public $addedTransactionMasterData = [];
    public $addedTransactionDetails = [];
    public $transactionLastCallParams = [];

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new TestTransactionHistory();
        }
        return self::$instance; 
    }

    public function getTransactionHistoryMaster($contractId, $type, $limit = null, $offset = null)
    {
        $transactionData = [];

        /** @var TransactionHistoryMaster $singleMasterDataObject */
        $singleMasterDataObject = new TransactionHistoryMaster(
            $contractId,
            (new \DateTime()),
            $type,
            TransactionHistoryStatus::FINISHED,
            -1,
            -1,
            -1
        );

        $transactionData[] = $singleMasterDataObject;

        for ($i = $offset; $i < $offset + $limit; $i++) {
            /** @var TransactionHistoryMaster $singleMasterDataObject */
            $singleMasterDataObject = new TransactionHistoryMaster(
                $contractId,
                (new \DateTime()),
                $type,
                TransactionHistoryStatus::FINISHED,
                $i,
                $i,
                $i
            );

            $singleMasterDataObject->setNote('Note ' . $i);
            $transactionData[] = $singleMasterDataObject;
        }

        return $transactionData;
    }

    public function getTransactionHistoryMasterCount($contractId, $type)
    {
        return 100;
    }

    /**
     * @param string $contractId
     * @param int $masterId
     * @param string $type
     * @param int $limit
     * @param int $offset
     * @return TransactionHistoryDetail[]
     * @internal param $transactionId
     */
    public function getTransactionHistoryDetails($contractId, $masterId, $type, $limit = null, $offset = null)
    {
        $transactionDataDetails = [];

        $singleDetailDataObject = new TransactionHistoryDetail(
            TransactionHistoryStatus::FAILED,
            new \DateTime(),
            -1,
            $masterId,
            111,
            123
        );
        $transactionDataDetails[] = $singleDetailDataObject;

        for ($i = $offset; $i < $offset + $limit; $i++) {
            /** @var TransactionHistoryDetail $singleDetailDataObject */
            $singleDetailDataObject = new TransactionHistoryDetail(
                TransactionHistoryStatus::FINISHED,
                new \DateTime(),
                $i,
                $masterId,
                $i,
                $i
            );
            $singleDetailDataObject->setUpdatedInShop(true);
            $singleDetailDataObject->setNote('Note ' . $i);
            $transactionDataDetails[] = $singleDetailDataObject;
        }
        
        return $transactionDataDetails;
    }

    public function getTransactionHistoryDetailsCount($contractId, $masterId)
    {
        return 100;
    }

    /**
     * @param string $contractId
     * @param string $transactionType
     *
     * @return int
     */
    public function startTransaction($contractId, $transactionType)
    {
        $this->transactionLastCallParams['contractId'] = $contractId;
        $this->transactionLastCallParams['transactionType'] = $transactionType;
        $this->transactionLastCallParams['status'] = TransactionHistoryStatus::IN_PROGRESS;
        
        $transactionHistoryMasterData = new TransactionHistoryMaster(
            $contractId,
            (new \DateTime()),
            $transactionType,
            TransactionHistoryStatus::IN_PROGRESS
        );

        $transactionHistoryStorage = new TestTransactionHistoryStorage();

        /** @var TransactionHistoryStorageDTO $transaction */
        $transaction = $transactionHistoryStorage->saveTransactionHistory(
            $transactionHistoryMasterData,
            []
        );

        $this->addedTransactionMasterData[self::TRANSACTION_ID] = $transaction->getTransactionMaster();
        
        return self::TRANSACTION_ID;
    }

    /**
     * @param string $contractId
     * @param string $type
     * @param null $transactionId
     * @param array $transactionHistoryDetails
     * @param null $transactionUniqueIdentifier
     * @param array $failedItems
     * @return array|\Patagona\Pricemonitor\Core\Sync\TransactionHistory\TransactionHistoryDetail[]
     */
    public function updateTransaction(
        $contractId, $type, $transactionId = null, array $transactionHistoryDetails = [], $transactionUniqueIdentifier = null, array $failedItems = []
    ) {
        $transactionHistoryStorage = new TestTransactionHistoryStorage();

        /** @var TransactionHistoryStorageDTO $transaction */
        $transaction = $transactionHistoryStorage->saveTransactionHistory(
            $this->addedTransactionMasterData[self::TRANSACTION_ID],
            $transactionHistoryDetails
        );

        $this->addedTransactionDetails[self::TRANSACTION_ID] = $transaction->getTransactionDetails();

        return $this->addedTransactionDetails[self::TRANSACTION_ID];
    }

    /**
     * @param string $contractId
     * @param string $transactionStatus
     * @param string $type
     * @param null $transactionId
     * @param null $uniqueIdentifier
     * @param string $note
     */
    public function finishTransaction(
        $contractId,
        $transactionStatus,
        $type,
        $transactionId = null,
        $uniqueIdentifier = null,
        $note = ''
    ) {
        $this->transactionLastCallParams['contractId'] = $contractId;
        $this->transactionLastCallParams['status'] = $transactionStatus;
        $this->transactionLastCallParams['type'] = $type;
        $this->transactionLastCallParams['transactionId'] = $transactionId;
        $this->transactionLastCallParams['uniqueIdentifier'] = $uniqueIdentifier;
        $this->transactionLastCallParams['note'] = $note;

        $this->addedTransactionMasterData[self::TRANSACTION_ID]->setStatus($transactionStatus);
        
        if (!empty($note)) {
            $this->addedTransactionMasterData[self::TRANSACTION_ID]->setNote($note);
        }

        $transactionHistoryStorage = new TestTransactionHistoryStorage();

       $transactionHistoryStorage->saveTransactionHistory($this->addedTransactionMasterData[self::TRANSACTION_ID], []);
    }
}