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

use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Filter as FilterInterface;

/**
 * Class MongoDBFilter.
 */
class MongoDBFilter
{
    const MUST_NOT = 'must_not';
    const MUST = 'must';
    const SHOULD = 'should';

    const CONTAINS_PATTERN = 'function() { var p = /%s/i; return p.test(this.%s); }';
    const STARTS_WITH_PATTERN = 'function() { var p = /^%s/i; return p.test(this.%s); }';
    const ENDS_WITH_PATTERN = 'function() { var p = /%s$/i; return p.test(this.%s); }';
    const EQUALS_PATTERN = 'function() { var p = /^%s/i; return p.test(this.%s); }';

    const NOT_CONTAINS_PATTERN = 'function() { var p = /%s/i; return !p.test(this.%s); }';
    const NOT_STARTS_WITH_PATTERN = 'function() { var p = /^%s/i; return !p.test(this.%s); }';
    const NOT_ENDS_WITH_PATTERN = 'function() { var p = /%s$/i; return !p.test(this.%s); }';
    const NOT_EQUALS_PATTERN = 'function() { var p = /^%s$/i; return !p.test(this.%s); }';

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
               // self::apply($where, $filters, 'AND');
                break;

            case self::MUST_NOT:
               // self::apply($where, $filters, 'AND NOT');
                break;

            case self::SHOULD:
                //self::apply($where, $filters, 'OR');
                break;
        }
    }
}
