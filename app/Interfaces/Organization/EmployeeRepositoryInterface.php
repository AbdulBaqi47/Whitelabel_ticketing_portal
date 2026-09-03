<?php

namespace App\Interfaces\Organization;

interface EmployeeRepositoryInterface {
    
    public function getAll(?string $term, $per_page);

    public function store(array $data);

    public function statusChange(string $uuid);

    public function delete(string $uuid);

    public function update(array $data, int|string $uuid);
}