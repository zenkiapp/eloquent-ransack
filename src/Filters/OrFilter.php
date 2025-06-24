<?php

namespace Jdexx\EloquentRansack\Filters;

use Illuminate\Database\Eloquent\Builder;
use Jdexx\EloquentRansack\QueryBuilder;

class OrFilter extends BaseFilter
{
    private array $attributes;
    private string $predicate;
    private $value;

    public function __construct(array $attributes, string $predicate, $value)
    {
        $this->attributes = $attributes;
        $this->predicate = $predicate;
        $this->value = $value;
    }

    public function apply(Builder $query): Builder
    {
        return $query->where(function ($query) {
            foreach ($this->attributes as $index => $attribute) {
                $filterClass = QueryBuilder::PREDICATE_FILTERS[$this->predicate];
                $filter = new $filterClass($attribute, $this->value);
                
                if ($index === 0) {
                    // For the first attribute, use where to start the OR group
                    $filter->apply($query);
                } else {
                    // For subsequent attributes, use orWhere to add OR conditions
                    $this->applyOrCondition($query, $attribute);
                }
            }
        });
    }

    private function applyOrCondition(Builder $query, string $attribute): Builder
    {
        $value = $this->value;
        
        // Apply the appropriate OR condition based on the predicate
        switch ($this->predicate) {
            case 'eq':
                return $query->orWhere($attribute, '=', $value);
            case 'not_eq':
                return $query->orWhere($attribute, '!=', $value);
            case 'cont':
                $value = '%' . $value . '%';
                return $query->orWhere($attribute, 'LIKE', $value);
            case 'in':
                return $query->orWhereIn($attribute, (array) $value);
            case 'not_in':
                return $query->orWhereNotIn($attribute, (array) $value);
            case 'lt':
                return $query->orWhere($attribute, '<', $value);
            case 'lte':
                return $query->orWhere($attribute, '<=', $value);
            case 'gt':
                return $query->orWhere($attribute, '>', $value);
            case 'gte':
                return $query->orWhere($attribute, '>=', $value);
            default:
                return $query;
        }
    }
}