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
    const EQUALS_PATTERN = '/^%s/i.test(this.%s)';

    const NOT_CONTAINS_PATTERN = '!(/%s/i.test(this.%s))';
    const NOT_STARTS_WITH_PATTERN = '!(/^%s/i.test(this.%s))';
    const NOT_ENDS_WITH_PATTERN = '!(/%s$/i.test(this.%s))';
    const NOT_EQUALS_PATTERN = '!(/^%s$/i.test(this.%s))';

    /**
     * @param array           $filterArray
     * @param FilterInterface $filter
     */
    public static function filter(array &$filterArray, FilterInterface $filter)
    {
        foreach ($filter->filters() as $condition => $filters) {
            $filters = self::removeEmptyFilters($filters);
            if (count($filters) > 0) {
                self::processConditions($filterArray, $condition, $filters);
            }
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
     * @param array  $filterArray
     * @param string $condition
     * @param array  $filters
     */
    private static function processConditions(array &$filterArray, $condition, array &$filters)
    {
        switch ($condition) {
            case self::MUST:
                self::apply($filterArray, $filters, '$and');
                break;

            case self::MUST_NOT:
                self::apply($filterArray, $filters, '$not');
                break;

            case self::SHOULD:
                self::apply($filterArray, $filters, '$or');
                break;
        }
    }

    /**
     * @param array $filterArray
     * @param array $filters
     * @param       $boolean
     */
    protected static function apply(array &$filterArray, array $filters, $boolean)
    {
        foreach ($filters as $filterName => $valuePair) {
            foreach ($valuePair as $key => $value) {
                if (is_array($value) && count($value) > 0) {
                    if (count($value) > 1) {
                        switch ($filterName) {
                            case BaseFilter::RANGES:
                                $filterArray[$key]['$gte'] = $value[0];
                                $filterArray[$key]['$lte'] = $value[1];
                                break;
                            case BaseFilter::NOT_RANGES:
                                $filterArray[$key]['$lt'] = $value[0];
                                $filterArray[$key]['$gt'] = $value[1];
                                break;
                            case BaseFilter::GROUP:
                                $filterArray[$key]['$in'] = $value;
                                break;
                            case BaseFilter::NOT_GROUP:
                                $filterArray[$key]['$nin'] = $value;
                                break;
                        }
                        break;
                    }
                    $value = array_shift($value);
                }

                switch ($filterName) {
                    case BaseFilter::GREATER_THAN_OR_EQUAL:
                        $filterArray[$key]['$gte'] = $value;
                        break;
                    case BaseFilter::GREATER_THAN:
                        $filterArray[$key]['$gt'] = $value;
                        break;
                    case BaseFilter::LESS_THAN_OR_EQUAL:
                        $filterArray[$key]['$lte'] = $value;
                        break;
                    case BaseFilter::LESS_THAN:
                        $filterArray[$key]['$lt'] = $value;
                        break;
                    case BaseFilter::CONTAINS:
                        $filterArray['$where'][] = sprintf(
                            ('$not' !== $boolean) ? self::CONTAINS_PATTERN : self::NOT_CONTAINS_PATTERN,
                            $value,
                            $key
                        );
                        break;
                    case BaseFilter::NOT_CONTAINS:
                        $filterArray['$where'][] = sprintf(
                            ('$not' !== $boolean) ? self::NOT_CONTAINS_PATTERN : self::CONTAINS_PATTERN,
                            $value,
                            $key
                        );
                        break;
                    case BaseFilter::STARTS_WITH:
                        $filterArray['$where'][] = sprintf(
                            ('$not' !== $boolean) ? self::STARTS_WITH_PATTERN : self::NOT_STARTS_WITH_PATTERN,
                            $value,
                            $key
                        );
                        break;
                    case BaseFilter::ENDS_WITH:
                        $filterArray['$where'][] = sprintf(
                            ('$not' !== $boolean) ? self::ENDS_WITH_PATTERN : self::NOT_ENDS_WITH_PATTERN,
                            $value,
                            $key
                        );
                        break;
                    case BaseFilter::EQUALS:
                        $filterArray['$where'][] = sprintf(
                            ('$not' !== $boolean) ? self::EQUALS_PATTERN : self::NOT_EQUALS_PATTERN,
                            $value,
                            $key
                        );
                        break;
                    case BaseFilter::NOT_EQUAL:
                        $filterArray['$where'][] = sprintf(
                            ('$not' !== $boolean) ? self::NOT_EQUALS_PATTERN : self::EQUALS_PATTERN,
                            $value,
                            $key
                        );
                        break;
                }
            }
        }

        if (!empty($filterArray['$where'])) {
            $filterArray['$where'] = 'return '.implode(('$or' === $boolean) ? ' || ' : ' && ', $filterArray['$where']);
        }
    }
}
