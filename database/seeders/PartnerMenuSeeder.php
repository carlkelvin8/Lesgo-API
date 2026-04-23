<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Partner;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Support\Str;

class PartnerMenuSeeder extends Seeder
{
    public function run(): void
    {
        echo "🍕 Seeding LesEat Restaurants...\n";
        $this->seedRestaurants();
        
        echo "\n🛒 Seeding LesBuy Stores...\n";
        $this->seedStores();
        
        echo "\n✅ Partner and Menu seeding completed!\n";
    }

    private function seedRestaurants(): void
    {
        // Jollibee
        $jollibee = Partner::create([
            'name' => 'Jollibee - Claveria',
            'slug' => 'jollibee-claveria',
            'description' => 'Station 2, Barangay Balabag, Boracay',
            'category' => 'restaurant',
            'tags' => ['fast food', 'chicken', 'burger', 'fries'],
            'cuisine_types' => ['Filipino', 'Fast Food'],
            'rating' => 4.7,
            'total_reviews' => 1250,
            'delivery_fee' => 49.00,
            'min_order_amount' => 150,
            'estimated_delivery_minutes' => 30,
            'is_open' => true,
            'is_featured' => true,
            'accepts_online_payment' => true,
            'status' => 'active',
        ]);

        $chickenjoyCategory = MenuCategory::create([
            'partner_id' => $jollibee->id,
            'name' => 'Chickenjoy',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        MenuItem::create([
            'partner_id' => $jollibee->id,
            'menu_category_id' => $chickenjoyCategory->id,
            'name' => '1-pc Chickenjoy',
            'description' => 'Crispy and juicy fried chicken',
            'price' => 85.00,
            'is_available' => true,
            'is_popular' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ]);

        MenuItem::create([
            'partner_id' => $jollibee->id,
            'menu_category_id' => $chickenjoyCategory->id,
            'name' => '2-pc Chickenjoy',
            'description' => 'Two pieces of crispy fried chicken',
            'price' => 165.00,
            'is_available' => true,
            'is_popular' => true,
            'sort_order' => 2,
        ]);

        $burgersCategory = MenuCategory::create([
            'partner_id' => $jollibee->id,
            'name' => 'Burgers',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        MenuItem::create([
            'partner_id' => $jollibee->id,
            'menu_category_id' => $burgersCategory->id,
            'name' => 'Yumburger',
            'description' => 'Classic beef burger with special sauce',
            'price' => 45.00,
            'is_available' => true,
            'is_popular' => true,
            'sort_order' => 1,
        ]);

        MenuItem::create([
            'partner_id' => $jollibee->id,
            'menu_category_id' => $burgersCategory->id,
            'name' => 'Champ',
            'description' => 'Quarter pound beef burger',
            'price' => 125.00,
            'is_available' => true,
            'sort_order' => 2,
        ]);

        echo "  ✓ Jollibee created with menu items\n";

        // McDonald's
        $mcdo = Partner::create([
            'name' => "McDonald's - Boracay",
            'slug' => 'mcdonalds-boracay',
            'description' => 'Station 1, Barangay Balabag, Boracay',
            'category' => 'restaurant',
            'tags' => ['fast food', 'burger', 'fries', 'chicken'],
            'cuisine_types' => ['American', 'Fast Food'],
            'rating' => 4.6,
            'total_reviews' => 980,
            'delivery_fee' => 49.00,
            'min_order_amount' => 150,
            'estimated_delivery_minutes' => 35,
            'is_open' => true,
            'is_featured' => false,
            'accepts_online_payment' => true,
            'status' => 'active',
        ]);

        $mealCategory = MenuCategory::create([
            'partner_id' => $mcdo->id,
            'name' => 'Meals',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        MenuItem::create([
            'partner_id' => $mcdo->id,
            'menu_category_id' => $mealCategory->id,
            'name' => 'Big Mac Meal',
            'description' => 'Big Mac with fries and drink',
            'price' => 185.00,
            'is_available' => true,
            'is_popular' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ]);

        MenuItem::create([
            'partner_id' => $mcdo->id,
            'menu_category_id' => $mealCategory->id,
            'name' => 'Quarter Pounder Meal',
            'description' => 'Quarter Pounder with cheese, fries and drink',
            'price' => 195.00,
            'is_available' => true,
            'is_popular' => true,
            'sort_order' => 2,
        ]);

        MenuItem::create([
            'partner_id' => $mcdo->id,
            'menu_category_id' => $mealCategory->id,
            'name' => '6-pc Chicken McNuggets Meal',
            'description' => 'Crispy chicken nuggets with fries and drink',
            'price' => 165.00,
            'is_available' => true,
            'sort_order' => 3,
        ]);

        echo "  ✓ McDonald's created with menu items\n";

        // Bella Italia Pizzeria
        $bella = Partner::create([
            'name' => 'Bella Italia Pizzeria',
            'slug' => 'bella-italia-pizzeria',
            'description' => 'Station 3, Barangay Balabag, Boracay',
            'category' => 'restaurant',
            'tags' => ['pizza', 'pasta', 'italian'],
            'cuisine_types' => ['Italian', 'Pizza'],
            'rating' => 4.8,
            'total_reviews' => 450,
            'delivery_fee' => 59.00,
            'min_order_amount' => 200,
            'estimated_delivery_minutes' => 40,
            'is_open' => true,
            'is_featured' => true,
            'accepts_online_payment' => true,
            'status' => 'active',
        ]);

        $pizzaCategory = MenuCategory::create([
            'partner_id' => $bella->id,
            'name' => 'Pizza',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        MenuItem::create([
            'partner_id' => $bella->id,
            'menu_category_id' => $pizzaCategory->id,
            'name' => 'Margherita Pizza',
            'description' => 'Classic tomato, mozzarella and basil',
            'price' => 350.00,
            'is_available' => true,
            'is_popular' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ]);

        MenuItem::create([
            'partner_id' => $bella->id,
            'menu_category_id' => $pizzaCategory->id,
            'name' => 'Pepperoni Pizza',
            'description' => 'Loaded with pepperoni and cheese',
            'price' => 395.00,
            'is_available' => true,
            'is_popular' => true,
            'sort_order' => 2,
        ]);

        $pastaCategory = MenuCategory::create([
            'partner_id' => $bella->id,
            'name' => 'Pasta',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        MenuItem::create([
            'partner_id' => $bella->id,
            'menu_category_id' => $pastaCategory->id,
            'name' => 'Spaghetti Carbonara',
            'description' => 'Creamy pasta with bacon',
            'price' => 285.00,
            'is_available' => true,
            'is_popular' => true,
            'sort_order' => 1,
        ]);

        echo "  ✓ Bella Italia created with menu items\n";
    }

    private function seedStores(): void
    {
        // 7-Eleven
        $sevenEleven = Partner::create([
            'name' => '7-Eleven - Boracay Station 2',
            'slug' => '7-eleven-boracay-station-2',
            'description' => 'Station 2, Main Road, Boracay',
            'category' => 'grocery',
            'tags' => ['convenience', 'snacks', 'drinks', 'essentials'],
            'cuisine_types' => [],
            'rating' => 4.5,
            'total_reviews' => 320,
            'delivery_fee' => 39.00,
            'min_order_amount' => 100,
            'estimated_delivery_minutes' => 20,
            'is_open' => true,
            'is_featured' => true,
            'accepts_online_payment' => true,
            'status' => 'active',
        ]);

        $snacksCategory = MenuCategory::create([
            'partner_id' => $sevenEleven->id,
            'name' => 'Snacks',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        MenuItem::create([
            'partner_id' => $sevenEleven->id,
            'menu_category_id' => $snacksCategory->id,
            'name' => 'Piattos Cheese',
            'description' => 'Potato crisps with cheese flavor',
            'price' => 35.00,
            'unit' => 'pack',
            'is_available' => true,
            'is_popular' => true,
            'sort_order' => 1,
        ]);

        MenuItem::create([
            'partner_id' => $sevenEleven->id,
            'menu_category_id' => $snacksCategory->id,
            'name' => 'Nova Multigrain',
            'description' => 'Crunchy multigrain snack',
            'price' => 28.00,
            'unit' => 'pack',
            'is_available' => true,
            'sort_order' => 2,
        ]);

        $drinksCategory = MenuCategory::create([
            'partner_id' => $sevenEleven->id,
            'name' => 'Drinks',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        MenuItem::create([
            'partner_id' => $sevenEleven->id,
            'menu_category_id' => $drinksCategory->id,
            'name' => 'Coca-Cola 1.5L',
            'description' => 'Refreshing cola drink',
            'price' => 65.00,
            'unit' => 'bottle',
            'is_available' => true,
            'is_popular' => true,
            'sort_order' => 1,
        ]);

        MenuItem::create([
            'partner_id' => $sevenEleven->id,
            'menu_category_id' => $drinksCategory->id,
            'name' => 'C2 Green Tea',
            'description' => 'Refreshing green tea drink',
            'price' => 25.00,
            'unit' => 'bottle',
            'is_available' => true,
            'is_popular' => true,
            'sort_order' => 2,
        ]);

        echo "  ✓ 7-Eleven created with products\n";

        // Mercury Drug
        $mercury = Partner::create([
            'name' => 'Mercury Drug - Boracay',
            'slug' => 'mercury-drug-boracay',
            'description' => 'D-Mall, Station 2, Boracay',
            'category' => 'pharmacy',
            'tags' => ['pharmacy', 'medicine', 'health', 'wellness'],
            'cuisine_types' => [],
            'rating' => 4.7,
            'total_reviews' => 580,
            'delivery_fee' => 49.00,
            'min_order_amount' => 150,
            'estimated_delivery_minutes' => 30,
            'is_open' => true,
            'is_featured' => false,
            'accepts_online_payment' => true,
            'status' => 'active',
        ]);

        $medicineCategory = MenuCategory::create([
            'partner_id' => $mercury->id,
            'name' => 'Medicine',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        MenuItem::create([
            'partner_id' => $mercury->id,
            'menu_category_id' => $medicineCategory->id,
            'name' => 'Biogesic 500mg',
            'description' => 'Paracetamol for fever and pain',
            'price' => 7.50,
            'unit' => 'tablet',
            'is_available' => true,
            'is_popular' => true,
            'requires_prescription' => false,
            'sort_order' => 1,
        ]);

        MenuItem::create([
            'partner_id' => $mercury->id,
            'menu_category_id' => $medicineCategory->id,
            'name' => 'Neozep Forte',
            'description' => 'For colds and flu symptoms',
            'price' => 8.50,
            'unit' => 'tablet',
            'is_available' => true,
            'is_popular' => true,
            'requires_prescription' => false,
            'sort_order' => 2,
        ]);

        $vitaminsCategory = MenuCategory::create([
            'partner_id' => $mercury->id,
            'name' => 'Vitamins',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        MenuItem::create([
            'partner_id' => $mercury->id,
            'menu_category_id' => $vitaminsCategory->id,
            'name' => 'Vitamin C 500mg',
            'description' => 'Immune system support',
            'price' => 5.00,
            'unit' => 'tablet',
            'is_available' => true,
            'is_popular' => true,
            'sort_order' => 1,
        ]);

        echo "  ✓ Mercury Drug created with products\n";

        // Alfamart
        $alfamart = Partner::create([
            'name' => 'Alfamart - Boracay',
            'slug' => 'alfamart-boracay',
            'description' => 'Station 3, Boracay Island',
            'category' => 'grocery',
            'tags' => ['grocery', 'convenience', 'food', 'household'],
            'cuisine_types' => [],
            'rating' => 4.4,
            'total_reviews' => 290,
            'delivery_fee' => 39.00,
            'min_order_amount' => 100,
            'estimated_delivery_minutes' => 25,
            'is_open' => true,
            'is_featured' => false,
            'accepts_online_payment' => true,
            'status' => 'active',
        ]);

        $groceryCategory = MenuCategory::create([
            'partner_id' => $alfamart->id,
            'name' => 'Groceries',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        MenuItem::create([
            'partner_id' => $alfamart->id,
            'menu_category_id' => $groceryCategory->id,
            'name' => 'Lucky Me Pancit Canton',
            'description' => 'Instant noodles',
            'price' => 15.00,
            'unit' => 'pack',
            'is_available' => true,
            'is_popular' => true,
            'sort_order' => 1,
        ]);

        MenuItem::create([
            'partner_id' => $alfamart->id,
            'menu_category_id' => $groceryCategory->id,
            'name' => 'Spam Classic 340g',
            'description' => 'Canned meat',
            'price' => 185.00,
            'unit' => 'can',
            'is_available' => true,
            'sort_order' => 2,
        ]);

        MenuItem::create([
            'partner_id' => $alfamart->id,
            'menu_category_id' => $groceryCategory->id,
            'name' => 'Alaska Evaporated Milk',
            'description' => 'Evaporated milk 370ml',
            'price' => 45.00,
            'unit' => 'can',
            'is_available' => true,
            'sort_order' => 3,
        ]);

        echo "  ✓ Alfamart created with products\n";
    }
}
