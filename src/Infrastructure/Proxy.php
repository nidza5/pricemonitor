<?php

namespace Patagona\Pricemonitor\Core\Infrastructure;

use Patagona\Pricemonitor\Core\Sync\Callbacks\CallbackDTO;

class Proxy extends BaseProxy
{

    const BASE_API_URL = 'https://app.patagona.de/api';

    /**
     * Creates Pricemonitor API proxy
     *
     * @param string $email Pricemonitor account email
     * @param string $password Pricemonitor account password
     *
     * @return \Patagona\Pricemonitor\Core\Infrastructure\Proxy for Pricemonitor API
     */
    public static function createFor($email, $password)
    {
        return new self($email, $password, ServiceRegister::getHttpClient());
    }

    /**
     * Gets contract list ([contract_id => contract_name]) from Pricemonitor
     *
     * @return array List of contracts for an account. Key is contract id and value is contract name
     * @throws \Exception
     */
    public function getContracts()
    {
        $content = $this->request('GET','/account');
        if (empty($content['companies'])) {
            return [];
        }

        $contracts = [];
        foreach ($content['companies'] as $company) {
            if (empty($company['contracts'])) {
                continue;
            }

            foreach ($company['contracts'] as $contract) {
                if (!$contract['active']) {
                    continue;
                }

                $contracts[$contract['sid']] = $contract['name'];
            }
        }

        return $contracts;
    }

    /**
     * Gets export task status from PM API
     *
     * @param string $contractId Pricemonitor contract id
     * @param string $taskId Pricemonitor export task id for which to get status
     *
     * @return array Task status summary with data from API (state, failures...)
     * @throws \Exception
     */
    public function getExportStatus($contractId, $taskId)
    {
        $content = $this->request('GET',"/2/v/contracts/{$contractId}/tasks/{$taskId}");

        if (!isset($content['state'])) {
            throw new \Exception('State for export status in response from API not valid. Response: ' . json_encode($content));
        }

        if (!empty($content['creationDate'])) {
            $content['creationDate'] = \DateTime::createFromFormat("Y-m-d\TH:i:s.u\Z", $content['creationDate']);
        }

        if (!empty($content['startDate'])) {
            $content['startDate'] = \DateTime::createFromFormat("Y-m-d\TH:i:s.u\Z", $content['startDate']);
        }

        if (!empty($content['finishDate'])) {
            $content['finishDate'] = \DateTime::createFromFormat("Y-m-d\TH:i:s.u\Z", $content['finishDate']);
        }

        return $content;
    }

    /**
     * Gets the list of recommended prices from PM API
     *
     * @param string $contractId Pricemonitor contract id
     * @param int $start Pagination parameter, if set prices returned will start from this index. Default value is 0
     * @param int $limit Pagination parameter, number of prices to get per request. Default value is 1000
     * @param \DateTime|null $since Time limitation. If set oldest price result will be greater or equal to this value
     *
     * @return array Price recommendation list from PM API
     * @throws \Exception
     */
    public function importPrices($contractId, $start = 0, $limit = 1000, \DateTime $since = null)
    {
        $queryParams = [
            'start' => $start,
            'limit' => $limit,
        ];

        if (!empty($since) && $this->isDateWithin48Hours($since)) {
            $since->setTimezone(new \DateTimeZone('UTC'));
            $queryParams['since'] = $since->format("Y-m-d\TH:i:s.u\Z");
        }

        $content = $this->request('GET',"/1/{$contractId}/products/analysis/pricerecommendations", $queryParams);

        if (!isset($content['priceRecommendations'])) {
            throw new \Exception('Api response not in valid format: ' . json_encode($content));
        }
        
        if (empty($content['priceRecommendations'])) {
            return [];
        }

        return array_map(function ($priceRecommendation) {
            $priceRecommendation['currency'] = 'EUR';
            return $priceRecommendation;
        }, $content['priceRecommendations']);
    }

    /**
     * Uploads the list of products to PM. This method completely replaces existing PM product list with new products
     *
     * @param string $contractId Pricemonitor contract id
     * @param array $products List of new products to upload to PM. Each product should have mandatory attributes (gtin, name,
     * productId, referencePrice, minPriceBoundary and maxPriceBoundary) and arbitrary number of tags as a key value pairs
     *
     * @return string Id of a PM task that can be used for fetching export product status
     * @throws \Exception
     */
    public function exportProducts($contractId, $products)
    {
        $content = $this->request(
            'PUT',
            "/2/v/contracts/{$contractId}/products",
            [],
            $this->castMandatoryFieldsToProperTypesForProducts($products)
        );

        if (empty($content['id'])) {
            throw new \Exception('Response for product export is not in valid format. Request: ' . json_encode($content));
        }

        return $content['id'];
    }

