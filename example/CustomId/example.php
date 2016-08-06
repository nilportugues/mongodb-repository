<?php

use MongoDB\Client;
use NilPortugues\Example\CustomId\User;
use NilPortugues\Example\CustomId\UserId;
use NilPortugues\Example\CustomId\UserMapping;
use NilPortugues\Example\CustomId\UserRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Order;
use NilPortugues\Foundation\Domain\Model\Repository\Sort;

include_once __DIR__.'./../../vendor/autoload.php';

$client = new Client();
$mapping = new UserMapping();

$repository = new UserRepository($mapping, $client, 'example_db', 'users');
$repository->removeAll();

$user = new User(1, 'nilportugues', 'Nil', 'hello@example.org', new DateTime('2016-01-11'));
$user = $repository->add($user);

$userId = new UserId($user->id());
print_r($repository->find($userId));

echo PHP_EOL;

$filter = new Filter();
$filter->must()->beGreaterThanOrEqual('registeredOn.date', '2016-01-01 00:00:00.000000');
$filter->must()->beLessThan('registeredOn.date', '2016-02-01 00:00:00.000000');

$sort = new Sort();
$sort->setOrderFor('registeredOn.date', new Order('ASC'));

print_r($repository->findBy($filter, $sort));
echo PHP_EOL;
