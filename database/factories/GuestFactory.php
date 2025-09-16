<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class GuestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_address'       => $this->faker->title(),
            'last_name'          => $this->faker->lastName(),
            'first_name'         => $this->faker->firstName(),
            'email'              => $this->faker->unique()->safeEmail(),
            'mobile'             => $this->faker->unique()->numerify('01#########'),
            'secondary_mobile'   => $this->faker->numerify('01#########'),
            'emergency_contact'  => $this->faker->numerify('01#########'),
            'gender'             => $this->faker->randomElement(['Male', 'Female']),
            'dob'                => $this->faker->date(),
            'dom'                => $this->faker->optional()->date(),
            'pres_country_id'    => 20,
            'pres_city'          => $this->faker->city(),
            'pres_address'       => $this->faker->address(),
            'perm_country_id'    => 20,
            'perm_city'          => $this->faker->city(),
            'perm_address'       => $this->faker->address(),
            'source'             => $this->faker->word(),
            'profession'         => $this->faker->jobTitle(),
            'market'             => $this->faker->word(),
            'company'            => $this->faker->company(),
            'nid'                => $this->faker->numerify('#############'),
            'passport'           => $this->faker->optional()->numerify('##########'),
            'passport_date'      => $this->faker->optional()->date(),
            'passport_exp_date'  => $this->faker->optional()->date(),
            'nationality_id'     => 20,
            'ref_id'             => 1,
            'created_at'         => now(),
            'updated_at'         => now(),
        ];
    }
}
