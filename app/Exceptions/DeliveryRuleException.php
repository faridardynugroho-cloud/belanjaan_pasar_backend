<?php

namespace App\Exceptions;

use DomainException;

class DeliveryRuleException extends DomainException
{
    public const CODE_CAPACITY_EXCEEDED = 'CAPACITY_EXCEEDED';

    public const CODE_WEIGHT_EXCEEDED = 'WEIGHT_EXCEEDED';

    public const CODE_DELIVERY_ALREADY_STARTED = 'DELIVERY_ALREADY_STARTED';

    public const CODE_INVALID_STATUS = 'INVALID_STATUS';

    public const CODE_NOT_OWNER = 'NOT_OWNER';

    public const CODE_PENDING_ORDERS_EXIST = 'PENDING_ORDERS_EXIST';

    public const CODE_NO_ACCEPTED_ORDERS = 'NO_ACCEPTED_ORDERS';

    public const CODE_DISTANCE_EXCEEDED = 'DISTANCE_EXCEEDED';

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
