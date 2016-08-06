<?php

namespace NilPortugues\Tests\Foundation;

use DateTime;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Mapping;
use NilPortugues\Tests\Foundation\Helpers\Clients;

class MongoDBCustomerMappingWithCustomId implements Mapping
{
    /**
     * Returns the name of the collection or table.
     *
     * @return string
     */
    public function name(): string
    {
        return 'customers';
    }

    /**
     * Keys are object properties without property defined in identity().
     * Values its equivalents in the data store.
     *
     * @return array
     */
    public function map(): array
    {
        return [
            'id' => 'customer_id',
            'name' => 'customer_name',
            'totalOrders' => 'total_orders',
            'totalEarnings' => 'total_earnings',
            'date.date' => 'created_at',
        ];
    }

    /**
     * Name of the identity field in storage.
     *
     * @return string
     */
    public function identity(): string
    {
        return 'customer_id';
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function fromArray(array $data)
    {
        return new Clients(
            !empty($data['customer_id']) ? $data['customer_id'] : '',
            !empty($data['customer_name']) ? $data['customer_name'] : '',
            !empty($data['created_at']) ? (new DateTime())->setTimestamp(strtotime($data['created_at'])) : new DateTime(),
            !empty($data['total_orders']) ? $data['total_orders'] : '',
            !empty($data['total_earnings']) ? $data['total_earnings'] : ''
        );
    }

    /**
     * The automatic generated strategy used will be the data-store's if set to true.
     *
     * @return bool
     */
    public function autoGenerateId(): bool
    {
        return false;
    }
}
