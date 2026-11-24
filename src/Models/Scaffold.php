<?php

namespace Emmanpbarrameda\FilamentScaffold2\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/********************************************
 * ? Placeholder model — Filament resources need one
 * ? detector: no table behind it; CreateScaffold returns `new Scaffold` and never saves it
 ********************************************/
class Scaffold extends Model
{
    use HasFactory;
}
