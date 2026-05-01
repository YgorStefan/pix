<?php
declare(strict_types=1);

namespace App\Controller;

use Hyperf\HttpServer\Annotation\AutoController;

#[AutoController]
class IndexController
{
    public function index(): array
    {
        return ['service' => 'saque-pix', 'status' => 'ok'];
    }
}
