<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre', 'identificador', 'estado', 'ultima_vez'])]
class Terminal extends Model
{
    protected $table = 'terminales';

    protected function casts(): array
    {
        return ['ultima_vez' => 'datetime'];
    }
}
