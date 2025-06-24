<?php

namespace Jdexx\EloquentRansack;

use Illuminate\Database\Eloquent\Builder;
use Jdexx\EloquentRansack\Filters\BaseFilter;
use Jdexx\EloquentRansack\Filters\ContainsFilter;
use Jdexx\EloquentRansack\Filters\EqualsFilter;
use Jdexx\EloquentRansack\Filters\InFilter;
use Jdexx\EloquentRansack\Filters\LessThanFilter;
use Jdexx\EloquentRansack\Filters\LessThanOrEqualFilter;
use Jdexx\EloquentRansack\Filters\GreaterThanFilter;
use Jdexx\EloquentRansack\Filters\GreaterThanOrEqualFilter;
use Jdexx\EloquentRansack\Filters\NotEqualFilter;
use Jdexx\EloquentRansack\Filters\NotInFilter;
use Jdexx\EloquentRansack\Filters\OrFilter;
use Jdexx\EloquentRansack\Input\PredicateSplitter;

class QueryBuilder
{
    public const PREDICATE_FILTERS = [
        'cont' => ContainsFilter::class,
        'eq' => EqualsFilter::class,
        'not_eq' => NotEqualFilter::class,
        'in' => InFilter::class,
        'not_in' => NotInFilter::class,
        'lt' => LessThanFilter::class,
        'lte' => LessThanOrEqualFilter::class,
        'gt' => GreaterThanFilter::class,
        'gte' => GreaterThanOrEqualFilter::class,
    ];

    private array $modelAttributes;
    private array $input;
    private Builder $query;

    public function __construct(Builder $query, string $class, array $input)
    {
        $this->modelAttributes = $this->fetchDatabaseColumns($class);
        $this->query = $query;
        $this->input = array_filter($input);
    }

    public function call(): Builder
    {
        $query = $this->getQuery();

        // Check if we have grouped conditions
        if (isset($this->input['_groups'])) {
            // Handle grouped conditions
            $this->applyGroupedConditions($query, $this->input['_groups']);
        } else {
            // Handle regular conditions
            $filters = $this->buildFilters();

            // Check if we need to apply OR logic between parameters
            $useOrBetweenParams = isset($this->input['_or']) && $this->input['_or'] === 'true';

            if ($useOrBetweenParams && count($filters) > 1) {
                // Apply filters with OR logic between them
                $query->where(function ($q) use ($filters) {
                    foreach ($filters as $index => $filter) {
                        if ($index === 0) {
                            // Apply the first filter normally to start the OR group
                            $filter->apply($q);
                        } else {
                            // Apply subsequent filters with OR logic
                            $q->orWhere(function ($subQuery) use ($filter) {
                                $filter->apply($subQuery);
                            });
                        }
                    }
                });
            } else {
                // Apply filters with AND logic (default behavior)
                foreach ($filters as $filter) {
                    $filter->apply($query);
                }
            }
        }

        return $query;
    }

    /**
     * Apply grouped conditions to the query
     * 
     * @param Builder $query
     * @param array $groups
     * @return Builder
     */
    private function applyGroupedConditions(Builder $query, array $groups): Builder
    {
        // Process the groups
        return $query->where(function ($q) use ($groups) {
            foreach ($groups as $group) {
                $this->applyGroup($q, $group);
            }
        });
    }

    /**
     * Apply a group of conditions to the query
     * 
     * @param Builder $query
     * @param array $group
     * @return Builder
     */
    private function applyGroup(Builder $query, array $group): Builder
    {
        $operator = $group['operator'] ?? 'AND';
        $conditions = $group['conditions'] ?? [];

        // Apply the group with a where clause
        $query->where(function ($q) use ($operator, $conditions) {
            foreach ($conditions as $index => $condition) {
                // Each condition can be either a simple condition or a nested group
                if (isset($condition['operator'])) {
                    // This is a nested group
                    if ($index === 0) {
                        $this->applyGroup($q, $condition);
                    } else {
                        // Apply with the appropriate operator
                        if (strtoupper($operator) === 'OR') {
                            $q->orWhere(function ($subQuery) use ($condition) {
                                $this->applyGroup($subQuery, $condition);
                            });
                        } else {
                            $q->where(function ($subQuery) use ($condition) {
                                $this->applyGroup($subQuery, $condition);
                            });
                        }
                    }
                } else {
                    // This is a simple condition
                    foreach ($condition as $key => $value) {
                        // Split the key into attribute and predicate
                        $data = $this->splitConditionKey($key, $value);
                        if ($data) {
                            $filter = $this->buildFilterForInput($data);
                            if ($filter) {
                                if ($index === 0) {
                                    $filter->apply($q);
                                } else {
                                    // Apply with the appropriate operator
                                    if (strtoupper($operator) === 'OR') {
                                        $q->orWhere(function ($subQuery) use ($filter) {
                                            $filter->apply($subQuery);
                                        });
                                    } else {
                                        $q->where(function ($subQuery) use ($filter) {
                                            $filter->apply($subQuery);
                                        });
                                    }
                                }
                            }
                        }
                    }
                }
            }
        });

        return $query;
    }

    /**
     * Split a condition key into attribute and predicate
     * 
     * @param string $key
     * @param mixed $value
     * @return array|null
     */
    private function splitConditionKey(string $key, $value): ?array
    {
        $predicateSplitter = new PredicateSplitter([$key => $value]);
        $data = $predicateSplitter->call();

        return $data[0] ?? null;
    }

    private function getQuery(): Builder
    {
        return $this->query;
    }

    private function buildFilters(): array
    {
        $filters = [];
        $data = $this->splitPredicates();

        foreach ($data as $inputData) {
            $filter = $this->buildFilterForInput($inputData);
            if ($filter) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    private function buildFilterForInput(array $data): ?BaseFilter
    {
        $attribute = $data['attribute'];
        $predicate = $data['predicate'];
        $value = $data['value'];
        $isOr = $data['is_or'] ?? false;

        // Handle OR conditions between fields
        if ($isOr && is_array($attribute)) {
            // Check if all attributes exist in the model
            foreach ($attribute as $attr) {
                if ($this->modelDoesNotHaveAttribute($attr)) {
                    return null;
                }
            }

            // Create an OrFilter with all attributes
            return new OrFilter($attribute, $predicate, $value);
        }

        // Handle regular filters
        if ($this->modelDoesNotHaveAttribute($attribute)) {
            return null;
        }
        $filterClass = self::PREDICATE_FILTERS[$predicate];
        return new $filterClass($attribute, $value);
    }

    private function modelDoesNotHaveAttribute(string $attribute): bool
    {
        return !in_array($attribute, $this->getModelAttributes());
    }

    private function getModelAttributes(): array
    {
        return $this->modelAttributes;
    }

    private function getInput(): array
    {
        return $this->input;
    }

    private function fetchDatabaseColumns(string $class): array
    {
        $model = new $class();
        $table = $model->getTable();
        return $model->getConnection()->getSchemaBuilder()->getColumnListing($table);
    }

    private function splitPredicates(): array
    {
        $input = $this->getInput();
        $predicateSplitter = new PredicateSplitter($input);
        return $predicateSplitter->call();
    }
}
