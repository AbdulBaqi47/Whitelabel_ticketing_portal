<?php

namespace App\Interfaces;

interface CityRepositoryInterface
{

    public function getAll();

    public function store(array $data);

    public function findById(int|string $uuid);

    public function update(array $data, int|string $uuid);

    public function delete(int|string $uuid);
}
