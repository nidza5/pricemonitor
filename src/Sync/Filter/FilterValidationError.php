<?php

namespace Patagona\Pricemonitor\Core\Sync\Filter;


class FilterValidationError
{
    private $ownErrors = [];
    
    private $childrenErrors = [];

    /**
     * @return array
     */
    public function getOwnErrors()
    {
        return $this->ownErrors;
    }

    /**
     * @return array
     */
    public function getChildrenErrors()
    {
        return $this->childrenErrors;
    }

    /**
     * @param string[] $errors
     */
    public function addOwnErrors(array $errors)
    {
        $this->ownErrors = array_merge($this->ownErrors, $errors);
    }

    /**
     * @param string[] $errors
     */
    public function addChildrenErrors(array $errors)
    {
        $this->childrenErrors = $this->childrenErrors + $errors;
    }
}