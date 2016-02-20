<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 16/02/16
 * Time: 0:07.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB;

use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity;

/**
 * Class EntityId
 * @package NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB
 */
class EntityId implements Identity
{
    private $id;

    /**
     * ObjectId constructor.
     *
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return (string) $this->id;
    }
}
