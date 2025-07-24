<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MenuController extends Controller
{
    public function getMenus()
    {
        $response = Http::withToken(env('POS_API_TOKEN'))
            ->get('https://pos-dev.canngopi.com/api/menus');

        if ($response->failed()) {
            // Log error ke laravel.log
            Log::error('Gagal ambil data dari POS API', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            // Tampilkan error asli dari POS ke response
            return response()->json([
                'error' => 'Gagal ambil data dari POS API',
                'status' => $response->status(),
                'body' => $response->body()
            ], 500);
        }

        $data = $response->json();

        if (!isset($data['data']) || !is_array($data['data'])) {
            Log::error('Format data POS tidak valid', ['response' => $data]);

            return response()->json([
                'error' => 'Format data tidak valid dari POS API',
                'raw_response' => $data
            ], 500);
        }

        return response()->json($data['data']);
    }
}
