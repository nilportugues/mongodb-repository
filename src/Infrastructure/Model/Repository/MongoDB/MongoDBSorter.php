<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 15/02/16
 * Time: 21:00.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB;

use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Mapping;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Order;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Sort as SortInterface;

/**
 * Class MongoDBSorter.
 */
class MongoDBSorter
{
    /**
     * @param array              $options
     * @param SortInterface|null $sort
     * @param Mapping            $mapping
     */
    public static function sort(array &$options, SortInterface $sort = null, Mapping $mapping)
    {
        $columns = $mapping->map();

        if (null !== $sort) {
            /** @var Order[] $orders */
            $orders = (array) $sort->orders();
            foreach ($orders as $propertyName => $order) {
                $key = $propertyName;
                if ($propertyName !== BaseMongoDBRepository::MONGODB_OBJECT_ID) {
                    $columns[$propertyName];
                }

                self::guardColumnExists($columns, $propertyName);
                $options['sort'][$key] = $order->isAscending() ? 1 : -1;
            }
        }
    }

    /**
     * @param $columns
     * @param $propertyName
     *
     * @return mixed
     * @codeCoverageIgnore
     */
    protected static function guardColumnExists($columns, $propertyName)
    {
        if (false !== array_search($propertyName, $columns, true)
            && $propertyName !== BaseMongoDBRepository::MONGODB_OBJECT_ID
        ) {
            throw new \RuntimeException(sprintf('Property %s has no associated column.', $propertyName));
        }
    }
}
