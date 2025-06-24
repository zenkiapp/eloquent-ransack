<?php

namespace Jdexx\EloquentRansack\Input;

use Jdexx\EloquentRansack\QueryBuilder;

class PredicateSplitter
{
    private array $input;

    public function __construct(array $input)
    {
        $this->input = $input;
    }

    public function call(): array
    {
        $data = [];
        foreach ($this->getInput() as $columnWithPredicate => $value) {
            $result = $this->manipulateInput($columnWithPredicate, $value);
            if (is_null($result)) {
                continue;
            }
            $data[] = $result;
        }
        return $data;
    }

    /**
     * @param mixed $value
     */
    private function manipulateInput(string $columnWithPredicate, $value): ?array
    {
        $matchingPredicates = $this->matchingPredicates($columnWithPredicate);
        if (empty($matchingPredicates)) {
            return null;
        }
        $predicate = $this->determinePredicate($matchingPredicates);
        $attributeName = $this->determineAttributeName($columnWithPredicate, $predicate);

        // Check if we have multiple attributes (OR condition)
        if (is_array($attributeName)) {
            return [
                'attribute' => $attributeName,
                'predicate' => $predicate,
                'value' => $value,
                'is_or' => true,
            ];
        }

        return [
            'attribute' => $attributeName,
            'predicate' => $predicate,
            'value' => $value,
        ];
    }

    private function determineAttributeName(string $columnWithPredicate, string $predicate): string|array
    {
        $predicateStart = strpos($columnWithPredicate, $predicate);
        // -1 to chop off the underscore
        $attributePart = substr($columnWithPredicate, 0, $predicateStart - 1);

        // Check if the attribute part contains "_or_" which indicates OR condition between fields
        if (strpos($attributePart, '_or_') !== false) {
            // Split the attribute part by "_or_" to get individual attributes
            return explode('_or_', $attributePart);
        }

        return $attributePart;
    }

    /**
     * Given predicates like in and not_in use the longest matching predicate
     */
    private function determinePredicate(array $predicates)
    {
        $lengths = array_map('strlen', $predicates);
        $maxLength = max($lengths);
        $key = array_search($maxLength, $lengths);
        return $predicates[$key];
    }

    private function matchingPredicates(string $columnWithPredicate): array
    {
        $predicates = array_keys(QueryBuilder::PREDICATE_FILTERS);
        $matchingPredicates = [];
        foreach ($predicates as $predicate) {
            if (false !== strpos($columnWithPredicate, $predicate)) {
                $matchingPredicates[] = $predicate;
            }
        }
        return $matchingPredicates;
    }

    private function getInput(): array
    {
        return $this->input;
    }
}
