<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        return view('layouts.order');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|string',
            'customer_name' => 'required|string|max:255',
            'order_type' => 'required|in:dine in,take away',
            'note' => 'nullable|string',
        ]);

        $orderItems = json_decode($validated['items'], true);

        if (!$orderItems || !is_array($orderItems)) {
            return redirect()->back()->withErrors(['items' => 'Data pesanan tidak valid']);
        }

        DB::beginTransaction();

        try {
            $order = Order::create([
                'customer_name' => $validated['customer_name'],
                'order_type' => $validated['order_type'],
                'note' => $validated['note'] ?? null,
            ]);

            foreach ($orderItems as $item) {
                $order->items()->create([
                    'menu_id' => $item['id'] ?? null,
                    'name' => $item['name'] ?? 'Menu tidak diketahui',
                    'price' => isset($item['price']) ? (float)$item['price'] : 0,
                    'quantity' => $item['quantity'] ?? 0,
                    'subtotal' => (isset($item['price'], $item['quantity'])) ? $item['price'] * $item['quantity'] : 0,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()->route('receipt.show', ['orderId' => $order->id]);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal menyimpan pesanan: ' . $e->getMessage()]);
        }
    }
}
