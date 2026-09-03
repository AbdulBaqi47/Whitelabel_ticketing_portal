<?php

namespace App\Interfaces;

interface ConnectorRepositoryInterface
{
    public function update(array $data);

    public function statusChange($status, string $type);

    public function findByType(string $type);

    public function dropDown();
}
