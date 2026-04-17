<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        $restaurants = [
            [
                'name'                       => 'Jollibee - Claveria',
                'description'                => 'Fast Food • Chicken • Burgers',
                'category'                   => 'restaurant',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => true,
                'rating'                     => 4.7,
                'total_reviews'              => 1024,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 150,
                'estimated_delivery_minutes' => 25,
                'accepts_online_payment'     => true,
                'slug'                       => 'jollibee-claveria',
                'menu' => [
                    [
                        'name'     => 'Chickenjoy',
                        'popular'  => true,
                        'items'    => [
                            ['name' => '1-pc Chickenjoy',        'price' => 89,  'description' => 'Crispy on the outside, juicy on the inside.', 'popular' => true],
                            ['name' => '2-pc Chickenjoy',        'price' => 169, 'description' => 'Two pieces of our signature fried chicken.', 'popular' => true],
                            ['name' => 'Chickenjoy w/ Jolly Spaghetti', 'price' => 149, 'description' => 'Chickenjoy + sweet-style spaghetti combo.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'     => 'Burgers',
                        'popular'  => false,
                        'items'    => [
                            ['name' => 'Yumburger',              'price' => 49,  'description' => 'Classic Jollibee burger with special sauce.', 'popular' => true],
                            ['name' => 'Cheeseburger',           'price' => 59,  'description' => 'Yumburger topped with melted cheese.', 'popular' => false],
                            ['name' => 'Double Yumburger',       'price' => 89,  'description' => 'Double the beef, double the flavor.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'     => 'Fries & Sides',
                        'popular'  => false,
                        'items'    => [
                            ['name' => 'Regular Fries',          'price' => 39,  'description' => 'Golden crispy fries.', 'popular' => false],
                            ['name' => 'Large Fries',            'price' => 59,  'description' => 'More fries for more fun.', 'popular' => false],
                            ['name' => 'Jolly Spaghetti',        'price' => 69,  'description' => 'Sweet-style spaghetti with hotdog and cheese.', 'popular' => true],
                        ],
                    ],
                    [
                        'name'     => 'Drinks',
                        'popular'  => false,
                        'items'    => [
                            ['name' => 'Coke Regular',           'price' => 39,  'description' => 'Ice-cold Coca-Cola.', 'popular' => false],
                            ['name' => 'Coke Large',             'price' => 55,  'description' => 'Large ice-cold Coca-Cola.', 'popular' => false],
                            ['name' => 'Pineapple Juice',        'price' => 45,  'description' => 'Refreshing pineapple juice.', 'popular' => false],
                        ],
                    ],
                ],
            ],
            [
                'name'                       => 'Mang Inasal - Claveria',
                'description'                => 'Filipino BBQ • Chicken Inasal • Rice Meals',
                'category'                   => 'restaurant',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => true,
                'rating'                     => 4.5,
                'total_reviews'              => 856,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 150,
                'estimated_delivery_minutes' => 30,
                'accepts_online_payment'     => true,
                'slug'                       => 'mang-inasal-claveria',
                'menu' => [
                    [
                        'name'     => 'Chicken Inasal',
                        'popular'  => true,
                        'items'    => [
                            ['name' => 'Chicken Paa (Leg)',      'price' => 119, 'description' => 'Grilled chicken leg marinated in special spices.', 'popular' => true],
                            ['name' => 'Chicken Pecho (Breast)', 'price' => 129, 'description' => 'Grilled chicken breast, juicy and flavorful.', 'popular' => true],
                            ['name' => 'Chicken Inasal Combo',   'price' => 159, 'description' => 'Chicken inasal with unlimited rice.', 'popular' => true],
                        ],
                    ],
                    [
                        'name'     => 'Pork BBQ',
                        'popular'  => false,
                        'items'    => [
                            ['name' => 'Pork BBQ',               'price' => 99,  'description' => 'Grilled pork skewers with special marinade.', 'popular' => false],
                            ['name' => 'Pork BBQ Combo',         'price' => 139, 'description' => 'Pork BBQ with rice and soup.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'     => 'Soups & Sides',
                        'popular'  => false,
                        'items'    => [
                            ['name' => 'Bulalo',                 'price' => 189, 'description' => 'Beef bone marrow soup.', 'popular' => false],
                            ['name' => 'Sinigang na Baboy',      'price' => 169, 'description' => 'Sour tamarind pork soup.', 'popular' => false],
                            ['name' => 'Kanin (Rice)',            'price' => 29,  'description' => 'Steamed white rice.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'     => 'Drinks',
                        'popular'  => false,
                        'items'    => [
                            ['name' => 'Coke',                   'price' => 39,  'description' => 'Ice-cold Coca-Cola.', 'popular' => false],
                            ['name' => 'Iced Tea',               'price' => 35,  'description' => 'Refreshing iced tea.', 'popular' => false],
                            ['name' => 'Buko Juice',             'price' => 45,  'description' => 'Fresh coconut juice.', 'popular' => false],
                        ],
                    ],
                ],
            ],
            [
                'name'                       => 'Chowking - Claveria',
                'description'                => 'Chinese Fast Food • Noodles • Dimsum',
                'category'                   => 'restaurant',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => false,
                'rating'                     => 4.3,
                'total_reviews'              => 612,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 150,
                'estimated_delivery_minutes' => 30,
                'accepts_online_payment'     => true,
                'slug'                       => 'chowking-claveria',
                'menu' => [
                    [
                        'name'     => 'Noodles',
                        'popular'  => true,
                        'items'    => [
                            ['name' => 'Chao Fan',               'price' => 89,  'description' => 'Chinese-style fried rice.', 'popular' => true],
                            ['name' => 'Beef Wonton Noodle Soup','price' => 109, 'description' => 'Noodle soup with beef and wontons.', 'popular' => true],
                            ['name' => 'Pork Asado Noodle Soup', 'price' => 99,  'description' => 'Noodle soup with braised pork.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'     => 'Dimsum',
                        'popular'  => false,
                        'items'    => [
                            ['name' => 'Siomai (4 pcs)',         'price' => 69,  'description' => 'Steamed pork dumplings.', 'popular' => true],
                            ['name' => 'Siopao Asado',           'price' => 55,  'description' => 'Steamed bun with pork asado filling.', 'popular' => false],
                            ['name' => 'Halo-Halo',              'price' => 89,  'description' => 'Filipino shaved ice dessert.', 'popular' => false],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($restaurants as $restaurantData) {
            $menuData = $restaurantData['menu'];
            unset($restaurantData['menu']);

            // Create or update partner
            $partner = Partner::updateOrCreate(
                ['slug' => $restaurantData['slug']],
                $restaurantData
            );

            // Clear existing menu
            MenuCategory::where('partner_id', $partner->id)->delete();

            // Create menu categories and items
            foreach ($menuData as $sortOrder => $categoryData) {
                $category = MenuCategory::create([
                    'partner_id'  => $partner->id,
                    'name'        => $categoryData['name'],
                    'is_active'   => true,
                    'is_popular'  => $categoryData['popular'],
                    'sort_order'  => $sortOrder,
                ]);

                foreach ($categoryData['items'] as $itemSort => $itemData) {
                    MenuItem::create([
                        'partner_id'       => $partner->id,
                        'menu_category_id' => $category->id,
                        'name'             => $itemData['name'],
                        'description'      => $itemData['description'],
                        'price'            => $itemData['price'],
                        'is_available'     => true,
                        'is_popular'       => $itemData['popular'],
                        'sort_order'       => $itemSort,
                    ]);
                }
            }

            $this->command->info("Seeded: {$partner->name} with " . count($menuData) . " categories");
        }
    }
}
