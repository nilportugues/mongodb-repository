<?php

namespace NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB;

use MongoDB\Model\BSONDocument;
use NilPortugues\Foundation\Domain\Model\Repository\Page as ResultPage;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Page;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Pageable;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\PageRepository;

class MongoDBPageRepository extends BaseMongoDBRepository implements PageRepository
{
    /**
     * Returns a Page of entities meeting the paging restriction provided in the Pageable object.
     *
     * @param Pageable $pageable
     *
     * @return Page
     *
     * @throws \Exception
     */
    public function findAll(Pageable $pageable = null) : Page
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

            $pageSize = $pageable->pageSize();
            $pageSize = ($pageSize > 0) ? $pageSize : 1;

            $options['limit'] = $pageSize;
            $options['skip'] = $pageSize * ($page);

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

            return new ResultPage($results, $total, $pageable->pageNumber(), ceil($total / $pageSize));
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
    protected function bsonDocumentArrayToNativeArray($bsonDocumentArray) : array
    {
        $resultArray = [];

        /** @var BSONDocument[] $bsonDocumentArray */
        foreach ($bsonDocumentArray as $bsonDocument) {
            $bsonDocument = $bsonDocument->getArrayCopy();
            $resultArray[] = $this->recursiveArrayCopy($bsonDocument);
        }

        return $resultArray;
    }
}
