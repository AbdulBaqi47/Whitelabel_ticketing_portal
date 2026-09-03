<?php

namespace App\Interfaces\BankAccounts;

interface BankAccountsRepositoryInterface
{
    public function getAll(?string $term, $per_page);

    public function store(array $data);

    public function findById(int|string $uuid);

    public function update(array $data,int|string $uuid);

    public function delete(int|string $uuid);

    public function bankList();

    public function bankDropDown();

    public function showToAgencies(int $bankId);
}
