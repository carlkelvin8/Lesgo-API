<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Partner;
use App\Models\MenuCategory;
use App\Models\MenuItem;

echo "🍽️ Adding Test Menu Items...\n\n";

$partner = Partner::where('slug', 'test-restaurant')->first();

if (!$partner) {
    echo "❌ Partner not found\n";
    exit(1);
}

echo "✅ Found partner: {$partner->name} (ID: {$partner->id})\n\n";

// Clear existing menu
MenuCategory::where('partner_id', $partner->id)->delete();
echo "🗑️ Cleared existing menu\n\n";

// Create categories and items
$categories = [
    [
        'name' => 'Pizza',
        'icon_emoji' => '🍕',
        'items' => [
            ['name' => 'Sun Sets Pizza', 'description' => 'Signature pizza baked with sunset crust', 'price' => 300.00],
            ['name' => 'Pepperoni Pizza', 'description' => 'Classic pepperoni with mozzarella', 'price' => 350.00],
            ['name' => 'Hawaiian Pizza', 'description' => 'Ham and pineapple delight', 'price' => 320.00],
        ],
    ],
    [
        'name' => 'Rice Meals',
        'icon_emoji' => '🍛',
        'items' => [
            ['name' => 'Sun Sets Sisig', 'description' => 'Savory sizzling sisig made fresh to order', 'price' => 140.00],
            ['name' => 'Chicken Adobo', 'description' => 'Filipino classic with rice', 'price' => 120.00],
            ['name' => 'Pork BBQ', 'description' => 'Grilled pork skewers with rice', 'price' => 130.00],
        ],
    ],
    [
        'name' => 'Chicken',
        'icon_emoji' => '🍗',
        'items' => [
            ['name' => 'Sun Sets Fries', 'description' => 'Crispy fries with your choice of toppings', 'price' => 120.00],
            ['name' => 'Fried Chicken', 'description' => 'Crispy fried chicken with gravy', 'price' => 150.00],
            ['name' => 'Buffalo Wings', 'description' => 'Spicy buffalo wings', 'price' => 180.00],
        ],
    ],
    [
        'name' => 'Pasta',
        'icon_emoji' => '🍝',
        'items' => [
            ['name' => 'Carbonara', 'description' => 'Creamy carbonara pasta', 'price' => 160.00],
            ['name' => 'Spaghetti Bolognese', 'description' => 'Classic meat sauce pasta', 'price' => 150.00],
        ],
    ],
    [
        'name' => 'Desserts',
        'icon_emoji' => '🍰',
        'items' => [
            ['name' => 'Cookies', 'description' => 'Soft-baked cookies for snack cravings', 'price' => 40.00],
            ['name' => 'Chocolate Cake', 'description' => 'Rich chocolate cake slice', 'price' => 80.00],
        ],
    ],
    [
        'name' => 'Drinks',
        'icon_emoji' => '🥤',
        'items' => [
            ['name' => 'Matcha Latte', 'description' => 'Premium matcha drink with smooth finish', 'price' => 80.00],
            ['name' => 'Iced Coffee', 'description' => 'Refreshing iced coffee', 'price' => 70.00],
            ['name' => 'Fresh Juice', 'description' => 'Freshly squeezed orange juice', 'price' => 60.00],
        ],
    ],
];

$totalItems = 0;

foreach ($categories as $index => $categoryData) {
    $category = MenuCategory::create([
        'partner_id' => $partner->id,
        'name' => $categoryData['name'],
        'icon_emoji' => $categoryData['icon_emoji'],
        'is_active' => true,
        'sort_order' => $index + 1,
    ]);
    
    echo "✅ Created category: {$category->name}\n";
    
    foreach ($categoryData['items'] as $itemIndex => $itemData) {
        MenuItem::create([
            'partner_id' => $partner->id,
            'menu_category_id' => $category->id,
            'name' => $itemData['name'],
            'description' => $itemData['description'],
            'price' => $itemData['price'],
            'is_available' => true,
            'is_popular' => $itemIndex === 0, // First item in each category is popular
            'sort_order' => $itemIndex + 1,
        ]);
        
        $totalItems++;
    }
}

echo "\n🎉 Menu created successfully!\n";
echo "   Categories: " . count($categories) . "\n";
echo "   Total Items: {$totalItems}\n\n";

echo "✅ You can now test the merchant side app!\n";
