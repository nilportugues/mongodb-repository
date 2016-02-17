<?php
use NilPortugues\Foundation\Domain\Model\Repository\Fields;
use NilPortugues\Foundation\Domain\Model\Repository\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Order;
use NilPortugues\Foundation\Domain\Model\Repository\Pageable;
use NilPortugues\Foundation\Domain\Model\Repository\Sort;
use NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB\MongoDBRepository;

include 'vendor/autoload.php';

class ObjectId implements \NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity{

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

$client = new \MongoDB\Client();

$mongoRepository = new MongoDBRepository($client, 'test', 'users');
/*
var_dump($mongoRepository->count());

var_dump(
    $mongoRepository->exists(
        new ObjectId('56c241f74985af52b3434277')
    )
);

print_r(
    $mongoRepository->find(
        new ObjectId('56c241f74985af52b3434277'),
        (new Fields(['item', 'category']))
    )
);

print_r($mongoRepository->find(new ObjectId(1)));

print_r(
    $mongoRepository->findBy(
        null,
        (new Sort(['item'], new Order('DESC'))),
        null
    )
);
*/

$mongoRepository = new MongoDBRepository($client, 'demo', 'zips');

$sort = new Sort(['city'], new Order('ASC'));

$filter = new Filter();

$filter->must()->beGreaterThan('pop', 15000);
$filter->must()->beLessThan('pop', 20000);
$filter->mustNot()->equal('state', 'AK');

$fields = new Fields(['city', 'state', 'pop']);

$pageable = new Pageable(2, 20, $sort, $filter, $fields);
$results = $mongoRepository->findAll($pageable);

print_r($results->content());
