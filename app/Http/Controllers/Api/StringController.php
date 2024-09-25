<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CsvService;
use App\Services\JsonService;
use Illuminate\Http\Request;

class StringController extends Controller
{
    public function process(Request $request)
    {
        try {
            $data = $request->all();
            $type = $data['type'];
            $entity = $data['entity'];
            $gameType = $data['gameType'];
            $function = $type . $entity;

            $service = $gameType === "sdv"
                ? new JsonService($data['base'], $data['translation'])
                : new CsvService($data['base'], $data['translation']);
            $result = $service->$function();
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage(), 'trace' => $th->getTrace()], 400);
        }
        return response()
            ->json(
                [
                    'result' => json_encode($result['result']),
                    'unmapped' => json_encode($result['unmapped'] ?? null),
                    'leftover' => json_encode($result['leftover'] ?? null)
                ],
                200
            );
    }
}
