<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Menu;

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_name' => 'required|string|max:255',
            'order_type' => 'required|in:dine in,take away',
            'note' => 'nullable|string',
            'items' => 'required|string' 
        ]);

        $items = json_decode($request->items, true);

        if (!is_array($items) || empty($items)) {
            return redirect()->back()->withErrors(['items' => 'Item pesanan tidak valid.']);
        }

        $order = Order::create([
            'customer_name' => $request->customer_name,
            'order_type' => $request->order_type,
            'note' => $request->note,
        ]);

        foreach ($items as $item) {
            $menu = Menu::findOrFail($item['id']);
            $quantity = (int)$item['quantity'];
            $price = $menu->price;
            $subtotal = $price * $quantity;

            OrderItem::create([
                'order_id' => $order->id,
                'menu_id' => $menu->id,
                'name' => $menu->name,
                'quantity' => $quantity,
                'price' => $price,
                'subtotal' => $subtotal,
            ]);
        }

        return redirect()->route('receipt.show', ['orderId' => $order->id]);
    }
}
