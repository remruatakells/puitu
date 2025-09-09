<?php

namespace App\Support;

trait ApiResponse
{
    protected function ok($data = [], $meta = null, $extra = [])
    {
        return response()->json(['status' => 'success', 'data' => $data, 'meta' => $meta] + $extra);
    }

    protected function fail($message, $code = 422, $extra = [])
    {
        return response()->json(['status' => 'error', 'message' => $message] + $extra, $code);
    }
}
