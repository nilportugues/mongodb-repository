<?php

/**
 * Author: Nil Portugués Calderó <contact@nilportugues.com>
 * Date: 15/02/16
 * Time: 20:59.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB;

use NilPortugues\Foundation\Domain\Model\Repository\Contracts\BaseFilter;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Filter as FilterInterface;
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Mapping;

/**
 * Class MongoDBFilter.
 */
class MongoDBFilter
{
    const MUST_NOT = 'must_not';
    const MUST = 'must';
    const SHOULD = 'should';

    const CONTAINS_PATTERN = '/%s/i.test(this.%s)';
    const STARTS_WITH_PATTERN = '/^%s/i.test(this.%s)';
    const ENDS_WITH_PATTERN = '/%s$/i.test(this.%s)';

    const NOT_CONTAINS_PATTERN = '/^((?!%s.))/i.test(this.%s)';
    const NOT_STARTS_WITH_PATTERN = '/^(?!%s).+/i.test(this.%s)';
    const NOT_ENDS_WITH_PATTERN = '!(/%s$/i.test(this.%s))';
    const NOT_RANGES_REGEX = '%s < this.%s && %s > this.%s ';

    /** @var Mapping */
    protected static $mapping;

    /**
     * @param array           $filterArray
     * @param FilterInterface $filter
     * @param Mapping         $mapping
     */
    public static function filter(array &$filterArray, FilterInterface $filter, Mapping $mapping)
    {
        self::$mapping = $mapping;
        $columns = $mapping->map();

        foreach ($filter->filters() as $condition => $filters) {
            $filters = self::removeEmptyFilters($filters);
            if (count($filters) > 0) {
                self::processConditions($columns, $filterArray, $condition, $filters);
            }
        }

        if (!empty($filterArray['$or']) && count($filterArray['$or']) > 0) {
            $filterArray['$nor'] = $filterArray['$and'];
            unset($filterArray['$and']);
        }
    }

    /**
     * @param array $filters
     *
     * @return array
     */
    private static function removeEmptyFilters(array $filters)
    {
        $filters = array_filter($filters, function ($v) {
            return count($v) > 0;
        });

        return $filters;
    }

    /**
     * @param array $columns
     * @param array $filterArray
     * @param $condition
     * @param array $filters
     */
    private static function processConditions(array &$columns, array &$filterArray, $condition, array &$filters)
    {
        switch ($condition) {
            case self::MUST:
                self::applyFilter($columns, $filterArray, $filters, '$and', '$and', '&&');
                break;

            case self::MUST_NOT:
                self::applyFilter($columns, $filterArray, $filters, '$not', '$and', '&&');
                break;

            case self::SHOULD:
                self::applyFilter($columns, $filterArray, $filters, '$or', '$or', '||');
                break;
        }
    }

    /**
     * @param array $columns
     * @param array $filterArray
     * @param array $filters
     * @param $logicOperator
     * @param $mongoOperator
     * @param $javascriptOperator
     */
    protected static function applyFilter(
        array &$columns,
        array &$filterArray,
        array &$filters,
        $logicOperator,
        $mongoOperator,
        $javascriptOperator
    ) {
        $rawConditions = [];
        $regexConditions = [];

        if (empty($filterArray[$mongoOperator])) {
            $filterArray[$mongoOperator] = [];
        }

        self::apply($columns, $rawConditions, $filters, $logicOperator, $regexConditions);

        if (!empty($regexConditions)) {
            $rawConditions['$where'] = implode(' '.$javascriptOperator.' ', $regexConditions);
        }

        $filterArray[$mongoOperator] = array_merge($filterArray[$mongoOperator], [$rawConditions]);
    }

