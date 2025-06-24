<?php

namespace Jdexx\EloquentRansack\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Jdexx\EloquentRansack\Tests\Support\Models\Post;
use Jdexx\EloquentRansack\Tests\TestCase;

class NestedConditionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_or_and_conditions()
    {
        // Create posts with different attributes
        $post1 = factory(Post::class)->create([
            'name' => 'First Post',
            'description' => 'Target description',
            'published' => true,
        ]);

        $post2 = factory(Post::class)->create([
            'name' => 'Second Post',
            'description' => 'Another description',
            'published' => false,
        ]);

        $post3 = factory(Post::class)->create([
            'name' => 'Third Post',
            'description' => 'Target description',
            'published' => false,
        ]);

        $post4 = factory(Post::class)->create([
            'name' => 'Fourth Post',
            'description' => 'Another description',
            'published' => true,
        ]);

        // Search for posts where (name equals "First Post" OR name equals "Second Post") AND description equals "Target description"
        $input = [
            '_groups' => [
                [
                    'operator' => 'OR',
                    'conditions' => [
                        ['name_eq' => 'First Post'],
                        ['name_eq' => 'Third Post']
                    ]
                ],
                [
                    'operator' => 'AND',
                    'conditions' => [
                        ['description_eq' => 'Target description']
                    ]
                ]
            ]
        ];

        $results = Post::ransack($input)->get();

        // Should return posts 1 and 3 (post 1 has name "First Post" and description "Target description", 
        // post 3 has name "Third Post" and description "Target description")
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains(function ($result) use ($post1) {
            return $result->id === $post1->id;
        }));
        $this->assertTrue($results->contains(function ($result) use ($post3) {
            return $result->id === $post3->id;
        }));
        $this->assertFalse($results->contains(function ($result) use ($post2) {
            return $result->id === $post2->id;
        }));
        $this->assertFalse($results->contains(function ($result) use ($post4) {
            return $result->id === $post4->id;
        }));
    }

    public function test_deeply_nested_conditions()
    {
        // Create posts with different attributes
        $post1 = factory(Post::class)->create([
            'name' => 'First Post',
            'description' => 'Description one',
            'published' => true,
        ]);

        $post2 = factory(Post::class)->create([
            'name' => 'Second Post',
            'description' => 'Description two',
            'published' => false,
        ]);

        $post3 = factory(Post::class)->create([
            'name' => 'Third Post',
            'description' => 'Description three',
            'published' => true,
        ]);

        $post4 = factory(Post::class)->create([
            'name' => 'Fourth Post',
            'description' => 'Description four',
            'published' => false,
        ]);

        // Search for posts where ((name equals "First Post" OR name equals "Second Post") AND published is true)
        // OR ((name equals "Third Post" OR name equals "Fourth Post") AND published is false)
        $input = [
            '_groups' => [
                [
                    'operator' => 'OR',
                    'conditions' => [
                        [
                            'operator' => 'AND',
                            'conditions' => [
                                [
                                    'operator' => 'OR',
                                    'conditions' => [
                                        ['name_eq' => 'First Post'],
                                        ['name_eq' => 'Second Post']
                                    ]
                                ],
                                ['published_eq' => true]
                            ]
                        ],
                        [
                            'operator' => 'AND',
                            'conditions' => [
                                [
                                    'operator' => 'OR',
                                    'conditions' => [
                                        ['name_eq' => 'Third Post'],
                                        ['name_eq' => 'Fourth Post']
                                    ]
                                ],
                                ['published_eq' => false]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $results = Post::ransack($input)->get();

        // Should return posts 1 and 4 (post 1 has name "First Post" and published true, 
        // post 4 has name "Fourth Post" and published false)
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains(function ($result) use ($post1) {
            return $result->id === $post1->id;
        }));
        $this->assertTrue($results->contains(function ($result) use ($post4) {
            return $result->id === $post4->id;
        }));
        $this->assertFalse($results->contains(function ($result) use ($post2) {
            return $result->id === $post2->id;
        }));
        $this->assertFalse($results->contains(function ($result) use ($post3) {
            return $result->id === $post3->id;
        }));
    }
}
