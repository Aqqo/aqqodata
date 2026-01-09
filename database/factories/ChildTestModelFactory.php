<?php
namespace Aqqo\OData\Database\Factories;

use Aqqo\OData\Tests\Testclasses\ChildTestModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChildTestModel>
 */
class ChildTestModelFactory extends Factory
{
    protected $model = \Aqqo\OData\Tests\Testclasses\ChildTestModel::class;

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
