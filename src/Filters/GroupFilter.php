<?php

namespace Jdexx\EloquentRansack\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jdexx\EloquentRansack\QueryBuilder;

class GroupFilter extends BaseFilter
{
    /**
     * @var array
     */
    private $conditions;

    /**
     * @var string
     */
    private $operator;

    /**
     * @var array
     */
    private $subGroups;

    /**
     * @param array $conditions
     * @param string $operator
     * @param array $subGroups
     */
    public function __construct(array $conditions, $operator = 'AND', array $subGroups = [])
    {
        parent::__construct('', ''); // BaseFilter requires attribute and value, but we don't use them
        $this->conditions = $conditions;
        $this->operator = strtoupper($operator);
        $this->subGroups = $subGroups;
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function apply(Builder $query): Builder
    {
        return $query->where(function ($q) {
            // Apply conditions within this group
            foreach ($this->conditions as $index => $condition) {
                $this->applyCondition($q, $condition, $index);
            }

            // Apply sub-groups
            foreach ($this->subGroups as $index => $subGroup) {
                $this->applySubGroup($q, $subGroup, $index);
            }
        });
    }

    /**
     * @param Builder $query
     * @param array $condition
     * @param int $index
     * @return Builder
     */
    private function applyCondition(Builder $query, array $condition, $index)
    {
        $attribute = $condition['attribute'];
        $predicate = $condition['predicate'];
        $value = $condition['value'];

        // Create the appropriate filter
        $filterClass = QueryBuilder::PREDICATE_FILTERS[$predicate];
        $filter = new $filterClass($attribute, $value);

        // Apply the filter with the appropriate operator
        if ($index === 0) {
            // First condition uses where
            $filter->apply($query);
        } else {
            // Subsequent conditions use orWhere or where depending on the operator
            if ($this->operator === 'OR') {
                $query->orWhere(function ($subQuery) use ($filter) {
                    $filter->apply($subQuery);
                });
            } else {
                $query->where(function ($subQuery) use ($filter) {
                    $filter->apply($subQuery);
                });
            }
        }

        return $query;
    }

    /**
     * @param Builder $query
     * @param GroupFilter $subGroup
     * @param int $index
     * @return Builder
     */
    private function applySubGroup(Builder $query, GroupFilter $subGroup, $index)
    {
        // Apply the sub-group with the appropriate operator
        if ($index === 0 && empty($this->conditions)) {
            // First sub-group and no conditions, use where
            $subGroup->apply($query);
        } else {
            // Subsequent sub-groups use orWhere or where depending on the operator
            if ($this->operator === 'OR') {
                $query->orWhere(function ($subQuery) use ($subGroup) {
                    $subGroup->apply($subQuery);
                });
            } else {
                $query->where(function ($subQuery) use ($subGroup) {
                    $subGroup->apply($subQuery);
                });
            }
        }

        return $query;
    }
}
