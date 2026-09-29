<?php

use Illuminate\Support\Facades\Route;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Staff;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Support\Facades\Http;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Http;
Route::post('/login', function (Illuminate\Http\Request $request) {
    $staff = Staff::where('login_info', $request->login_info)->first();

    if (!$staff || !Hash::check($request->password, $staff->password)) {
        return response()->json(['message' => 'That email or password isn\'t right.'], 401);
    }

    $token = $staff->createToken('pos-token')->plainTextToken;

    return response()->json([
        'staff' => $staff,
        'token' => $token,
    ]);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/products', function (Illuminate\Http\Request $request) {
    $query = Product::query();
    if ($request->filled('search')) {
        $query->where('name', 'like', '%' . $request->search . '%');
    }
    return $query->get();
});

    Route::get('/products/barcode/{barcode}', function ($barcode) {
        $product = Product::where('barcode', $barcode)->first();

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return $product;
    });

    Route::post('/products/{id}/request-delete-otp', function ($id) {
    $user = auth()->user();
    if ($user->role !== 'manager') {
        return response()->json(['message' => 'Only managers can do this.'], 403);
    }

    $product = Product::findOrFail($id);
    $otp = rand(100000, 999999);
    Cache::put("delete-otp-{$user->staff_id}-{$id}", $otp, now()->addMinutes(5));

    Mail::raw("Your code to delete '{$product->name}' is: {$otp}\nThis expires in 5 minutes.", function ($message) use ($user) {
        $message->to($user->login_info)->subject('Omie Store — Delete confirmation code');
    });

    return response()->json(['message' => 'Code sent to your email.']);
});

Route::delete('/products/{id}', function (Illuminate\Http\Request $request, $id) {
    $user = auth()->user();
    if ($user->role !== 'manager') {
        return response()->json(['message' => 'Only managers can do this.'], 403);
    }

    $cachedOtp = Cache::get("delete-otp-{$user->staff_id}-{$id}");
    if (!$cachedOtp || (string) $cachedOtp !== (string) $request->otp) {
        return response()->json(['message' => 'That code is wrong or has expired.'], 422);
    }

    $product = Product::findOrFail($id);

    \App\Models\ProductDeletion::create([
        'product_name' => $product->name,
        'barcode' => $product->barcode,
        'deleted_by' => $user->staff_id,
    ]);

    $product->delete();
    Cache::forget("delete-otp-{$user->staff_id}-{$id}");

    return response()->json(['message' => 'Product deleted.']);
});

    Route::get('/products/lookup-external/{barcode}', function ($barcode) {
    try {
        $response = Http::timeout(5)->get("https://world.openfoodfacts.org/api/v0/product/{$barcode}.json");
        $data = $response->json();

        if (($data['status'] ?? 0) == 1) {
            $product = $data['product'];
            return response()->json([
                'found' => true,
                'name' => $product['product_name'] ?? null,
                'brand' => $product['brands'] ?? null,
            ]);
        }

        return response()->json(['found' => false]);
    } catch (\Exception $e) {
        return response()->json(['found' => false]);
    }
});

Route::post('/assistant', function (Illuminate\Http\Request $request) {
    $userMessage = $request->message;

    $response = Http::post(
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:generateContent?key=' . env('GEMINI_API_KEY'),
        // 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . env('GEMINI_API_KEY'),
        [
            'systemInstruction' => [
                'parts' => [
                    ['text' => 'You are a friendly, upbeat assistant inside Omie Store, a point-of-sale app for a Nigerian shop. '
                        . 'You help cashiers and managers use the app (scanning, checkout, inventory, closing a shift) '
                        . 'and answer general shop questions. Keep answers short, warm, and in simple English. Use an occasional fitting emoji.']
                ],
            ],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userMessage]]],
            ],
        ]
    );

    $data = $response->json();

    \Illuminate\Support\Facades\Log::info('Gemini raw response', $data ?? []);

    $reply = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$reply) {
        return response()->json(['reply' => "Sorry, I couldn't think of an answer just now. Try again?", 'debug' => $data]);
    }

    return response()->json(['reply' => $reply]);
});

// Auto-calculated numbers for closing the shift
Route::get('/eod/summary', function (Illuminate\Http\Request $request) {
    $date = $request->query('date', now()->toDateString());
    $user = auth()->user();

    $sales = $user->role === 'manager'
        ? Sale::whereDate('created_at', $date)->get()
        : Sale::whereDate('created_at', $date)->where('staff_id', $user->staff_id)->get();

    return [
        'date' => $date,
        'total_sales' => $sales->sum('total_amount'),
        'total_transactions' => $sales->count(),
    ];
});

