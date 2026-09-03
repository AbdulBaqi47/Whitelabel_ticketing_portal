<?php

namespace App\Interfaces;

interface AccountHeadRepositoryInterface
{
    public function getAll(?string $term, $per_page);

    public function store(array $data);

    public function update(array $data, int|string $uuid);

    public function delete(int|string $uuid);

    public function dropDown();
}
