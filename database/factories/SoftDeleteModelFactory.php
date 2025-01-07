<?php

namespace Aqqo\OData\Database\Factories;

use Aqqo\OData\Tests\Testclasses\SoftDeleteModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class SoftDeleteModelFactory extends Factory
{
    protected $model = SoftDeleteModel::class;

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