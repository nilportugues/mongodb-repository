<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 7/02/16
 * Time: 17:59.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NilPortugues\Example\Persistence\MongoDB;

use NilPortugues\Example\Service\UserAdapter;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Fields;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Pageable;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Sort;
use NilPortugues\Foundation\Domain\Model\Repository\Page;
use NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB\MongoDBRepository;

/**
 * Class UserRepository.
 */
class UserRepository extends MongoDBRepository
{
    /**
     * @var UserAdapter
     */
    protected $userAdapter;

    /**
     * UserRepository constructor.
     *
     * @param \MongoDB\Client $client
     * @param UserAdapter     $userAdapter
     */
    public function __construct($client, UserAdapter $userAdapter)
    {
        $this->userAdapter = $userAdapter;
        parent::__construct($client, 'exampledb', 'users');
    }


    /**
     * {@inheritdoc}
     */
    public function find(Identity $id, Fields $fields = null)
    {
        $model = parent::find($id, $fields);

        return $this->userAdapter->fromMongoDB($model);
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(Filter $filter = null, Sort $sort = null, Fields $fields = null)
    {
        $modelArray = parent::findBy($filter, $sort, $fields);

        return $this->fromMongoDBArray($modelArray);
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(Pageable $pageable = null)
    {
        $page = parent::findAll($pageable);

        return new Page(
            $this->fromMongoDBArray($page->content()),
            $page->totalElements(),
            $page->pageNumber(),
            $page->totalPages(),
            $page->sortings(),
            $page->filters(),
            $page->fields()
        );
    }

    /**
     * @param array $modelArray
     *
     * @return array
     */
    protected function fromMongoDBArray(array $modelArray)
    {
        $results = [];
        foreach ($modelArray as $model) {
            $results[] = $this->userAdapter->fromMongoDB($model);
        }

        return $results;
    }
}