// Submit an end of day report
Route::post('/eod', function (Illuminate\Http\Request $request) {
    $user = auth()->user();
    $date = $request->date ?? now()->toDateString();

    $sales = $user->role === 'manager'
        ? Sale::whereDate('created_at', $date)->get()
        : Sale::whereDate('created_at', $date)->where('staff_id', $user->staff_id)->get();

    $report = EodReport::updateOrCreate(
        ['staff_id' => $user->staff_id, 'report_date' => $date],
        [
            'role' => $user->role,
            'total_sales' => $sales->sum('total_amount'),
            'total_transactions' => $sales->count(),
            'satisfied_customers' => $request->satisfied_customers,
            'notes' => $request->notes,
        ]
    );

    return $report;
});

// Manager view: every report submitted for a given day
Route::get('/eod', function (Illuminate\Http\Request $request) {
    if (auth()->user()->role !== 'manager') {
        return response()->json(['message' => 'Only managers can view this.'], 403);
    }
    $date = $request->query('date', now()->toDateString());
    return EodReport::with('staff')->whereDate('report_date', $date)->orderBy('role')->get();
});

    Route::get('/sales/by-date', function (Illuminate\Http\Request $request) {
    if (auth()->user()->role !== 'manager') {
        return response()->json(['message' => 'Only managers can view this.'], 403);
    }

    $date = $request->query('date', now()->toDateString());

    $sales = Sale::with(['items', 'staff'])
        ->whereDate('created_at', $date)
        ->orderByDesc('created_at')
        ->get();

    return [
        'date' => $date,
        'sales' => $sales,
        'total_revenue' => $sales->sum('total_amount'),
        'sale_count' => $sales->count(),
    ];
});

// List all staff (manager only)
Route::get('/staff', function () {
    if (auth()->user()->role !== 'manager') {
        return response()->json(['message' => 'Only managers can view this.'], 403);
    }
    return Staff::select('staff_id', 'name', 'role', 'login_info')->get();
});

// Create a new staff account (manager only)
Route::post('/staff', function (Illuminate\Http\Request $request) {
    if (auth()->user()->role !== 'manager') {
        return response()->json(['message' => 'Only managers can add employees.'], 403);
    }

    if (Staff::where('login_info', $request->login_info)->exists()) {
        return response()->json(['message' => 'That email is already in use.'], 422);
    }

    if (!in_array($request->role, ['manager', 'cashier'])) {
        return response()->json(['message' => 'Role must be manager or cashier.'], 422);
    }

    $staff = Staff::create([
        'name' => $request->name,
        'login_info' => $request->login_info,
        'role' => $request->role,
        'password' => \Illuminate\Support\Facades\Hash::make($request->password),
    ]);

    return $staff;
});

    Route::post('/products', function (Illuminate\Http\Request $request) {
        try {
            $product = Product::create($request->only([
                'name', 'price', 'barcode', 'quantity_in_stock', 'expiry_date', 'supplier_id'
            ]));
            return $product;
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json(['message' => 'A product with that barcode already exists.'], 422);
            }
            return response()->json(['message' => 'Something went wrong saving this product. Please try again.'], 500);
        }
    });

    Route::put('/products/{id}', function (Illuminate\Http\Request $request, $id) {
        try {
            $product = Product::findOrFail($id);
            $product->update($request->only(['quantity_in_stock']));
            return $product;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'That product no longer exists.'], 404);
        }
    });

    Route::delete('/products/{id}', function ($id) {
        if (auth()->user()->role !== 'manager') {
            return response()->json(['message' => 'Only managers can remove products.'], 403);
        }
        try {
            Product::findOrFail($id)->delete();
            return response()->json(['message' => 'Product removed.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'That product no longer exists.'], 404);
        }
    });

    Route::post('/sales', function (Illuminate\Http\Request $request) {
        try {
            $sale = Sale::create([
                'staff_id' => $request->staff_id,
                'total_amount' => $request->total_amount,
                'payment_type' => $request->payment_type,
            ]);

            foreach ($request->items as $item) {
                SaleItem::create([
                    'sale_id' => $sale->sale_id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price_at_time_of_sale' => $item['price_at_sale'],
                ]);

                $product = Product::find($item['product_id']);
                $product?->decrement('quantity_in_stock', $item['quantity']);
            }

            return $sale->load('items');
        } catch (\Illuminate\Database\QueryException $e) {
    return response()->json(['message' => $e->getMessage()], 500); // TEMPORARY: shows the real error
}
    });

    Route::delete('/sales/{id}', function ($id) {
        $user = auth()->user();

        if ($user->role !== 'manager') {
            return response()->json(['message' => 'Only managers can void a sale.'], 403);
        }

        try {
            $sale = Sale::findOrFail($id);
            $sale->delete();
            return response()->json(['message' => 'Sale voided.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'That sale no longer exists.'], 404);
        }
    });

});