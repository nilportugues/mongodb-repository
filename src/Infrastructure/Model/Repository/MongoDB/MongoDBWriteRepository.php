<?php

namespace NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB;

use MongoDB\BSON\ObjectID;
use MongoDB\Operation\BulkWrite;
use MongoDB\Operation\FindOneAndUpdate;
use NilPortugues\Assert\Assert;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\WriteRepository;
use NilPortugues\Foundation\Domain\Model\Repository\Filter as DomainFilter;

class MongoDBWriteRepository extends BaseMongoDBRepository implements WriteRepository
{
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
        $options = $this->options;
        $result = $this->getCollection()->findOne($this->applyIdFiltering($id), $options);

        return (!empty($result)) ? true : false;
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

        return $this->findByHelper($updateFilter);
    }

    /**
     * Returns all instances of the type.
     *
     * @param Filter|null $filter
     *
     * @return array
     */
    protected function findByHelper(Filter $filter = null)
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
}
