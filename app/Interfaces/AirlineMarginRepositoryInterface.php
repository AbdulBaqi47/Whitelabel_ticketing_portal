<?php

namespace App\Interfaces;

interface AirlineMarginRepositoryInterface
{
    public function getAll(?string $term, $per_page);

    public function store(array $data);

    public function findById(int|string $uuid);

    public function update(array $data, int|string $uuid);

    public function delete(int|string $uuid);
}
