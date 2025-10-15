<?php
namespace Aqqo\OData\Database\Factories;

use Aqqo\OData\Tests\Testclasses\TestModelWithResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestModelWithResource>
 */
class TestModelWithResourceFactory extends Factory
{
    protected $model = \Aqqo\OData\Tests\Testclasses\TestModelWithResource::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}
