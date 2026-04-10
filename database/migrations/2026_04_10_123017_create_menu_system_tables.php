<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Menu Categories (e.g. "Popular", "Pharmacy", "Health", "Personal Care")
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->string('name');                          // "Pharmacy", "Health", "Popular"
            $table->string('icon_url')->nullable();          // optional category icon
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);   // marks the "Popular" tab
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['partner_id', 'is_active', 'sort_order']);
        });

        // Menu Items (the actual products)
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->onDelete('cascade');
            $table->foreignId('menu_category_id')->constrained()->onDelete('cascade');
            $table->string('name');                          // "Face Mask (50pcs)"
            $table->text('description')->nullable();         // "3-ply disposable surgical face masks."
            $table->string('image_url')->nullable();
            $table->decimal('price', 10, 2);                 // ₱250.00
            $table->decimal('original_price', 10, 2)->nullable(); // for showing discounts
            $table->string('unit')->nullable();              // "pcs", "bottle", "pack", "kg"
            $table->boolean('is_available')->default(true);
            $table->boolean('is_popular')->default(false);   // shows in "Popular" tab
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_best_seller')->default(false);
            $table->boolean('requires_prescription')->default(false); // for pharmacy items
            $table->integer('sort_order')->default(0);
            $table->json('tags')->nullable();                // ["new", "sale", "bestseller"]
            $table->json('options')->nullable();             // size/variant options
            $table->timestamps();

            $table->index(['partner_id', 'is_available']);
            $table->index(['menu_category_id', 'sort_order']);
            $table->index(['partner_id', 'is_popular']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
    }
};
