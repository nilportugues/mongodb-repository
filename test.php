<?php
use NilPortugues\Foundation\Domain\Model\Repository\Fields;
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
