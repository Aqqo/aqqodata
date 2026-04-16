<?php

namespace Aqqo\OData\Database\Factories;

use Aqqo\OData\Tests\Testclasses\RelatedModel;
use Aqqo\OData\Tests\Testclasses\TestModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RelatedModel>
 */
class RelatedModelFactory extends Factory
{
    protected $model = RelatedModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_model_id' => TestModel::factory(),
            'name' => $this->faker->name(),
            'full_name' => $this->faker->optional()->name(),
            'cost' => $this->faker->optional()->numberBetween(0, 1000),
        ];
    }
}
