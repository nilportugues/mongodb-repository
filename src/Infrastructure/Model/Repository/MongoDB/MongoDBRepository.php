<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 15/02/16
 * Time: 21:00.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB;

use MongoDB\BSON\ObjectID;
use MongoDB\Client;
use MongoDB\Driver\Exception\InvalidArgumentException;
use MongoDB\Model\BSONDocument;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Fields;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Page;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Pageable;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\PageRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\ReadRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Sort;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\WriteRepository;

/**
 * Class MongoDBRepository.
 */
class MongoDBRepository implements ReadRepository, WriteRepository, PageRepository
{
    const MONGODB_OBJECT_ID = '_id';
    const MONGODB_PROJECTION = 'projection';

    /**
     * @var string
     */
    protected $databaseName;
    /**
     * @var string
     */
    protected $collectionName;
    /**
     * @var Client
     */
    protected $client;
    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var \MongoDB\Collection
     */
    protected $collection;

    /**
     * If not using ObjectID, field being used as primary key.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * MongoDBRepository constructor.
     *
     * @param Client $client
     * @param        $databaseName
     * @param        $collectionName
     * @param array  $options
     */
    public function __construct(Client $client, $databaseName, $collectionName, array $options = [])
    {
        $this->client = $client;
        $this->databaseName = (string) $databaseName;
        $this->collectionName = (string) $collectionName;
        $this->options = $options;
    }

    /**
     * Returns all instances of the type.
     *
     * @param Filter|null $filter
     * @param Sort|null   $sort
     * @param Fields|null $fields
     *
     * @return array
     */
    public function findBy(Filter $filter = null, Sort $sort = null, Fields $fields = null)
    {
        $collection = $this->getCollection();
        $options = $this->options;
        $filterArray = [];

        $this->applyFiltering($filter, $filterArray);
        $this->applySorting($sort, $options);
        $this->getSpecificFields($fields, $options);

        return $collection->find($filterArray, $options)->toArray();
    }

    /**
     * @return \MongoDB\Collection
     */
    protected function getCollection()
    {
        if (null === $this->collection) {
            $this->collection = $this->client->selectCollection($this->databaseName, $this->collectionName);
        }

        return $this->collection;
    }

    /**
     * @param Filter $filter
     * @param array  $filterArray
     */
    protected function applyFiltering(Filter $filter = null, array &$filterArray)
    {
        if (null !== $filter) {
            MongoDBFilter::filter($filterArray, $filter);
        }
    }

    /**
     * @param Sort  $sort
     * @param array $options
     */
    protected function applySorting(Sort $sort = null, array &$options)
    {
        if (null !== $sort) {
            MongoDBSorter::sort($options, $sort);
        }
    }

    /**
     * @param Fields $fields
     * @param array  $options
     */
    protected function getSpecificFields(Fields $fields = null, array &$options)
    {
        if (null !== $fields) {
            $fields = $fields->get();
            $options[self::MONGODB_PROJECTION] = array_combine(
                $fields,
                array_fill(0, count($fields), 1)
            );
        }
    }

    /**
     * Returns the total amount of elements in the repository given the restrictions provided by the Filter object.
     *
     * @param Filter|null $filter
     *
     * @return int
     */
    public function count(Filter $filter = null)
    {
        $options = $this->options;
        $collection = $this->getCollection();

        $filterArray = [];
        $this->applyFiltering($filter, $filterArray);

        return $collection->count($filterArray, $options);
    }

    /**
     * Returns whether an entity with the given id exists.
     *
     * @param $id
     *
     * @return bool
     */
    public function exists(Identity $id)
    {
        return null !== $this->find($id);
    }

    /**
     * Retrieves an entity by its id.
     *
     * @param Identity    $id
     * @param Fields|null $fields
     *
     * @return array
     */
    public function find(Identity $id, Fields $fields = null)
    {
        $options = $this->options;
        $this->getSpecificFields($fields, $options);

        /** @var BSONDocument $result */
        $result = $this->getCollection()->findOne($this->applyIdFiltering($id), $options);

        return (!empty($result)) ? $result : [];
    }

    /**
     * @param Identity $id
     *
     * @return array
     */
    protected function applyIdFiltering(Identity $id)
    {
        try {
            $filter = [self::MONGODB_OBJECT_ID => new ObjectID($id->id())];
        } catch (InvalidArgumentException $e) {
            $filter = [$this->primaryKey => $id->id()];
        }

        return $filter;
    }

    /**
     * Adds a new entity to the storage.
     *
     * @param Identity $value
     *
     * @return mixed
     */
    public function add(Identity $value)
    {
        $options = $this->options;
        $id = $this->getCollection()->insertOne($value, $this->options)->getInsertedId();

        return $result = $this->getCollection()->findOne(new EntityId($id), $options);
    }

    /**
     * Adds a collections of entities to the storage.
     *
     * @param array $values
     *
     * @return mixed
     */
    public function addAll(array $values)
    {
        $this->getCollection()->insertMany($values, $this->options);
    }

    /**
     * Removes the entity with the given id.
     *
     * @param $id
     */
    public function remove(Identity $id)
    {
        $this->getCollection()->deleteOne($this->applyIdFiltering($id), $this->options);
    }

    /**
     * Removes all elements in the repository given the restrictions provided by the Filter object.
     * If $filter is null, all the repository data will be deleted.
     *
     * @param Filter $filter
     *
     * @return bool
     */
    public function removeAll(Filter $filter = null)
    {
        $options = $this->options;
        $collection = $this->getCollection();

        if (null == $filter) {
            $collection->drop($options);

            return;
        }

        $filterArray = [];
        $this->applyFiltering($filter, $filterArray);

        $collection->deleteMany($filterArray, $options);
    }

    /**
     * Returns a Page of entities meeting the paging restriction provided in the Pageable object.
     *
     * @param Pageable $pageable
     *
     * @return Page
     */
    public function findAll(Pageable $pageable = null)
    {
        $options = $this->options;
        $collection = $this->getCollection();
    }
}
