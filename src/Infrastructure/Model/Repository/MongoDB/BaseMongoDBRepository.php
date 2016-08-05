<?php

namespace NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB;

use MongoDB\BSON\ObjectID;
use MongoDB\Client;
use MongoDB\Model\BSONDocument;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Fields;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Mapping;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Sort;

abstract class BaseMongoDBRepository
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
    /** @var Mapping */
    protected $mapping;

    /**
     * BaseMongoDBRepository constructor.
     *
     * @param Mapping $mapping
     * @param Client  $client
     * @param $databaseName
     * @param $collectionName
     * @param array $options
     */
    protected function __construct(Mapping $mapping, Client $client, $databaseName, $collectionName, array $options = [])
    {
        $this->mapping = $mapping;
        $this->client = $client;
        $this->databaseName = (string) $databaseName;
        $this->collectionName = (string) $collectionName;
        $this->options = $options;
    }

    /**
     * @param Mapping $mapping
     * @param Client  $client
     * @param $databaseName
     * @param $collectionName
     * @param array $options
     *
     * @return static
     */
    public static function create(Mapping $mapping, Client $client, $databaseName, $collectionName, array $options = [])
    {
        return new static($mapping, $client, $databaseName, $collectionName, $options);
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
            MongoDBFilter::filter($filterArray, $filter, $this->mapping);
        }
    }

    /**
     * @param Identity $id
     *
     * @return array
     */
    protected function applyIdFiltering(Identity $id)
    {
        $filter = [$this->mapping->identity() => $id->id()];
        try {
            if ($this->mapping->autoGenerateId()) {
                $filter = [self::MONGODB_OBJECT_ID => new ObjectID($id->id())];
            }
        } catch (\InvalidArgumentException $e) {
            $filter = [$this->mapping->identity() => $id->id()];
        } finally {
            return $filter;
        }
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

        if ($data instanceof ObjectID) {
            $data = $data->__toString();
        }

        if (\is_array($data)) {
            foreach ($data as &$value) {
                $value = $this->recursiveArrayCopy($value);
            }
        }

        return $data;
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
     * @param Sort  $sort
     * @param array $options
     */
    protected function applySorting(Sort $sort = null, array &$options)
    {
        if (null !== $sort) {
            MongoDBSorter::sort($options, $sort, $this->mapping);
        }
    }

    /**
     * @param Fields $fields
     *
     * @return array
     */
    protected function getColumns(Fields $fields = null)
    {
        $newFields = [];

        if ($fields) {
            foreach ($this->mapping->map() as $objectProperty => $tableColumn) {
                if (in_array($objectProperty, $fields->get())) {
                    $newFields[$objectProperty] = $tableColumn;
                }
            }
        }

        return $newFields;
    }
}