    public function getExpirationDateForContract($contractId)
    {
        $contractDetails = $this->request('GET', "/1/{$contractId}/settings");

        return isset($contractDetails['expirationDate']) ?
            date_create_from_format("Y-m-d\TH:i:s.u\Z", $contractDetails['expirationDate']) : null;
    }

    /**
     * @param $contractId
     *
     * @return CallbackDTO[]
     */
    public function getCallbacks($contractId)
    {
        $callbacks = $this->request(
            'GET',
            "/2/m/contracts/{$contractId}/settings/callbacks"
        );

        return $this->createCallbackDTOs($callbacks);
    }

    /**
     * @param array $callbackDTOs
     * @param CallbackDTO[] $contractId
     *
     * @return CallbackDTO[]
     */
    public function registerCallbacks(array $callbackDTOs, $contractId)
    {
        $callbacks = $this->request(
            'PUT',
            "/2/m/contracts/{$contractId}/settings/callbacks",
            [],
            $this->createCallbacksForRequest($callbackDTOs)
        );

        return $this->createCallbackDTOs($callbacks);
    }

    private function castMandatoryFieldsToProperTypesForProducts($products)
    {
        foreach ($products as &$product) {
            if (isset($product['gtin'])) {
                $product['gtin'] = intval($product['gtin']);
            }

            if (isset($product['referencePrice'])) {
                $product['referencePrice'] = floatval($product['referencePrice']);
            }

            if (isset($product['minPriceBoundary'])) {
                $product['minPriceBoundary'] = floatval($product['minPriceBoundary']);
            }

            if (isset($product['maxPriceBoundary'])) {
                $product['maxPriceBoundary'] = floatval($product['maxPriceBoundary']);
            }
        }

        return $products;
    }

    /**
     * Helper method that checks if given time is within last 48 hours. Buffer of 5 minutes is added in 48h boundary calculation
     * to make sure that given datetime will stay within boundary for a request.
     *
     * @param \DateTime $dateTime Datetime to check
     *
     * @return bool True if given date is within 48 hours; otherwise false
     */
    private function isDateWithin48Hours(\DateTime $dateTime)
    {
        // Add 5 minutes to 48h to make sure request is valid
        $utc48hBoundary = new \DateTime('48 hours ago + 5 minutes', new \DateTimeZone('UTC'));

        return $utc48hBoundary->getTimestamp() < $dateTime->getTimestamp();
    }

    /**
     * @param CallbackDTO[] $callbackDTOs
     *
     * @return array
     */
    private function createCallbacksForRequest(array $callbackDTOs)
    {
        $callbacksForRequest['pricemonitorCompleted'] = [];

        /** @var CallbackDTO $callbackDTO */
        foreach ($callbackDTOs as $callbackDTO) {
            $bodyTemplate = '';
            if (!empty($callbackDTO->getBodyTemplate())) {
                $bodyTemplate = json_encode($callbackDTO->getBodyTemplate());
            }

            $headers = $callbackDTO->getHeaders();

            $callbacksForRequest['pricemonitorCompleted'][] = [
                'method' => $callbackDTO->getMethod(),
                'name' => $callbackDTO->getName(),
                'bodyTemplate' => $bodyTemplate,
                'headers' => !empty($headers) ? $headers : ['Cache-Control' => 'no-cache'],
                'url' => $callbackDTO->getUrl(),
            ];
        }

        return $callbacksForRequest;
    }

    /**
     * @param $callbacks
     * @return array
     */
    private function createCallbackDTOs($callbacks)
    {
        $callbacksDTOs = [];

        foreach ($callbacks['pricemonitorCompleted'] as $callback) {
            $callbackName = isset($callback['name']) ? $callback['name'] : '';
            $callbacksDTOs[] = new CallbackDTO(
                $callback['method'],
                $callbackName,
                json_decode($callback['bodyTemplate'], true),
                $callback['url'],
                $callback['headers']
            );
        }

        return $callbacksDTOs;
    }

}