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

use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Order;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Sort as SortInterface;

/**
 * Class MongoDBSorter.
 */
class MongoDBSorter
{
    /**
     * @param array         $options
     * @param SortInterface $sort
     */
    public static function sort(array &$options, SortInterface $sort)
    {
        /** @var Order $order */
        foreach ($sort->orders() as $propertyName => $order) {
            $options['sort'][$propertyName] = $order->isAscending() ? 1 : 0;
        }
    }
}
