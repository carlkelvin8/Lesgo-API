<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\PartnerBranch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * Seeds LesEat restaurants and LesBuy stores located in the
 * CAGAYAN VALLEY region (Region II) — primarily Tuguegarao City, Cagayan
 * and Claveria, Cagayan. This is NOT Cagayan de Oro (Misamis Oriental).
 *
 * Coordinates:
 *   - Tuguegarao City, Cagayan : 17.6132, 121.7270 (postal 3500)
 *   - Claveria, Cagayan        : 18.6058, 121.0778 (postal 3519)
 */
class CagayanValleySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌄 Seeding Cagayan Valley (Region II) partners...');

        foreach ($this->partners() as $data) {
            $menuData   = $data['menu'];
            $branchData = $data['branch'];
            unset($data['menu'], $data['branch']);

            // Create or update the partner (idempotent by slug)
            $partner = Partner::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            // Create or update the primary branch with Cagayan Valley coordinates
            $branchData['partner_id'] = $partner->id;
            PartnerBranch::updateOrCreate(
                ['partner_id' => $partner->id, 'name' => $branchData['name']],
                $branchData
            );

            // Reset menu for a clean re-seed
            MenuCategory::where('partner_id', $partner->id)->delete();

            foreach ($menuData as $sortOrder => $categoryData) {
                $category = MenuCategory::create([
                    'partner_id' => $partner->id,
                    'name'       => $categoryData['name'],
                    'is_active'  => true,
                    'is_popular' => $categoryData['popular'] ?? false,
                    'sort_order' => $sortOrder,
                ]);

                foreach ($categoryData['items'] as $itemSort => $item) {
                    MenuItem::create([
                        'partner_id'       => $partner->id,
                        'menu_category_id' => $category->id,
                        'name'             => $item['name'],
                        'description'      => $item['description'] ?? null,
                        'price'            => $item['price'],
                        'unit'             => $item['unit'] ?? null,
                        'is_available'     => true,
                        'is_popular'       => $item['popular'] ?? false,
                        'sort_order'       => $itemSort,
                    ]);
                }
            }

            $this->command->info("  ✓ {$partner->name} — {$branchData['city']} (" . count($menuData) . ' categories)');
        }

        $this->command->info('✅ Cagayan Valley seeding completed!');
    }

    private function partners(): array
    {
        // Reusable branch coordinates for Cagayan Valley
        $tuguegarao = [
            'city'        => 'Tuguegarao City',
            'region'      => 'Cagayan Valley',
            'country'     => 'Philippines',
            'postal_code' => '3500',
            'latitude'    => 17.6132000,
            'longitude'   => 121.7270000,
        ];

        $claveria = [
            'city'        => 'Claveria',
            'region'      => 'Cagayan Valley',
            'country'     => 'Philippines',
            'postal_code' => '3519',
            'latitude'    => 18.6058100,
            'longitude'   => 121.0778880,
        ];

        return [
            // ── Jollibee - Tuguegarao ──────────────────────────────────────
            [
                'name'                       => 'Jollibee - Tuguegarao',
                'description'                => 'Fast Food • Chicken • Burgers',
                'category'                   => 'restaurant',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => true,
                'rating'                     => 4.7,
                'total_reviews'              => 1120,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 150,
                'estimated_delivery_minutes' => 25,
                'accepts_online_payment'     => true,
                'slug'                       => 'jollibee-tuguegarao',
                'cuisine_types'              => ['Filipino', 'Fast Food'],
                'tags'                       => ['fast food', 'chicken', 'burger'],
                'branch' => array_merge($tuguegarao, [
                    'name'          => 'Jollibee Tuguegarao (Bonifacio St.)',
                    'phone_number'  => '+63788440001',
                    'address_line1' => 'Bonifacio St., Centro 10',
                    'is_primary'    => true,
                ]),
                'menu' => [
                    [
                        'name' => 'Chickenjoy', 'popular' => true,
                        'items' => [
                            ['name' => '1-pc Chickenjoy', 'price' => 89, 'description' => 'Crispy on the outside, juicy on the inside.', 'popular' => true],
                            ['name' => '2-pc Chickenjoy', 'price' => 169, 'description' => 'Two pieces of our signature fried chicken.', 'popular' => true],
                            ['name' => 'Chickenjoy w/ Jolly Spaghetti', 'price' => 149, 'description' => 'Chickenjoy + sweet-style spaghetti combo.'],
                        ],
                    ],
                    [
                        'name' => 'Burgers', 'popular' => false,
                        'items' => [
                            ['name' => 'Yumburger', 'price' => 49, 'description' => 'Classic Jollibee burger with special sauce.', 'popular' => true],
                            ['name' => 'Cheeseburger', 'price' => 59, 'description' => 'Yumburger topped with melted cheese.'],
                            ['name' => 'Champ', 'price' => 125, 'description' => 'Quarter-pound beef burger.'],
                        ],
                    ],
                    [
                        'name' => 'Drinks', 'popular' => false,
                        'items' => [
                            ['name' => 'Coke Regular', 'price' => 39, 'description' => 'Ice-cold Coca-Cola.'],
                            ['name' => 'Pineapple Juice', 'price' => 45, 'description' => 'Refreshing pineapple juice.'],
                        ],
                    ],
                ],
            ],

            // ── Mang Inasal - Tuguegarao ───────────────────────────────────
            [
                'name'                       => 'Mang Inasal - Tuguegarao',
                'description'                => 'Filipino BBQ • Chicken Inasal • Rice Meals',
                'category'                   => 'restaurant',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => true,
                'rating'                     => 4.5,
                'total_reviews'              => 742,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 150,
                'estimated_delivery_minutes' => 30,
                'accepts_online_payment'     => true,
                'slug'                       => 'mang-inasal-tuguegarao',
                'cuisine_types'              => ['Filipino', 'BBQ'],
                'tags'                       => ['inasal', 'chicken', 'rice meals'],
                'branch' => array_merge($tuguegarao, [
                    'name'          => 'Mang Inasal Tuguegarao (Balzain Rd.)',
                    'phone_number'  => '+63788440002',
                    'address_line1' => 'Balzain Road, Ugac Sur',
                    'is_primary'    => true,
                ]),
                'menu' => [
                    [
                        'name' => 'Chicken Inasal', 'popular' => true,
                        'items' => [
                            ['name' => 'Chicken Paa (Leg)', 'price' => 119, 'description' => 'Grilled chicken leg marinated in special spices.', 'popular' => true],
                            ['name' => 'Chicken Pecho (Breast)', 'price' => 129, 'description' => 'Grilled chicken breast, juicy and flavorful.', 'popular' => true],
                            ['name' => 'Chicken Inasal Combo', 'price' => 159, 'description' => 'Chicken inasal with unlimited rice.', 'popular' => true],
                        ],
                    ],
                    [
                        'name' => 'Soups & Sides', 'popular' => false,
                        'items' => [
                            ['name' => 'Bulalo', 'price' => 189, 'description' => 'Beef bone marrow soup.'],
                            ['name' => 'Kanin (Rice)', 'price' => 29, 'description' => 'Steamed white rice.'],
                        ],
                    ],
                    [
                        'name' => 'Drinks', 'popular' => false,
                        'items' => [
                            ['name' => 'Iced Tea', 'price' => 35, 'description' => 'Refreshing iced tea.'],
                            ['name' => 'Buko Juice', 'price' => 45, 'description' => 'Fresh coconut juice.'],
                        ],
                    ],
                ],
            ],

            // ── Pancit Batil Patung Haus (Tuguegarao specialty) ────────────
            [
                'name'                       => 'Pancit Batil Patung Haus',
                'description'                => 'Tuguegarao Specialty • Noodles • Local Favorites',
                'category'                   => 'restaurant',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => true,
                'rating'                     => 4.8,
                'total_reviews'              => 526,
                'delivery_fee'               => 39.00,
                'min_order_amount'           => 120,
                'estimated_delivery_minutes' => 25,
                'accepts_online_payment'     => true,
                'slug'                       => 'pancit-batil-patung-haus',
                'cuisine_types'              => ['Filipino', 'Ilocano'],
                'tags'                       => ['pancit', 'batil patung', 'local', 'tuguegarao'],
                'branch' => array_merge($tuguegarao, [
                    'name'          => 'Batil Patung Haus (Caritan)',
                    'phone_number'  => '+63788440003',
                    'address_line1' => 'Caritan Centro, Maharlika Highway',
                    'is_primary'    => true,
                ]),
                'menu' => [
                    [
                        'name' => 'Pancit Batil Patung', 'popular' => true,
                        'items' => [
                            ['name' => 'Batil Patung Regular', 'price' => 95, 'description' => 'Tuguegarao\'s famous stir-fried miki noodles topped with poached egg, carabao meat, and egg-drop soup.', 'popular' => true],
                            ['name' => 'Batil Patung Special', 'price' => 130, 'description' => 'Extra toppings of meat, chicharon, and egg.', 'popular' => true],
                            ['name' => 'Batil Patung Bilao (Sharing)', 'price' => 320, 'description' => 'Good for 3-4 persons.'],
                        ],
                    ],
                    [
                        'name' => 'Silog Meals', 'popular' => false,
                        'items' => [
                            ['name' => 'Tapsilog', 'price' => 99, 'description' => 'Beef tapa, garlic rice, and egg.', 'popular' => true],
                            ['name' => 'Longsilog', 'price' => 89, 'description' => 'Longganisa, garlic rice, and egg.'],
                        ],
                    ],
                    [
                        'name' => 'Drinks', 'popular' => false,
                        'items' => [
                            ['name' => 'Softdrinks', 'price' => 30, 'description' => 'Assorted sodas.'],
                            ['name' => 'Bottled Water', 'price' => 20, 'description' => 'Purified drinking water.'],
                        ],
                    ],
                ],
            ],

            // ── Chowking - Tuguegarao ──────────────────────────────────────
            [
                'name'                       => 'Chowking - Tuguegarao',
                'description'                => 'Chinese Fast Food • Noodles • Dimsum',
                'category'                   => 'restaurant',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => false,
                'rating'                     => 4.3,
                'total_reviews'              => 488,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 150,
                'estimated_delivery_minutes' => 30,
                'accepts_online_payment'     => true,
                'slug'                       => 'chowking-tuguegarao',
                'cuisine_types'              => ['Chinese', 'Fast Food'],
                'tags'                       => ['chinese', 'noodles', 'dimsum'],
                'branch' => array_merge($tuguegarao, [
                    'name'          => 'Chowking Tuguegarao (Luna St.)',
                    'phone_number'  => '+63788440004',
                    'address_line1' => 'Luna St., Centro 11',
                    'is_primary'    => true,
                ]),
                'menu' => [
                    [
                        'name' => 'Noodles & Rice', 'popular' => true,
                        'items' => [
                            ['name' => 'Chao Fan', 'price' => 89, 'description' => 'Chinese-style fried rice.', 'popular' => true],
                            ['name' => 'Beef Wonton Noodle Soup', 'price' => 109, 'description' => 'Noodle soup with beef and wontons.', 'popular' => true],
                        ],
                    ],
                    [
                        'name' => 'Dimsum', 'popular' => false,
                        'items' => [
                            ['name' => 'Siomai (4 pcs)', 'price' => 69, 'description' => 'Steamed pork dumplings.', 'popular' => true],
                            ['name' => 'Siopao Asado', 'price' => 55, 'description' => 'Steamed bun with pork asado filling.'],
                            ['name' => 'Halo-Halo', 'price' => 89, 'description' => 'Filipino shaved ice dessert.'],
                        ],
                    ],
                ],
            ],

            // ── LesBuy Store: Cagayan Valley Grocery (Claveria) ────────────
            [
                'name'                       => 'CV Mart - Claveria',
                'description'                => 'Grocery • Convenience • Household Essentials',
                'category'                   => 'grocery',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => false,
                'rating'                     => 4.4,
                'total_reviews'              => 214,
                'delivery_fee'               => 39.00,
                'min_order_amount'           => 100,
                'estimated_delivery_minutes' => 25,
                'accepts_online_payment'     => true,
                'slug'                       => 'cv-mart-claveria',
                'cuisine_types'              => [],
                'tags'                       => ['grocery', 'convenience', 'household'],
                'branch' => array_merge($claveria, [
                    'name'          => 'CV Mart Claveria (Poblacion)',
                    'phone_number'  => '+63788550001',
                    'address_line1' => 'Poblacion, Claveria',
                    'is_primary'    => true,
                ]),
                'menu' => [
                    [
                        'name' => 'Groceries', 'popular' => true,
                        'items' => [
                            ['name' => 'Lucky Me Pancit Canton', 'price' => 15, 'unit' => 'pack', 'description' => 'Instant noodles.', 'popular' => true],
                            ['name' => 'Spam Classic 340g', 'price' => 185, 'unit' => 'can', 'description' => 'Canned meat.'],
                            ['name' => 'Alaska Evaporated Milk', 'price' => 45, 'unit' => 'can', 'description' => 'Evaporated milk 370ml.'],
                        ],
                    ],
                    [
                        'name' => 'Drinks', 'popular' => false,
                        'items' => [
                            ['name' => 'Coca-Cola 1.5L', 'price' => 65, 'unit' => 'bottle', 'description' => 'Refreshing cola drink.', 'popular' => true],
                            ['name' => 'C2 Green Tea', 'price' => 25, 'unit' => 'bottle', 'description' => 'Refreshing green tea drink.'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
