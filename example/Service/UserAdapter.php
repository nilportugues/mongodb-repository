<?php

namespace NilPortugues\Example\Service;

use DateTimeImmutable;
use NilPortugues\Example\Domain\User;
use NilPortugues\Example\Domain\UserId;

class UserAdapter
{
    /**
     * @param array $model
     *
     * @return \NilPortugues\Example\Domain\User
     */
    public function fromMongoDB( $model)
    {
        return new User(
            new UserId($model['userId']),
            $model['name'],
            new DateTimeImmutable($model['registrationDate']['date'])
        );
    }
}
