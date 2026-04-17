<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class LesbuySeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            [
                'name'                       => 'Mercury Drug - Claveria',
                'description'                => 'Pharmacy • Medicine • Health Products',
                'category'                   => 'pharmacy',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => true,
                'rating'                     => 4.6,
                'total_reviews'              => 342,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 100,
                'estimated_delivery_minutes' => 30,
                'accepts_online_payment'     => true,
                'slug'                       => 'mercury-drug-claveria',
                'menu' => [
                    [
                        'name'    => 'Medicine',
                        'popular' => true,
                        'items'   => [
                            ['name' => 'Biogesic 500mg (10 tabs)',    'price' => 35,  'description' => 'Paracetamol for fever and pain relief.', 'popular' => true],
                            ['name' => 'Neozep Forte (10 tabs)',       'price' => 45,  'description' => 'For colds and flu symptoms.', 'popular' => true],
                            ['name' => 'Mefenamic Acid 500mg (10 tabs)','price' => 55, 'description' => 'For pain and inflammation.', 'popular' => false],
                            ['name' => 'Amoxicillin 500mg (10 caps)',  'price' => 89,  'description' => 'Antibiotic for bacterial infections.', 'popular' => false],
                            ['name' => 'Losartan 50mg (30 tabs)',      'price' => 120, 'description' => 'For high blood pressure.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'    => 'Vitamins & Supplements',
                        'popular' => false,
                        'items'   => [
                            ['name' => 'Vitamin C 500mg (100 tabs)',   'price' => 89,  'description' => 'Immune system support.', 'popular' => true],
                            ['name' => 'Myra E 400 IU (30 caps)',      'price' => 149, 'description' => 'Vitamin E for skin health.', 'popular' => true],
                            ['name' => 'Centrum Adults (30 tabs)',     'price' => 299, 'description' => 'Complete multivitamin supplement.', 'popular' => false],
                            ['name' => 'Zinc 20mg (30 tabs)',          'price' => 79,  'description' => 'Immune and metabolic support.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'    => 'Personal Care',
                        'popular' => false,
                        'items'   => [
                            ['name' => 'Betadine Antiseptic 60ml',    'price' => 89,  'description' => 'Antiseptic solution for wounds.', 'popular' => false],
                            ['name' => 'Efficascent Oil 50ml',        'price' => 65,  'description' => 'Medicated oil for muscle pain.', 'popular' => false],
                            ['name' => 'Katinko Ointment 30g',        'price' => 45,  'description' => 'Topical analgesic ointment.', 'popular' => false],
                            ['name' => 'Surgical Face Mask (50 pcs)', 'price' => 149, 'description' => '3-ply disposable face masks.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'    => 'Baby & Child',
                        'popular' => false,
                        'items'   => [
                            ['name' => 'Tempra Syrup 60ml',           'price' => 89,  'description' => 'Paracetamol syrup for children.', 'popular' => false],
                            ['name' => 'Ceelin Plus 60ml',            'price' => 119, 'description' => 'Vitamin C + Zinc syrup for kids.', 'popular' => false],
                            ['name' => 'Pampers Newborn (12 pcs)',    'price' => 189, 'description' => 'Soft and absorbent baby diapers.', 'popular' => false],
                        ],
                    ],
                ],
            ],
            [
                'name'                       => 'National Book Store - Claveria',
                'description'                => 'School Supplies • Books • Office Supplies',
                'category'                   => 'school_supplies',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => true,
                'rating'                     => 4.4,
                'total_reviews'              => 218,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 100,
                'estimated_delivery_minutes' => 35,
                'accepts_online_payment'     => true,
                'slug'                       => 'national-book-store-claveria',
                'menu' => [
                    [
                        'name'    => 'School Supplies',
                        'popular' => true,
                        'items'   => [
                            ['name' => 'Ballpen Black (12 pcs)',       'price' => 49,  'description' => 'Smooth-writing ballpoint pens.', 'popular' => true],
                            ['name' => 'Mongol Pencil #2 (12 pcs)',    'price' => 55,  'description' => 'Classic yellow pencils.', 'popular' => true],
                            ['name' => 'Intermediate Pad (50 leaves)', 'price' => 35,  'description' => 'Ruled intermediate pad paper.', 'popular' => true],
                            ['name' => 'Folder Long (10 pcs)',         'price' => 45,  'description' => 'Colored long folders.', 'popular' => false],
                            ['name' => 'Scotch Tape 1 inch',           'price' => 29,  'description' => 'Clear adhesive tape.', 'popular' => false],
                            ['name' => 'Scissors (1 pc)',              'price' => 39,  'description' => 'All-purpose scissors.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'    => 'Art Supplies',
                        'popular' => false,
                        'items'   => [
                            ['name' => 'Crayola Crayons 24 colors',   'price' => 89,  'description' => 'Vibrant wax crayons.', 'popular' => false],
                            ['name' => 'Watercolor Set 12 colors',    'price' => 65,  'description' => 'Basic watercolor paint set.', 'popular' => false],
                            ['name' => 'Sketch Pad A4 (20 sheets)',   'price' => 55,  'description' => 'Thick paper for sketching.', 'popular' => false],
                            ['name' => 'Glue Stick (3 pcs)',          'price' => 45,  'description' => 'Non-toxic glue sticks.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'    => 'Books',
                        'popular' => false,
                        'items'   => [
                            ['name' => 'Composition Notebook',        'price' => 45,  'description' => '80-leaf composition notebook.', 'popular' => false],
                            ['name' => 'Diary / Journal',             'price' => 89,  'description' => 'Lined personal journal.', 'popular' => false],
                            ['name' => 'Coloring Book (Kids)',        'price' => 55,  'description' => 'Fun coloring book for children.', 'popular' => false],
                        ],
                    ],
                ],
            ],
            [
                'name'                       => 'Ace Hardware - Claveria',
                'description'                => 'Hardware • Tools • Home Improvement',
                'category'                   => 'hardware',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => false,
                'rating'                     => 4.3,
                'total_reviews'              => 156,
                'delivery_fee'               => 59.00,
                'min_order_amount'           => 200,
                'estimated_delivery_minutes' => 40,
                'accepts_online_payment'     => true,
                'slug'                       => 'ace-hardware-claveria',
                'menu' => [
                    [
                        'name'    => 'Tools',
                        'popular' => true,
                        'items'   => [
                            ['name' => 'Hammer (16 oz)',               'price' => 189, 'description' => 'Steel claw hammer.', 'popular' => true],
                            ['name' => 'Screwdriver Set (6 pcs)',      'price' => 149, 'description' => 'Phillips and flathead screwdrivers.', 'popular' => true],
                            ['name' => 'Measuring Tape 5m',           'price' => 89,  'description' => 'Retractable steel measuring tape.', 'popular' => false],
                            ['name' => 'Pliers (1 pc)',                'price' => 129, 'description' => 'Combination pliers.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'    => 'Electrical',
                        'popular' => false,
                        'items'   => [
                            ['name' => 'Extension Cord 3m',           'price' => 149, 'description' => '3-outlet extension cord.', 'popular' => false],
                            ['name' => 'LED Bulb 9W',                 'price' => 89,  'description' => 'Energy-saving LED bulb.', 'popular' => false],
                            ['name' => 'Electrical Tape (3 pcs)',     'price' => 45,  'description' => 'Insulating electrical tape.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'    => 'Plumbing',
                        'popular' => false,
                        'items'   => [
                            ['name' => 'PVC Pipe 1/2 inch (1m)',      'price' => 49,  'description' => 'Standard PVC water pipe.', 'popular' => false],
                            ['name' => 'Pipe Wrench 10 inch',         'price' => 189, 'description' => 'Heavy-duty pipe wrench.', 'popular' => false],
                            ['name' => 'Teflon Tape (3 pcs)',         'price' => 35,  'description' => 'Thread seal tape for pipes.', 'popular' => false],
                        ],
                    ],
                ],
            ],
            [
                'name'                       => 'Watsons - Claveria',
                'description'                => 'Beauty • Personal Care • Health',
                'category'                   => 'beauty',
                'status'                     => 'active',
                'is_open'                    => true,
                'is_featured'                => false,
                'rating'                     => 4.5,
                'total_reviews'              => 289,
                'delivery_fee'               => 49.00,
                'min_order_amount'           => 150,
                'estimated_delivery_minutes' => 30,
                'accepts_online_payment'     => true,
                'slug'                       => 'watsons-claveria',
                'menu' => [
                    [
                        'name'    => 'Skincare',
                        'popular' => true,
                        'items'   => [
                            ['name' => 'Pond\'s White Beauty Cream 50g','price' => 89, 'description' => 'Whitening moisturizer.', 'popular' => true],
                            ['name' => 'Neutrogena Sunscreen SPF50',   'price' => 299, 'description' => 'Daily UV protection.', 'popular' => true],
                            ['name' => 'Cetaphil Gentle Cleanser 125ml','price' => 249,'description' => 'Gentle face wash for all skin types.', 'popular' => false],
                            ['name' => 'Kojic Acid Soap (2 bars)',     'price' => 89,  'description' => 'Skin whitening soap.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'    => 'Hair Care',
                        'popular' => false,
                        'items'   => [
                            ['name' => 'Pantene Shampoo 170ml',       'price' => 89,  'description' => 'Smooth and silky shampoo.', 'popular' => false],
                            ['name' => 'Cream Silk Conditioner 170ml','price' => 89,  'description' => 'Hair conditioner for soft hair.', 'popular' => false],
                            ['name' => 'Palmolive Naturals 200ml',    'price' => 79,  'description' => 'Moisturizing shampoo.', 'popular' => false],
                        ],
                    ],
                    [
                        'name'    => 'Personal Care',
                        'popular' => false,
                        'items'   => [
                            ['name' => 'Colgate Toothpaste 150g',     'price' => 79,  'description' => 'Cavity protection toothpaste.', 'popular' => false],
                            ['name' => 'Safeguard Soap 135g (3 bars)','price' => 89,  'description' => 'Antibacterial bath soap.', 'popular' => false],
                            ['name' => 'Rexona Deodorant 40ml',       'price' => 69,  'description' => '48-hour protection deodorant.', 'popular' => false],
                            ['name' => 'Whisper Cottony 8 pads',      'price' => 49,  'description' => 'Feminine hygiene pads.', 'popular' => false],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($stores as $storeData) {
            $menuData = $storeData['menu'];
            unset($storeData['menu']);

            $partner = Partner::updateOrCreate(
                ['slug' => $storeData['slug']],
                $storeData
            );

            // Clear existing menu
            MenuCategory::where('partner_id', $partner->id)->delete();

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
