<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Surfsidemedia\Shoppingcart\Facades\Cart;

class CartController extends Controller
{
    public function index(){
        $items = Cart::instance('cart')->content();
        return view('cart', compact('items'));
    }

    public function add_to_cart(Request $request)
    {
        Cart::instance('cart')->add($request->id,$request->name,$request->quantity,$request->price)->associate('App\Models\Product');
        return redirect()->back();
    }

    public function increase_cart_quantity($rowId)
    {
        $product = Cart::instance('cart')->get($rowId);
        $qty = $product->qty + 1;
        Cart::instance('cart')->update($rowId, $qty);
        return redirect()->back();
    }

    public function decrease_cart_quantity($rowId)
    {
        $product = Cart::instance('cart')->get($rowId);
        $qty = $product->qty - 1;
        Cart::instance('cart')->update($rowId, $qty);
        return redirect()->back();
    }

    public function remove_item($rowId)
    {
        Cart::instance('cart')->remove($rowId);
        return redirect()->back();
    }

    public function empty_cart()
    {
        Cart::instance('cart')->destroy();
        return redirect()->back();
    }

    public function apply_coupon_code(Request $request)
    {
        $coupon_code = $request->coupon_code;
        if (!empty($coupon_code)) {
            $cartValue = (float) str_replace(',', '', Cart::instance('cart')->subtotal());
            $coupon = Coupon::where('code', $coupon_code)
                ->whereDate('expiry_date', '>=', Carbon::today())
                ->where('cart_value', '<=', $cartValue)
                ->first();
            if (!$coupon) {
                return redirect()->back()->with('error', 'Invalid coupon code!');
            } else {
                Session::put('coupon', [
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'value' => $coupon->value,
                    'cart_value' => $coupon->cart_value,
                ]);
                $this->calculateDiscount();
                return redirect()->back()->with('success', 'Coupon applied successfully');
            }
        } else {
            return redirect()->back()->with('error', 'Invalid coupon code');
        }
    }


    public function calculateDiscount(){
        $discount = 0;
        if(Session::has('coupon')){
            if (Session::get('coupon')['type'] == 'fixed') {
                $discount = Session::get('coupon')['value'];
            } else {
                $discount = (Cart::instance('cart')->subtotal() * (Session::get('coupon')['value']/100));
            }
        }
        $subTotalAfterDiscount = Cart::instance('cart')->subtotal() - $discount;
        $taxAfterDiscount = ($subTotalAfterDiscount * config('cart.tax')) / 100;
        $totalAfterDiscount = $subTotalAfterDiscount + $taxAfterDiscount;
        Session::put('discounts', [
            'discount' => number_format(floatval($discount), 2, '.', ''),
            'subtotal' => number_format(floatval($subTotalAfterDiscount), 2, '.', ''),
            'tax' => number_format(floatval($taxAfterDiscount), 2, '.', ''),
            'total' => number_format(floatval($totalAfterDiscount), 2, '.', ''),
        ]);
    }

    public function remove_coupon_code()
    {
        Session::forget('coupon');
        Session::forget('discounts');
        return redirect()->back()->with('success', 'Coupon has been removed');
    }
}
