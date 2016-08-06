<?php

namespace NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB;

use MongoDB\BSON\ObjectID;
use MongoDB\Client;
use MongoDB\Operation\BulkWrite;
use MongoDB\Operation\FindOneAndUpdate;
use NilPortugues\Assert\Assert;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Mapping;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\WriteRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Filter as DomainFilter;
use NilPortugues\Foundation\Infrastructure\ObjectFlattener;

class MongoDBWriteRepository extends BaseMongoDBRepository implements WriteRepository
{
    /** @var \NilPortugues\Serializer\Serializer */
    protected $serializer;

    /**
     * MongoDBWriteRepository constructor.
     *
     * @param Mapping $mapping
     * @param Client  $client
     * @param $databaseName
     * @param $collectionName
     * @param array $options
     */
    public function __construct(Mapping $mapping, Client $client, $databaseName, $collectionName, array $options = [])
    {
        $this->serializer = ObjectFlattener::instance();
        parent::__construct($mapping, $client, $databaseName, $collectionName, $options);
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
        $mappings = $this->mapping->map();

        foreach ($values as $value) {
            Assert::isInstanceOf($value, Identity::class);

            //Create the insertable data structure.
            $flattenedValue = $this->flattenObject($value);
            $insertValue = [];
            foreach ($mappings as $objectProperty => $field) {
                $insertValue[$field] = null;
                if (array_key_exists($objectProperty, $flattenedValue)) {
                    $insertValue[$field] = $flattenedValue[$objectProperty];
                }
            }

            $id = null;
            if (!$this->mapping->autoGenerateId()) {
                $id = $insertValue[$this->mapping->identity()];
            }

            if (null === $id) {
                unset($insertValue[self::MONGODB_OBJECT_ID]);
                $documents[][BulkWrite::INSERT_ONE] = [$insertValue];
            } else {
                $mayRequireInsert[][BulkWrite::INSERT_ONE] = [$insertValue];
                unset($insertValue[$this->mapping->identity()]);
                $documents[][BulkWrite::UPDATE_ONE] = [[$this->mapping->identity() => $id], ['$set' => $insertValue]];
            }
        }

        $addedValues = [];
        if (!empty($documents)) {
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
                $stringIds[] = ($this->mapping->autoGenerateId()) ? new ObjectID($id) : (string) $id;
            }

            $updateFilter = new DomainFilter();
            $updateFilter->must()->includeGroup(self::MONGODB_OBJECT_ID, $stringIds);

            $addedValues = $this->findByHelper($updateFilter);
        }

        return $addedValues;
    }

    /**
     * Returns all instances of the type.
     *
     * @param Filter|null $filter
     *
     * @return array
     */
    protected function findByHelper(Filter $filter = null) : array
    {
        $collection = $this->getCollection();
        $options = $this->options;
        $filterArray = [];

        $this->applyFiltering($filter, $filterArray);
        $result = $collection->find($filterArray, $options)->toArray();

        foreach ($result as &$r) {
            $r = $this->recursiveArrayCopy($r);
        }

        return $result;
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
     */
    public function removeAll(Filter $filter = null)
    {
        $options = $this->options;
        $collection = $this->getCollection();

        if (null === $filter) {
            $collection->drop($options);
        }

        $filterArray = [];
        $this->applyFiltering($filter, $filterArray);

        $collection->deleteMany($filterArray, $options);
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

    /**
     * @param Identity $value
     *
     * @return array
     */
    protected function updateOne(Identity $value) : array
    {
        $flattenedValue = $this->flattenObject($value);
        $mappings = $this->mappingWithoutIdentityColumn();

        $updateValue = [];
        foreach ($mappings as $objectProperty => $field) {
            $updateValue[$field] = $flattenedValue[$objectProperty];
        }

        $id = $value->id();
        $idField = $this->mapping->identity();

        if ($this->mapping->autoGenerateId()) {
            $keys = array_flip($this->mapping->map());
            $primaryKey = $keys[$this->mapping->identity()];

            $id = new ObjectID($flattenedValue[$primaryKey]);
            $idField = self::MONGODB_OBJECT_ID;
        }

        $result = $this->getCollection()->findOneAndUpdate(
            [$idField => $id],
            ['$set' => $updateValue],
            array_merge($this->options, ['returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER])
        );

        return (null !== $result) ? $this->recursiveArrayCopy((array) $result) : [];
    }

    /**
     * @param $value
     *
     * @return array
     */
    protected function flattenObject($value) : array
    {
        return $this->serializer->serialize($value);
    }

    /**
     * @return array
     */
    protected function mappingWithoutIdentityColumn() : array
    {
        $mappings = $this->mapping->map();

        if (false !== ($pos = array_search($this->mapping->identity(), $mappings, true))) {
            unset($mappings[$pos]);
        }

        return $mappings;
    }

    /**
     * @param Identity $value
     *
     * @return array
     */
    protected function addOne(Identity $value) : array
    {
        $flattenedValue = $this->flattenObject($value);
        $mappings = $this->mappingWithoutIdentityColumn();

        $insertValue = [];
        foreach ($mappings as $objectProperty => $field) {
            $insertValue[$field] = $flattenedValue[$objectProperty];
        }

        if (!$this->mapping->autoGenerateId()) {
            $insertValue[$this->mapping->identity()] = $value->id();
        }

        $inserted = $this->getCollection()->insertOne($insertValue, $this->options);
        $fetchCondition = [$this->mapping->identity() => $value->id()];

        if ($this->mapping->autoGenerateId()) {
            $fetchCondition = [self::MONGODB_OBJECT_ID => $inserted->getInsertedId()];
        }

        $result = $this->getCollection()->findOne($fetchCondition, $this->options);

        return (null !== $result) ? $this->recursiveArrayCopy($result) : [];
    }
}
