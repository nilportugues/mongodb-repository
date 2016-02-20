<?php

namespace NilPortugues\Tests\Foundation\Helpers;

use NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB\MongoDBRepository;

class ClientsRepository extends MongoDBRepository
{
    /**
     * {@inheritdoc}
     */
    protected function modelClassName()
    {
        return Clients::class;
    }
}
