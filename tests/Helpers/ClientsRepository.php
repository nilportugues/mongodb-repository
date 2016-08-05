<?php

namespace NilPortugues\Tests\Foundation\Helpers;

use NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB\MongoDBRepository;
use NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB\MongoDBRepositoryHydrator;

class ClientsRepository extends MongoDBRepository
{
    use MongoDBRepositoryHydrator;
}
