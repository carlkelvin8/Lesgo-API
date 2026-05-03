<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\Partner;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Generate slugs for existing partners that don't have one
        $partners = Partner::whereNull('slug')->orWhere('slug', '')->get();
        
        foreach ($partners as $partner) {
            $base = Str::slug($partner->name);
            $slug = $base;
            $i = 1;
            
            // Ensure uniqueness
            while (Partner::where('slug', $slug)->where('id', '!=', $partner->id)->exists()) {
                $slug = "{$base}-{$i}";
                $i++;
            }
            
            $partner->slug = $slug;
            $partner->save();
            
            echo "Generated slug for partner #{$partner->id} ({$partner->name}): {$slug}\n";
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - slugs can stay
    }
};
