<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReceiptController extends Controller
{
    public function show(Request $request, $orderId = null)
    {
        // Cek apakah user diizinkan akses receipt via flash session
        if (session('allow_receipt') != $orderId) {
            abort(403, 'Akses tidak diizinkan');
        }

        $order = $orderId
            ? Order::with('items')->find($orderId)
            : Order::with('items')->latest()->first();

        if (!$order) {
            return redirect('/')->withErrors(['receipt' => 'Order tidak ditemukan']);
        }

        return view('layouts.receipt', [
            'customerName' => $order->customer_name,
            'orderType' => $order->order_type,
            'createdAt' => $order->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i'),
            'orderItems' => $order->items,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'order_type' => 'required|in:dine in,take away',
            'items' => 'required|string',
        ]);

        $items = json_decode($request->items, true);

        if (!is_array($items) || empty($items)) {
            return back()->withErrors(['items' => 'Data item tidak valid']);
        }

        DB::beginTransaction();

        try {
            $order = Order::create([
                'customer_name' => $request->input('customer_name'),
                'order_type' => $request->input('order_type'),
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_id' => $item['id'] ?? null,
                    'name' => $item['name'] ?? 'Tidak diketahui',
                    'price' => $item['price'] ?? 0,
                    'quantity' => $item['quantity'] ?? 1,
                    'subtotal' => ($item['price'] ?? 0) * ($item['quantity'] ?? 1),
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            // === MULAI KIRIM KE POS ===
            $payload = [
                'customer_name' => $order->customer_name,
                'customer_email' => null,
                'table_number' => 0,
                'order_type' => ucwords($order->order_type),
                'items' => $order->items->map(function ($item) {
                    return [
                        'menu_id' => $item->menu_id,
                        'quantity' => $item->quantity,
                        'notes' => $item->notes,
                    ];
                })->toArray()
            ];

            Log::info('Token digunakan:', ['token' => env('POS_API_ORDER_TOKEN')]);

            $response = Http::withToken(env('POS_API_ORDER_TOKEN'))
                ->post('https://pos-dev.canngopi.com/api/self-order', $payload);

            Log::info('== Order sent to POS ==', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload
            ]);
            // === SELESAI KIRIM KE POS ===

            DB::commit();

            // Set flash session agar customer bisa akses receipt hanya 1x
            return redirect()
                ->route('receipt.show', ['orderId' => $order->id])
                ->with('allow_receipt', $order->id)
                ->with('clear_cart', true);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal kirim ke POS: ' . $e->getMessage());
            return back()->withErrors(['checkout' => 'Terjadi kesalahan saat memproses pesanan.']);
        }
    }
}
