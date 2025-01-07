<?php

namespace Aqqo\OData\Database\Factories;
use Aqqo\OData\Tests\Testclasses\AppendModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppendModelFactory extends Factory
{
    protected $model = AppendModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firstname' => $this->faker->firstName,
            'lastname' => $this->faker->lastName,
        ];
    }
}