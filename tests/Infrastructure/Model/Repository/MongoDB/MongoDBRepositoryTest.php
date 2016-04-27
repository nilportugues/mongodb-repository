<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 19/02/16
 * Time: 20:52.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NilPortugues\Tests\Foundation\Infrastructure\Model\Repository\MongoDB;

use DateTime;
use Exception;
use MongoDB\Client;
use NilPortugues\Foundation\Domain\Model\Repository\Fields;
use NilPortugues\Foundation\Domain\Model\Repository\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Order;
use NilPortugues\Foundation\Domain\Model\Repository\Page;
use NilPortugues\Foundation\Domain\Model\Repository\Pageable;
use NilPortugues\Foundation\Domain\Model\Repository\Sort;
use NilPortugues\Tests\Foundation\Helpers\ClientId;
use NilPortugues\Tests\Foundation\Helpers\Clients;
use NilPortugues\Tests\Foundation\Helpers\ClientsRepository;

class MongoDBRepositoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ClientsRepository
     */
    protected $repository;

    /**
     *
     */
    public function setUp()
    {
        $client = new Client();
        $this->repository = new ClientsRepository($client, 'example', 'users');

        $client1 = new Clients(1, 'John Doe', (new DateTime('2014-12-11')), 3, 25.125);
        $client2 = new Clients(2, 'Junichi Masuda', (new DateTime('2013-02-22')), 3, 50978.125);
        $client3 = new Clients(3, 'Shigeru Miyamoto', (new DateTime('2010-12-01')), 5, 47889850.125);
        $client4 = new Clients(4, 'Ken Sugimori', (new DateTime('2010-12-10')), 4, 69158.687);

        $this->repository->addAll([$client1, $client2, $client3, $client4]);
    }

    /**
     *
     */
    public function tearDown()
    {
        $this->repository->removeAll();
    }

    public function testFindByDistinct()
    {
        $distinctFields = new Fields(['name']);
        $clients = new Clients(
            5,
            'John Doe',
            new DateTime('2014-12-11'),
            3,
            [
                new DateTime('2014-12-16'),
                new DateTime('2014-12-31'),
                new DateTime('2015-03-11'),
            ],
            25.125
        );

        $this->repository->add($clients);
        $results = $this->repository->findByDistinct($distinctFields);

        $this->assertEquals(4, count($results));
    }

    public function testFindAllWithDistinct()
    {
        $clients = new Clients(
            5,
            'John Doe',
            new DateTime('2014-12-11'),
            3,
            [
                new DateTime('2014-12-16'),
                new DateTime('2014-12-31'),
                new DateTime('2015-03-11'),
            ],
            25.125
        );

        $this->repository->add($clients);

        $pageable = new Pageable(
            1,
            10,
            null,
            null,
            null,
            new Fields(['name'])
        );

        $result = $this->repository->findAll($pageable);
        $this->assertEquals(4, count($result->content()));
    }

    public function testTransactional()
    {
        $clients = new Clients(
            5,
            'John Doe',
            new DateTime('2014-12-11'),
            3,
            [
                new DateTime('2014-12-16'),
                new DateTime('2014-12-31'),
                new DateTime('2015-03-11'),
            ],
            25.125
        );

        $transaction = function () use ($clients) {
            $this->repository->add($clients);
        };

        $this->repository->transactional($transaction);

        $this->assertEquals(5, $this->repository->count());
    }

    public function testTransactionalFailsAndDoesNotGetAdded()
    {
        $clients = new Clients(
            5,
            'John Doe',
            new DateTime('2014-12-11'),
            3,
            [
                new DateTime('2014-12-16'),
                new DateTime('2014-12-31'),
                new DateTime('2015-03-11'),
            ],
            25.125
        );

        $transaction = function () use ($clients) {
            throw new \Exception('Just making it fail');
        };

        $this->setExpectedException(Exception::class);
        $this->repository->transactional($transaction);
    }

    public function testItCanUpdateAnExistingClient()
    {
        $client1 = new Clients(1, 'Homer Simpson', (new DateTime('2014-12-11')), 3, 25.125);
        $client1 = $this->repository->add($client1);
        $this->assertEquals('Homer Simpson', $client1['name']);

        $client1 = $this->repository->find(new ClientId(1));
        $this->assertEquals('Homer Simpson', $client1['name']);
    }

    public function testItCanUpdateAllClientsName()
    {
        $client1 = new Clients(1, 'Homer Simpson', (new DateTime('2014-12-11')), 3, 25.125);
        $client2 = new Clients(2, 'Homer Simpson', (new DateTime('2013-02-22')), 3, 50978.125);
        $client3 = new Clients(3, 'Homer Simpson', (new DateTime('2010-12-01')), 5, 47889850.125);
        $client4 = new Clients(4, 'Homer Simpson', (new DateTime('2010-12-10')), 4, 69158.687);

        $this->repository->addAll([$client1, $client2, $client3, $client4]);

        for ($i = 1; $i <= 4; ++$i) {
            $client = $this->repository->find(new ClientId($i));
            $this->assertEquals('Homer Simpson', $client['name']);
        }
    }

    /**
     *
     */
    public function testItCanFind()
    {
        /* @var Clients $client */
        $id = new ClientId(1);
        $client = $this->repository->find($id);

        $this->assertEquals(1, $client['id']);
    }

    /**
     *
     */
    public function testFindAll()
    {
        $result = $this->repository->findAll();

        $this->assertInstanceOf(Page::class, $result);
        $this->assertEquals(4, count($result->content()));
    }

    /**
     *
     */
    public function testFindAllWithPageable()
    {
        $filter = new Filter();
        $filter->must()->beGreaterThanOrEqual('id', 1);

        $pageable = new Pageable(2, 2, new Sort(['name'], new Order('DESC')), $filter);
        $result = $this->repository->findAll($pageable);

        $this->assertInstanceOf(Page::class, $result);
        $this->assertEquals(2, count($result->content()));
    }

    /**
     *
     */
    public function testCount()
    {
        $this->assertEquals(4, $this->repository->count());
    }

    /**
     *
     */
    public function testCountWithFilter()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Ken');

        $this->assertEquals(1, $this->repository->count($filter));
    }

    /**
     *
     */
    public function testExists()
    {
        $this->assertTrue($this->repository->exists(new ClientId(1)));
    }

    /**
     *
     */
    public function testRemove()
    {
        $id = new ClientId(1);
        $this->repository->remove($id);

        $this->assertFalse($this->repository->exists($id));
    }

    /**
     *
     */
    public function testRemoveAll()
    {
        $this->repository->removeAll();

        $this->assertFalse($this->repository->exists(new ClientId(1)));
    }

    /**
     *
     */
    public function testRemoveAllWithFilter()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Doe');

        $this->repository->removeAll($filter);

        $this->assertFalse($this->repository->exists(new ClientId(1)));
    }

    /**
     *
     */
    public function testFindByWithEmptyRepository()
    {
        $this->repository->removeAll();

        $sort = new Sort(['name'], new Order('ASC'));
        $filter = new Filter();

        $filter->must()->contain('name', 'Ken');
        $this->assertEquals([], $this->repository->findBy($filter, $sort));
    }

    /**
     *
     */
    public function testAdd()
    {
        $client = new Clients(5, 'Ken Sugimori', (new DateTime('2010-12-10')), 4, 69158.687);

        $this->repository->add($client);

        $this->assertNotNull($this->repository->find(new ClientId(5)));
    }

    /**
     *
     */
    public function testFindReturnsEmptyArrayIfNotFound()
    {
        $this->assertEquals([], $this->repository->find(new ClientId(99999)));
    }

    /**
     *
     */
    public function testAddAll()
    {
        $client5 = new Clients(5, 'New Client 1', (new DateTime('2010-12-10')), 4, 69158.687);
        $client6 = new Clients(6, 'New Client 2', (new DateTime('2010-12-10')), 4, 69158.687);
        $clients = [$client5, $client6];

        $this->repository->addAll($clients);

        $this->assertNotNull($this->repository->find(new ClientId(5)));
        $this->assertNotNull($this->repository->find(new ClientId(6)));
    }

    /**
     *
     */
    public function testAddAllRollbacks()
    {
        $this->setExpectedException(Exception::class);
        $clients = ['a', 'b'];
        $this->repository->addAll($clients);
    }

    /**
     *
     */
    public function testFind()
    {
        $expected = new Clients(4, 'Ken Sugimori', (new DateTime('2010-12-10')), 4, 69158.687);

        $this->assertEquals($expected->id(), $this->repository->find(new ClientId(4))['id']);
    }

    /**
     *
     */
    public function testFindBy()
    {
        $sort = new Sort(['name'], new Order('ASC'));
        $filter = new Filter();
        $filter->must()->contain('name', 'Ken');

        $result = $this->repository->findBy($filter, $sort);

        $this->assertNotEmpty($result);
        $this->assertEquals(1, count($result));
    }
    //--------------------------------------------------------------------------------
    // MUST FILTER TESTS
    //--------------------------------------------------------------------------------
    /**
     *
     */
    public function testFindByWithMustEqual()
    {
        $filter = new Filter();
        $filter->must()->equal('name', 'Ken Sugimori');

        $fields = new Fields(['name']);
        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(1, count($results));

        foreach ($results as $result) {
            $this->assertTrue(false !== strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustNotEqualTest()
    {
        $filter = new Filter();
        $filter->must()->notEqual('name', 'Ken Sugimori');

        $fields = new Fields(['name']);
        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(3, count($results));
        foreach ($results as $result) {
            $this->assertFalse(strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustContain()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Ken');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(1, count($results));
        foreach ($results as $result) {
            $this->assertTrue(false !== strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustNotContainTest()
    {
        $filter = new Filter();
        $filter->must()->notContain('name', 'Ken');

        $fields = new Fields(['name']);
        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(3, count($results));
        foreach ($results as $result) {
            $this->assertFalse(strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustEndsWith()
    {
        $filter = new Filter();
        $filter->must()->endsWith('name', 'mori');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(1, count($results));
        foreach ($results as $result) {
            $this->assertTrue(false !== strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustStartsWith()
    {
        $filter = new Filter();
        $filter->must()->startsWith('name', 'Ke');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(1, count($results));
        foreach ($results as $result) {
            $this->assertTrue(false !== strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustBeLessThan()
    {
        $filter = new Filter();
        $filter->must()->beLessThan('totalOrders', 6);

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(4, count($results));
    }

    /**
     *
     */
    public function testFindByWithMustBeLessThanOrEqual()
    {
        $filter = new Filter();
        $filter->must()->beLessThanOrEqual('totalOrders', 4);
        $fields = new Fields(['name']);
        $results = $this->repository->findBy($filter, null, $fields);
        $this->assertEquals(3, count($results));
    }

    /**
     *
     */
    public function testFindByWithMustBeGreaterThan()
    {
        $filter = new Filter();
        $filter->must()->beGreaterThan('totalOrders', 2);

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(4, count($results));
    }

    /**
     *
     */
    public function testFindByWithMustBeGreaterThanOrEqual()
    {
        $filter = new Filter();
        $filter->must()->beGreaterThanOrEqual('totalOrders', 2);

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(4, count($results));
    }

    /**
     *
     */
    public function testFindByMustIncludeGroup()
    {
        $filter = new Filter();
        $filter->must()->includeGroup('date', [new DateTime('2010-12-01'), new DateTime('2010-12-10'), new DateTime('2013-02-22')]);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(3, count($results));
    }

    /**
     *
     */
    public function testFindByMustNotIncludeGroupTest()
    {
        $filter = new Filter();
        $filter->must()->notIncludeGroup('date', [new DateTime('2010-12-01'), new DateTime('2010-12-10'), new DateTime('2013-02-22')]);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(1, count($results));
    }

    /**
     *
     */
    public function testFindByMustRange()
    {
        $filter = new Filter();
        $filter->must()->range('totalOrders', 2, 4);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(3, count($results));
    }

    /**
     *
     */
    public function testFindByMustNotRangeTest()
    {
        $filter = new Filter();
        $filter->must()->notRange('totalOrders', 2, 4);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(2, count($results));
    }
    //--------------------------------------------------------------------------------
    // MUST NOT FILTER TESTS
    //--------------------------------------------------------------------------------
    /**
     *
     */
    public function testFindByWithMustNotEqual()
    {
        $filter = new Filter();
        $filter->mustNot()->equal('name', 'Ken Sugimori');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(3, count($results));
        foreach ($results as $result) {
            $this->assertFalse(strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustNotNotEqual()
    {
        $filter = new Filter();
        $filter->mustNot()->notEqual('name', 'Ken Sugimori');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(1, count($results));
        foreach ($results as $result) {
            $this->assertTrue(false !== strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustNotContain()
    {
        $filter = new Filter();
        $filter->mustNot()->contain('name', 'Ken');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(3, count($results));
        foreach ($results as $result) {
            $this->assertFalse(strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustNotNotContain()
    {
        $filter = new Filter();
        $filter->mustNot()->notContain('name', 'Ken');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(1, count($results));
        foreach ($results as $result) {
            $this->assertTrue(false !== strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustNotEndsWith()
    {
        $filter = new Filter();
        $filter->mustNot()->endsWith('name', 'mori');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(3, count($results));
        foreach ($results as $result) {
            $this->assertFalse(strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustNotStartsWith()
    {
        $filter = new Filter();
        $filter->mustNot()->startsWith('name', 'Ke');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(3, count($results));
        foreach ($results as $result) {
            $this->assertFalse(strpos($result['name'], 'Ken'));
        }
    }

    /**
     *
     */
    public function testFindByWithMustNotBeLessThan()
    {
        $filter = new Filter();
        $filter->mustNot()->beLessThan('totalOrders', 2);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(4, count($results));
    }

    /**
     *
     */
    public function testFindByWithMustNotBeLessThanOrEqual()
    {
        $filter = new Filter();
        $filter->mustNot()->beLessThanOrEqual('totalOrders', 4);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(1, count($results));
    }

    /**
     *
     */
    public function testFindByWithMustNotBeGreaterThan()
    {
        $filter = new Filter();
        $filter->mustNot()->beGreaterThan('totalOrders', 6);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(4, count($results));
    }

    /**
     *
     */
    public function testFindByWithMustNotBeGreaterThanOrEqual()
    {
        $filter = new Filter();
        $filter->mustNot()->beGreaterThanOrEqual('totalOrders', 6);
        $results = $this->repository->findBy($filter);
        $this->assertEquals(4, count($results));
    }

    /**
     *
     */
    public function testFindByMustNotIncludeGroup()
    {
        $filter = new Filter();
        $filter->mustNot()->includeGroup('date', [new DateTime('2010-12-01'), new DateTime('2010-12-10'), new DateTime('2013-02-22')]);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(1, count($results));
    }

    /**
     *
     */
    public function testFindByMustNotNotIncludeGroup()
    {
        $filter = new Filter();
        $filter->mustNot()->notIncludeGroup('date', [new DateTime('2010-12-01'), new DateTime('2010-12-10'), new DateTime('2013-02-22')]);

        $results = $this->repository->findBy($filter);
        $this->assertEquals(3, count($results));
    }

    /**
     *
     */
    public function testFindByMustNotRange()
    {
        $filter = new Filter();
        $filter->mustNot()->range('totalOrders', 2, 4);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(2, count($results));
    }

    /**
     *
     */
    public function testFindByMustNotNotRangeTest()
    {
        $filter = new Filter();
        $filter->mustNot()->notRange('totalOrders', 2, 4);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(3, count($results));
    }
    //--------------------------------------------------------------------------------
    // SHOULD FILTER TESTS
    //--------------------------------------------------------------------------------
    /**
     *
     */
    public function testFindByWithShouldEqual()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->equal('name', 'Ken Sugimori');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(1, count($results));
    }

    /**
     *
     */
    public function testFindByShouldContain()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->contain('name', 'Ken');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(1, count($results));
    }

    /**
     *
     */
    public function testFindByShouldNotContainTest()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->notContain('name', 'Ken');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(3, count($results));
    }

    /**
     *
     */
    public function testFindByShouldEndsWith()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->endsWith('name', 'mori');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(1, count($results));
    }

    /**
     *
     */
    public function testFindByShouldStartsWith()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->startsWith('name', 'Ke');

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(1, count($results));
    }

    /**
     *
     */
    public function testFindByShouldBeLessThan()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->beLessThan('totalOrders', 6);

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(4, count($results));
    }

    /**
     *
     */
    public function testFindByShouldBeLessThanOrEqual()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->beLessThanOrEqual('totalOrders', 4);

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(3, count($results));
    }

    /**
     *
     */
    public function testFindByShouldBeGreaterThan()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->beGreaterThan('totalOrders', 2);

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(4, count($results));
    }

    /**
     *
     */
    public function testFindByShouldBeGreaterThanOrEqual()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->beGreaterThanOrEqual('totalOrders', 2);

        $fields = new Fields(['name']);

        $results = $this->repository->findBy($filter, null, $fields);

        $this->assertEquals(4, count($results));
    }

    /**
     *
     */
    public function testFindByShouldIncludeGroup()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');

        $filter->should()->includeGroup('date', [new DateTime('2010-12-01'), new DateTime('2010-12-10'), new DateTime('2013-02-22')]);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(3, count($results));
    }

    /**
     *
     */
    public function testFindByShouldNotIncludeGroupTest()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->notIncludeGroup('date', [new DateTime('2010-12-01'), new DateTime('2010-12-10'), new DateTime('2013-02-22')]);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(1, count($results));
    }

    /**
     *
     */
    public function testFindByShouldRange()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->range('totalOrders', 2, 4);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(3, count($results));
    }

    /**
     *
     */
    public function testFindByShouldNotRangeTest()
    {
        $filter = new Filter();
        $filter->must()->contain('name', 'Hideo Kojima');
        $filter->should()->notRange('totalOrders', 2, 4);

        $results = $this->repository->findBy($filter);

        $this->assertEquals(2, count($results));
    }
}
