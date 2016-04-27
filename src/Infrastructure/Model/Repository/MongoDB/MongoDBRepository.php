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
use MongoDB\Operation\BulkWrite;
use MongoDB\Operation\FindOneAndUpdate;
use NilPortugues\Assert\Assert;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Fields;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Page;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Pageable;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\PageRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\ReadRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Sort;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\WriteRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Filter as DomainFilter;
use NilPortugues\Foundation\Domain\Model\Repository\Page as ResultPage;

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
        $this->fetchSpecificFields($fields, $options);

        /** @var BSONDocument $result */
        $result = $this->getCollection()->findOne($this->applyIdFiltering($id), $options);

        return (!empty($result)) ? $this->recursiveArrayCopy($result) : [];
    }

    /**
     * @param Fields $fields
     * @param array  $options
     */
    protected function fetchSpecificFields(Fields $fields = null, array &$options)
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
     * @param $data
     *
     * @return array
     */
    protected function recursiveArrayCopy($data)
    {
        if ($data instanceof BSONDocument) {
            $data = $data->getArrayCopy();
        }

        if (\is_array($data)) {
            foreach ($data as &$value) {
                $value = $this->recursiveArrayCopy($value);
            }
        }

        return $data;
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
        if ($this->exists($value)) {
            return $this->updateOne($value);
        }

        return $this->addOne($value);
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
        $options = $this->options;
        $result = $this->getCollection()->findOne($this->applyIdFiltering($id), $options);

        return (!empty($result)) ? true : false;
    }

    /**
     * @param Identity $value
     *
     * @return array
     */
    protected function updateOne(Identity $value)
    {
        $value = MongoDBTransformer::create()->serialize($value);
        $id = (self::MONGODB_OBJECT_ID === $this->primaryKey) ? new ObjectID($value[self::MONGODB_OBJECT_ID]) : $value[$this->primaryKey];
        unset($value[$this->primaryKey]);

        $result = $this->getCollection()->findOneAndUpdate(
            [$this->primaryKey => $id],
            ['$set' => $value],
            array_merge($this->options, ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER])
        );

        return (null !== $result) ? $this->recursiveArrayCopy((array) $result) : [];
    }

    /**
     * @param Identity $value
     *
     * @return array
     */
    protected function addOne(Identity $value)
    {
        $value = MongoDBTransformer::create()->serialize($value);
        $id = $this->getCollection()->insertOne($value, $this->options)->getInsertedId();

        /** @var \MongoDB\Model\BSONDocument $result */
        $result = $this->getCollection()->findOne(new EntityId($id), $this->options);

        return (null !== $result) ? $this->recursiveArrayCopy($result) : [];
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
        $documents = [];
        $mayRequireInsert = [];
        $options = array_merge($this->options, ['upsert' => true, 'ordered' => false]);

        foreach ($values as $value) {
            Assert::isInstanceOf($value, Identity::class);
            $value = MongoDBTransformer::create()->serialize($value);

            if (self::MONGODB_OBJECT_ID === $this->primaryKey) {
                $id = new ObjectID(
                    (!empty($value[self::MONGODB_OBJECT_ID])) ? $value[self::MONGODB_OBJECT_ID] : null
                );
            } else {
                $id = (!empty($value[$this->primaryKey])) ? $value[$this->primaryKey] : new ObjectID(null);
            }

            if (null === $id) {
                $documents[][BulkWrite::INSERT_ONE] = [$value];
            } else {
                $mayRequireInsert[][BulkWrite::INSERT_ONE] = [$value];
                unset($value[$this->primaryKey]);
                $documents[][BulkWrite::UPDATE_ONE] = [[$this->primaryKey => $id], ['$set' => $value]];
            }
        }

        if (empty($documents)) {
            return [];
        }

        $result = $this->getCollection()->bulkWrite($documents, $options);
        $insertedIds = $result->getInsertedIds();
        $updatedIds = $result->getUpsertedIds();

        if (0 === count($updatedIds) && 0 !== count($mayRequireInsert)) {
            $result = $this->getCollection()->bulkWrite($mayRequireInsert, $options);
            $updatedIds = $result->getInsertedIds();
        }
        unset($mayRequireInsert);

        $stringIds = [];
        $ids = array_merge($updatedIds, $insertedIds);

        foreach ($ids as $id) {
            $stringIds[] = (string) $id;
        }

        $updateFilter = new DomainFilter();
        $updateFilter->must()->includeGroup(self::MONGODB_OBJECT_ID, $stringIds);

        return $this->findBy($updateFilter);
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
        $this->fetchSpecificFields($fields, $options);

        $result = $collection->find($filterArray, $options)->toArray();

        foreach ($result as &$r) {
            $r = $this->recursiveArrayCopy($r);
        }

        return $result;
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

        if (null === $filter) {
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

        if ($pageable) {
            $filterArray = [];
            $this->applyFiltering($pageable->filters(), $filterArray);
            $this->applySorting($pageable->sortings(), $options);

            $total = $collection->count($filterArray, $options);
            $page = $pageable->pageNumber() - 1;
            $page = ($page < 0) ? 1 : $page;

            $options['limit'] = $pageable->pageSize();
            $options['skip'] = $pageable->pageSize() * ($page);

            $distinct = $pageable->distinctFields()->get();
            if (count($distinct) > 0) {
                if (count($distinct) > 1) {
                    throw new \Exception('Mongo cannot select more than one field when calling distinct.');
                }
                $results = (array) $collection->distinct(array_shift($distinct), $filterArray, $this->options);
            } else {
                $this->fetchSpecificFields($pageable->fields(), $options);
                $results = $collection->find($filterArray, $options)->toArray();
            }

            return new ResultPage($results, $total, $pageable->pageNumber(), ceil($total / $pageable->pageSize()));
        }

        $bsonDocumentArray = $collection->find([], $options);

        return new ResultPage(
            $this->bsonDocumentArrayToNativeArray($bsonDocumentArray->toArray()),
            $collection->count([], $options),
            1,
            1
        );
    }

    /**
     * @param BSONDocument[] $bsonDocumentArray
     *
     * @return array
     */
    protected function bsonDocumentArrayToNativeArray($bsonDocumentArray)
    {
        $resultArray = [];

        /** @var BSONDocument[] $bsonDocumentArray */
        foreach ($bsonDocumentArray as $bsonDocument) {
            $bsonDocument = $bsonDocument->getArrayCopy();
            $resultArray[] = $this->recursiveArrayCopy($bsonDocument);
        }

        return $resultArray;
    }

    /**
     * @param array $store
     *
     * @return \MongoDB\InsertManyResult
     */
    protected function addMany(array &$store)
    {
        /* @var \MongoDB\InsertManyResult $result */
        return $this->getCollection()->insertMany($store, $this->options);
    }

    /**
     * Returns all instances of the type meeting $distinctFields values.
     *
     * @param Fields      $distinctFields
     * @param Filter|null $filter
     * @param Sort|null   $sort
     *
     * @return array
     *
     * @throws \Exception
     */
    public function findByDistinct(Fields $distinctFields, Filter $filter = null, Sort $sort = null)
    {
        $collection = $this->getCollection();
        $options = $this->options;
        $filterArray = [];

        $this->applyFiltering($filter, $filterArray);
        $this->applySorting($sort, $options);

        $fields = $distinctFields->get();

        if (count($fields) > 1) {
            throw new \Exception('Mongo cannot select more than one field when calling distinct.');
        }

        $results = (array) $collection->distinct(array_shift($fields), $filterArray, $this->options);

        foreach ($results as &$r) {
            $r = $this->recursiveArrayCopy($r);
        }

        return $results;
    }

    /**
     * Repository data is added or removed as a whole block.
     * Must work or fail and rollback any persisted/erased data.
     *
     * @param callable $transaction
     *
     * @throws \Exception
     */
    public function transactional(callable $transaction)
    {
        try {
            $transaction();
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
