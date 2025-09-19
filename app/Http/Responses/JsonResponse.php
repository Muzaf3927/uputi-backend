<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse as BaseJsonResponse;

class JsonResponse extends BaseJsonResponse
{
    public function __construct(
        $data = null,
        int $status = 200,
        array $headers = [],
        int $options = 0,
        bool $json = false
    ) {
        // добавляем флаг JSON_UNESCAPED_UNICODE глобально
        $options = $options | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        parent::__construct($data, $status, $headers, $options, $json);
    }
}
