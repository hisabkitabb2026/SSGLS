<?php

namespace App\Domains\Settings\Contracts;

interface CurrencyRepository
{
    public function create(array $data);

    public function update(int $id, array $data);

    public function find(int $id);

    public function delete(int $id);

    public function all();

    public function getActive();
}
