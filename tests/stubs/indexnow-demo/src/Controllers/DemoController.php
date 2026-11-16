<?php

namespace Azuriom\Plugin\IndexnowDemo\Controllers;

use Azuriom\Http\Controllers\Controller;

class DemoController extends Controller
{
    public function index()
    {
        return 'demo';
    }

    public function show(string $item)
    {
        return e($item);
    }
}
