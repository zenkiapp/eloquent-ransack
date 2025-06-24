<?php

namespace Jdexx\EloquentRansack\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Jdexx\EloquentRansack\Tests\Support\Models\Post;
use Jdexx\EloquentRansack\Tests\TestCase;

class OrConditionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_or_between_fields(): void
    {
        // Create posts with different attributes
        $post1 = factory(Post::class)->create([
            'name' => 'First Post',
            'description' => 'Some description',
        ]);
        
        $post2 = factory(Post::class)->create([
            'name' => 'Second Post',
            'description' => 'Contains target word',
        ]);
        
        $post3 = factory(Post::class)->create([
            'name' => 'Contains target word',
            'description' => 'Another description',
        ]);
        
        $post4 = factory(Post::class)->create([
            'name' => 'Fourth Post',
            'description' => 'Fourth description',
        ]);

        // Search for posts where either name OR description contains "target"
        $input = [
            'name_or_description_cont' => 'target',
        ];

        $results = Post::ransack($input)->get();

        // Should return posts 2 and 3
        $this->assertCount(2, $results);
        $this->assertTrue($results->contains(function ($result) use ($post2) {
            return $result->id === $post2->id;
        }));
        $this->assertTrue($results->contains(function ($result) use ($post3) {
            return $result->id === $post3->id;
        }));
        $this->assertFalse($results->contains(function ($result) use ($post1) {
            return $result->id === $post1->id;
        }));
        $this->assertFalse($results->contains(function ($result) use ($post4) {
            return $result->id === $post4->id;
        }));
    }

    public function test_or_between_parameters(): void
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
            'description' => 'Third description',
            'published' => true,
        ]);
        
        $post4 = factory(Post::class)->create([
            'name' => 'Fourth Post',
            'description' => 'Fourth description',
            'published' => false,
        ]);

        // Search for posts where name contains "First" OR published is true
        // By default, these would be combined with AND, but with _or=true, they should be combined with OR
        $input = [
            'name_cont' => 'First',
            'published_eq' => true,
            '_or' => 'true',
        ];

        $results = Post::ransack($input)->get();

        // Should return posts 1 and 3 (post 1 matches both conditions, post 3 matches only published=true)
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
}