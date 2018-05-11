<?php

namespace Patagona\Pricemonitor\Core\Sync\Queue;

use DateTime;

class StorageModel
{

    /**
     * @var int
     */
    private $id = null;

    /**
     * @var DateTime
     */
    private $reservationTime = null;

    /**
     * @var int
     */
    private $attempts = 0;

    /**
     * @var string
     */
    private $payload = '';

    public function __construct($data = [])
    {
        if (!empty($data['id'])) {
            $this->id = $data['id'];
        }

        if (!empty($data['reservationTime'])) {
            $this->reservationTime = $data['reservationTime'];
        }

        if (!empty($data['attempts'])) {
            $this->attempts = $data['attempts'];
        }

        if (!empty($data['payload'])) {
            $this->payload = $data['payload'];
        }
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return DateTime
     */
    public function getReservationTime()
    {
        return $this->reservationTime;
    }

    /**
     * @param DateTime $reservationTime
     */
    public function setReservationTime($reservationTime)
    {
        $this->reservationTime = $reservationTime;
    }

    /**
     * @return int
     */
    public function getAttempts()
    {
        return $this->attempts;
    }

    /**
     * @param int $attempts
     */
    public function setAttempts($attempts)
    {
        $this->attempts = $attempts;
    }

    /**
     * @return string
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * @param string $payload
     */
    public function setPayload($payload)
    {
        $this->payload = $payload;
    }

}