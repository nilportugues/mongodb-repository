<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 20/02/16
 * Time: 15:51.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NilPortugues\Tests\Foundation\Infrastructure\Model\Repository\MongoDB;

use NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB\EntityId;

class EntityIdTest extends \PHPUnit_Framework_TestCase
{
    const ID_VALUE = 1;

    public function testItCanConstruct()
    {
        $entityId = new EntityId(self::ID_VALUE);

        $this->assertEquals(self::ID_VALUE, $entityId->id());
    }

    public function testItCanCastToString()
    {
        $entityId = new EntityId(self::ID_VALUE);

        $this->assertEquals('1', (string) $entityId);
    }
}
