<?php

namespace NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB;

use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Fields;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Filter;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Identity;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Pageable;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Sort;
use NilPortugues\Foundation\Domain\Model\Repository\Page;

trait MongoDBRepositoryHydrator
{
    /**
     * {@inheritdoc}
     */
    public function find(Identity $id, Fields $fields = null)
    {
        $result = parent::find($id, $fields);

        if (empty($result)) {
            return;
        }

        return $this->mapping->fromArray($result);
    }

    /**
     * {@inheritdoc}
     */
    public function findBy(Filter $filter = null, Sort $sort = null, Fields $fields = null) : array
    {
        $results = parent::findBy($filter, $sort, $fields);

        return $this->hydrateArray($results);
    }

    /**
     * @param array $results
     *
     * @return array
     */
    protected function hydrateArray(array $results)
    {
        return array_map(function ($result) {
            return $this->mapping->fromArray($result);
        }, $results);
    }

    /**
     * {@inheritdoc}
     */
    public function findAll(Pageable $pageable = null) : \NilPortugues\Foundation\Domain\Model\Repository\Contracts\Page
    {
        $page = parent::findAll($pageable);

        $results = $page->content();
        if (!empty($results) && array_key_exists(0, $results) && is_array($results[0])) {
            $results = $this->hydrateArray($page->content());
        }

        return new Page(
            $results,
            $page->totalElements(),
            $page->pageNumber(),
            $page->totalPages(),
            $page->sortings(),
            $page->filters(),
            $page->fields()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findByDistinct(Fields $distinctFields, Filter $filter = null, Sort $sort = null) : array
    {
        $results = parent::findByDistinct($distinctFields, $filter, $sort);

        if (!empty($results) && array_key_exists(0, $results) && is_scalar($results[0])) {
            return $results;
        }

        return $this->hydrateArray($results);
    }

    /**
     * {@inheritdoc}
     */
    public function add(Identity $value)
    {
        $result = parent::add($value);

        if (empty($result)) {
            return;
        }

        return $this->mapping->fromArray($result);
    }

    /**
     * {@inheritdoc}
     */
    public function addAll(array $values)
    {
        $results = parent::addAll($values);

        return $this->hydrateArray($results);
    }
}
