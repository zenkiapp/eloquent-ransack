Inspired by [the ransack gem](https://github.com/activerecord-hackery/ransack) eloquent ransack's goal is to provide a simplistic filtering method on eloquent models.

## Usage

The Ransackable trait provides a ransack scope that you pass an array of input to. All filters should be of the form `column_name_predicate` where predicate is one of the options listed in the table below.

## Available Filtering Types

| predicate | example            | sql                            |
| --------- | ------------------ | ------------------------------ |
| eq        | name_eq            | where "name" = ?               |
| not_eq    | name_not_eq        | where "name" != ?              |
| cont      | name_cont          | where "name" LIKE ?            |
| in        | category_id_in     | where "category_id" in (?)     |
| not_in    | category_id_not_in | where "category_id" not in (?) |
| lt        | date_lt            | where "date" < ?               |
| lte       | date_lte           | where "date" <= ?              |
| gt        | date_gt            | where "date" > ?               |
| gte       | date_gte           | where "date" >= ?              |

## OR Conditions

You can use OR conditions in three ways:

### 1. OR Between Fields

You can search across multiple fields with the same predicate using the `_or_` syntax:

```php
// Search for posts where either name OR description contains "example"
$params = [
    'name_or_description_cont' => 'example'
];
$posts = Post::ransack($params)->get();
```

### 2. OR Between Parameters

By default, multiple parameters are combined with AND logic. You can use the `_or` parameter to combine them with OR logic instead:

```php
// Search for posts where name contains "example" OR published is true
$params = [
    'name_cont' => 'example',
    'published_eq' => true,
    '_or' => 'true'
];
$posts = Post::ransack($params)->get();
```

### 3. Nested AND/OR Conditions

For more complex queries, you can use the `_groups` parameter to create nested AND/OR conditions:

```php
// Search for posts where (name equals "a" OR name equals "b") AND description equals "c"
$params = [
    '_groups' => [
        [
            'operator' => 'OR',
            'conditions' => [
                ['name_eq' => 'a'],
                ['name_eq' => 'b']
            ]
        ],
        [
            'operator' => 'AND',
            'conditions' => [
                ['description_eq' => 'c']
            ]
        ]
    ]
];
$posts = Post::ransack($params)->get();
```

This allows you to create complex queries with nested groups of conditions, each with its own operator (AND/OR).

## Example

Eloquent Model

```php
use Jdexx\EloquentRansack\Ransackable;

class Post
{
  use Ransackable;
}
```

Form

```html
<form>
  <input type="text" name="name_eq" />
</form>
```

Controller

```php
class PostsController
{
  public function index(Request $request)
  {
    $params = $request->all();
    $posts = Post::ransack($params)->get();
  }
}
```
