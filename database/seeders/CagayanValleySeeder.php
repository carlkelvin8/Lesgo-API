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

            // ── LesBuy: 7-Eleven - Tuguegarao (convenience) ────────────────
            [
                'name'                       => '7-Eleven - Tuguegarao',
                'description'                => 'Convenience Store • Snacks • Drinks • Ready to Eat',
                'category'                   => 'grocery',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => true,
                'rating'                     => 4.5,
                'total_reviews'              => 336,
                'delivery_fee'               => 39.00,
                'min_order_amount'           => 100,
                'estimated_delivery_minutes' => 20,
                'accepts_online_payment'     => true,
                'slug'                       => '7-eleven-tuguegarao',
                'cuisine_types'              => [],
                'tags'                       => ['convenience', 'snacks', 'drinks', 'essentials'],
                'branch' => array_merge($tuguegarao, [
                    'name'          => '7-Eleven Tuguegarao (Rizal St.)',
                    'phone_number'  => '+63788550002',
                    'address_line1' => 'Rizal St., Centro 8',
                    'is_primary'    => true,
                ]),
                'menu' => [
                    [
                        'name' => 'Snacks', 'popular' => true,
                        'items' => [
                            ['name' => 'Piattos Cheese', 'price' => 35, 'unit' => 'pack', 'description' => 'Potato crisps with cheese flavor.', 'popular' => true],
                            ['name' => 'Nova Multigrain', 'price' => 28, 'unit' => 'pack', 'description' => 'Crunchy multigrain snack.'],
                            ['name' => 'Oishi Prawn Crackers', 'price' => 15, 'unit' => 'pack', 'description' => 'Classic prawn cracker snack.'],
                        ],
                    ],
                    [
                        'name' => 'Drinks', 'popular' => true,
                        'items' => [
                            ['name' => 'Coca-Cola 1.5L', 'price' => 65, 'unit' => 'bottle', 'description' => 'Refreshing cola drink.', 'popular' => true],
                            ['name' => 'Gatorade Blue Bolt 500ml', 'price' => 45, 'unit' => 'bottle', 'description' => 'Sports hydration drink.'],
                            ['name' => 'Bottled Water 500ml', 'price' => 20, 'unit' => 'bottle', 'description' => 'Purified drinking water.'],
                        ],
                    ],
                    [
                        'name' => 'Ready to Eat', 'popular' => false,
                        'items' => [
                            ['name' => 'Hotdog Sandwich', 'price' => 45, 'description' => '7-Eleven classic hotdog.', 'popular' => true],
                            ['name' => 'Cup Noodles Chicken', 'price' => 55, 'description' => 'Instant noodles, hot serving.'],
                        ],
                    ],
                ],
            ],

            // ── LesBuy: Mercury Drug - Tuguegarao (pharmacy) ───────────────
            [
                'name'                       => 'Mercury Drug - Tuguegarao',
                'description'                => 'Pharmacy • Medicine • Health Products',
                'category'                   => 'pharmacy',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => true,
                'rating'                     => 4.6,
                'total_reviews'              => 398,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 100,
                'estimated_delivery_minutes' => 30,
                'accepts_online_payment'     => true,
                'slug'                       => 'mercury-drug-tuguegarao',
                'cuisine_types'              => [],
                'tags'                       => ['pharmacy', 'medicine', 'health', 'wellness'],
                'branch' => array_merge($tuguegarao, [
                    'name'          => 'Mercury Drug Tuguegarao (Bonifacio St.)',
                    'phone_number'  => '+63788550003',
                    'address_line1' => 'Bonifacio St., Centro 10',
                    'is_primary'    => true,
                ]),
                'menu' => [
                    [
                        'name' => 'Medicine', 'popular' => true,
                        'items' => [
                            ['name' => 'Biogesic 500mg (10 tabs)', 'price' => 35, 'description' => 'Paracetamol for fever and pain relief.', 'popular' => true],
                            ['name' => 'Neozep Forte (10 tabs)', 'price' => 45, 'description' => 'For colds and flu symptoms.', 'popular' => true],
                            ['name' => 'Mefenamic Acid 500mg (10 tabs)', 'price' => 55, 'description' => 'For pain and inflammation.'],
                        ],
                    ],
                    [
                        'name' => 'Vitamins & Supplements', 'popular' => false,
                        'items' => [
                            ['name' => 'Vitamin C 500mg (100 tabs)', 'price' => 89, 'description' => 'Immune system support.', 'popular' => true],
                            ['name' => 'Centrum Adults (30 tabs)', 'price' => 299, 'description' => 'Complete multivitamin supplement.'],
                        ],
                    ],
                    [
                        'name' => 'Personal Care', 'popular' => false,
                        'items' => [
                            ['name' => 'Betadine Antiseptic 60ml', 'price' => 89, 'description' => 'Antiseptic solution for wounds.'],
                            ['name' => 'Surgical Face Mask (50 pcs)', 'price' => 149, 'description' => '3-ply disposable face masks.'],
                        ],
                    ],
                ],
            ],

            // ── LesBuy: Puregold - Tuguegarao (supermarket) ────────────────
            [
                'name'                       => 'Puregold - Tuguegarao',
                'description'                => 'Supermarket • Fresh Goods • Groceries',
                'category'                   => 'grocery',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => false,
                'rating'                     => 4.3,
                'total_reviews'              => 421,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 150,
                'estimated_delivery_minutes' => 35,
                'accepts_online_payment'     => true,
                'slug'                       => 'puregold-tuguegarao',
                'cuisine_types'              => [],
                'tags'                       => ['supermarket', 'groceries', 'fresh', 'household'],
                'branch' => array_merge($tuguegarao, [
                    'name'          => 'Puregold Tuguegarao (Maharlika Highway)',
                    'phone_number'  => '+63788550004',
                    'address_line1' => 'Maharlika Highway, Carig Sur',
                    'is_primary'    => true,
                ]),
                'menu' => [
                    [
                        'name' => 'Rice & Grains', 'popular' => true,
                        'items' => [
                            ['name' => 'Sinandomeng Rice 5kg', 'price' => 285, 'description' => 'Premium white rice.', 'popular' => true],
                            ['name' => 'Jasmine Rice 5kg', 'price' => 325, 'description' => 'Fragrant jasmine rice.'],
                        ],
                    ],
                    [
                        'name' => 'Fresh & Frozen', 'popular' => false,
                        'items' => [
                            ['name' => 'Fresh Eggs (1 tray)', 'price' => 210, 'description' => '30 pieces medium eggs.', 'popular' => true],
                            ['name' => 'Chicken Leg Quarter 1kg', 'price' => 165, 'description' => 'Fresh chicken cut.'],
                            ['name' => 'Hotdog Tender Juicy 1kg', 'price' => 145, 'description' => 'Frozen meat hotdog.'],
                        ],
                    ],
                    [
                        'name' => 'Pantry', 'popular' => false,
                        'items' => [
                            ['name' => 'Silver Swan Soy Sauce 1L', 'price' => 55, 'description' => 'All-purpose soy sauce.'],
                            ['name' => 'UFC Banana Catsup 320g', 'price' => 42, 'description' => 'Sweet banana catsup.'],
                        ],
                    ],
                ],
            ],

            // ── LesBuy: National Book Store - Tuguegarao (school supplies) ─
            [
                'name'                       => 'National Book Store - Tuguegarao',
                'description'                => 'School Supplies • Books • Office Supplies',
                'category'                   => 'school_supplies',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => false,
                'rating'                     => 4.4,
                'total_reviews'              => 202,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 100,
                'estimated_delivery_minutes' => 35,
                'accepts_online_payment'     => true,
                'slug'                       => 'national-book-store-tuguegarao',
                'cuisine_types'              => [],
                'tags'                       => ['school', 'books', 'office', 'supplies'],
                'branch' => array_merge($tuguegarao, [
                    'name'          => 'National Book Store Tuguegarao (Gonzaga St.)',
                    'phone_number'  => '+63788550005',
                    'address_line1' => 'Gonzaga St., Centro 4',
                    'is_primary'    => true,
                ]),
                'menu' => [
                    [
                        'name' => 'School Supplies', 'popular' => true,
                        'items' => [
                            ['name' => 'Ballpen Black (12 pcs)', 'price' => 49, 'unit' => 'box', 'description' => 'Smooth-writing ballpoint pens.', 'popular' => true],
                            ['name' => 'Mongol Pencil #2 (12 pcs)', 'price' => 55, 'unit' => 'box', 'description' => 'Classic yellow pencils.', 'popular' => true],
                            ['name' => 'Intermediate Pad (50 leaves)', 'price' => 35, 'unit' => 'pad', 'description' => 'Ruled intermediate pad paper.'],
                        ],
                    ],
                    [
                        'name' => 'Art Supplies', 'popular' => false,
                        'items' => [
                            ['name' => 'Crayola Crayons 24 colors', 'price' => 89, 'unit' => 'box', 'description' => 'Vibrant wax crayons.'],
                            ['name' => 'Watercolor Set 12 colors', 'price' => 65, 'unit' => 'set', 'description' => 'Basic watercolor paint set.'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
