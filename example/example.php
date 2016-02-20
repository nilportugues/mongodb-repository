<?php

use NilPortugues\Example\Domain\User;
use NilPortugues\Example\Domain\UserId;
use NilPortugues\Example\Persistence\MongoDB\UserRepository;
use NilPortugues\Example\Service\UserAdapter;
use NilPortugues\Foundation\Domain\Model\Repository\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Order;
use NilPortugues\Foundation\Domain\Model\Repository\Sort;

include_once '../vendor/autoload.php';

//-------------------------------------------------------------------------------------------------------------
// - Create database if does not exist
//-------------------------------------------------------------------------------------------------------------

$client = new \MongoDB\Client();

//-------------------------------------------------------------------------------------------------------------
// - Create dummy data
//-------------------------------------------------------------------------------------------------------------

$models[] = new User(new UserId(1), 'Admin User', new DateTimeImmutable('2016-02-18'));

for ($i = 2; $i <= 20; ++$i) {
    $models[] = new User(
        new UserId($i),
        'Dummy User '.$i,
        new DateTimeImmutable((new DateTime())->setDate(2016, rand(1, 12), rand(1, 27))->format('Y-m-d'))
    );
}


$repository = new UserRepository($client, new UserAdapter());
$repository->removeAll();
$repository->addAll($models);

//-------------------------------------------------------------------------------------------------------------
// - getUserAction
//-------------------------------------------------------------------------------------------------------------

$filter = new Filter();
$filter->must()->equal('userId', 1);
print_r($repository->findBy($filter));

//-------------------------------------------------------------------------------------------------------------
// - getUsersRegisteredLastMonth
//-------------------------------------------------------------------------------------------------------------

$filter = new Filter();
$filter->must()->notIncludeGroup('userId', [2, 5]);
$filter->must()->beGreaterThan('registrationDate', new DateTime('2016-03-01'));

$sort = new Sort();
$sort->setOrderFor('registrationDate', new Order('ASC'));

print_r($repository->findBy($filter, $sort));