    /**
     * @param array $columns
     * @param array $filterArray
     * @param array $filters
     * @param $conditional
     * @param array $where
     */
    protected static function apply(array &$columns, array &$filterArray, array $filters, $conditional, array &$where)
    {
        foreach ($filters as $filterName => $valuePair) {
            foreach ($valuePair as $key => $value) {
                $key = self::fetchColumnName($columns, $key);

                if (is_array($value) && count($value) > 0) {
                    $value = array_values($value);
                    if (count($value[0]) > 1) {
                        switch ($filterName) {
                            case BaseFilter::RANGES:
                                if ('$not' === $conditional) {
                                    $where[] = self::writeNotRangesRegex($key, $value);
                                    break;
                                }
                                $filterArray[$key]['$gte'] = $value[0][0];
                                $filterArray[$key]['$lte'] = $value[0][1];

                                break;
                            case BaseFilter::NOT_RANGES:
                                if ('$not' === $conditional) {
                                    $filterArray[$key]['$gte'] = $value[0][0];
                                    $filterArray[$key]['$lte'] = $value[0][1];
                                    break;
                                }
                                $where[] = self::writeNotRangesRegex($key, $value);

                                break;
                        }
                    } else {
                        switch ($filterName) {
                            case BaseFilter::GROUP:
                                $filterArray[$key][('$not' !== $conditional) ? '$in' : '$nin'] = $value;
                                break;
                            case BaseFilter::NOT_GROUP:
                                $filterArray[$key][('$not' !== $conditional) ? '$nin' : '$in'] = $value;
                                break;
                        }
                    }
                }
                $value = (array) $value;
                $value = array_shift($value);

                switch ($filterName) {
                    case BaseFilter::GREATER_THAN_OR_EQUAL:
                        $filterArray[$key][('$not' !== $conditional) ? '$gte' : '$lt'] = $value;
                        break;
                    case BaseFilter::GREATER_THAN:
                        $filterArray[$key][('$not' !== $conditional) ? '$gt' : '$lte'] = $value;
                        break;
                    case BaseFilter::LESS_THAN_OR_EQUAL:
                        $filterArray[$key][('$not' !== $conditional) ? '$lte' : '$gt'] = $value;
                        break;
                    case BaseFilter::LESS_THAN:
                        $filterArray[$key][('$not' !== $conditional) ? '$lt' : '$gte'] = $value;
                        break;
                    case BaseFilter::CONTAINS:
                        $where[] = sprintf(
                            ('$not' !== $conditional) ? self::CONTAINS_PATTERN : self::NOT_CONTAINS_PATTERN,
                            $value,
                            $key
                        );
                        break;
                    case BaseFilter::NOT_CONTAINS:
                        $where[] = sprintf(
                            ('$not' !== $conditional) ? self::NOT_CONTAINS_PATTERN : self::CONTAINS_PATTERN,
                            $value,
                            $key
                        );
                        break;
                    case BaseFilter::STARTS_WITH:
                        $where[] = sprintf(
                            ('$not' !== $conditional) ? self::STARTS_WITH_PATTERN : self::NOT_STARTS_WITH_PATTERN,
                            $value,
                            $key
                        );
                        break;
                    case BaseFilter::NOT_STARTS:
                        $where[] = sprintf(
                            ('$not' !== $conditional) ? self::NOT_STARTS_WITH_PATTERN : self::STARTS_WITH_PATTERN,
                            $value,
                            $key
                        );
                        break;
                    case BaseFilter::ENDS_WITH:
                        $where[] = sprintf(
                            ('$not' !== $conditional) ? self::ENDS_WITH_PATTERN : self::NOT_ENDS_WITH_PATTERN,
                            $value,
                            $key
                        );
                        break;
                    case BaseFilter::NOT_ENDS:
                        $where[] = sprintf(
                            ('$not' !== $conditional) ? self::NOT_ENDS_WITH_PATTERN : self::ENDS_WITH_PATTERN,
                            $value,
                            $key
                        );
                        break;
                    case BaseFilter::EQUALS:
                        if ('$not' !== $conditional) {
                            $filterArray[$key] = $value;
                        } else {
                            $filterArray[$key] = ['$ne' => $value];
                        }
                        break;
                    case BaseFilter::NOT_EQUAL:
                        if ('$not' !== $conditional) {
                            $filterArray[$key] = ['$ne' => $value];
                        } else {
                            $filterArray[$key] = $value;
                        }
                        break;

                    case BaseFilter::EMPTY_FILTER:
                        if ('$not' !== $conditional) {
                            $filterArray[$key] = ['$exists' => true, '$size' => 0];
                        } else {
                            $filterArray[$key] = ['$exists' => true, '$not' => ['$size' => 0]];
                        }
                        break;

                    case BaseFilter::NOT_EMPTY:
                        if ('$not' !== $conditional) {
                            $filterArray[$key] = ['$exists' => true, '$not' => ['$size' => 0]];
                        } else {
                            $filterArray[$key] = ['$exists' => true, '$size' => 0];
                        }
                        break;
                }
            }
        }
    }

    /**
     * @param string $key
     * @param array  $value
     *
     * @return string
     */
    protected static function writeNotRangesRegex($key, array &$value)
    {
        return sprintf(
            self::NOT_RANGES_REGEX,
            is_string($value[0][0]) ? "'".$value[0][0]."'" : $value[0][0],
            $key,
            is_string($value[0][1]) ? "'".$value[0][1]."'" : $value[0][1],
            $key
        );
    }

    /**
     * @param $columns
     * @param $propertyName
     *
     * @return int
     */
    protected static function fetchColumnName(array &$columns, $propertyName)
    {
        if ($propertyName === BaseMongoDBRepository::MONGODB_OBJECT_ID) {
            return $propertyName;
        }

        if (empty($columns[$propertyName])) {
            throw new \RuntimeException(sprintf('Property %s has no associated column.', $propertyName));
        }

        return $columns[$propertyName];
    }
}
