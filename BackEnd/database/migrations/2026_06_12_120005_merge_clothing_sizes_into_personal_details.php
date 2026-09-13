<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('user_personal_details', function (Blueprint $table) {
            $table->string('shoe_size')->nullable(); $table->string('trouser_size')->nullable();
            $table->string('shirt_size')->nullable(); $table->string('jacket_size')->nullable();
            $table->string('uniform_notes')->nullable();
        });
        if (Schema::hasTable('user_clothing_sizes')) {
            DB::table('user_clothing_sizes')->orderBy('id')->each(function ($row) {
                DB::table('user_personal_details')->updateOrInsert(['user_id'=>$row->user_id],[
                    'shoe_size'=>$row->shoe_size,'trouser_size'=>$row->trouser_size,'shirt_size'=>$row->shirt_size,
                    'jacket_size'=>$row->jacket_size,'uniform_notes'=>$row->uniform_notes,'updated_at'=>now(),'created_at'=>now(),
                ]);
            });
            Schema::drop('user_clothing_sizes');
        }
    }
    public function down(): void {
        Schema::create('user_clothing_sizes', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('shoe_size')->nullable(); $table->string('trouser_size')->nullable(); $table->string('shirt_size')->nullable();
            $table->string('jacket_size')->nullable(); $table->string('uniform_notes')->nullable(); $table->timestamps();
        });
        Schema::table('user_personal_details', fn(Blueprint $table)=>$table->dropColumn(['shoe_size','trouser_size','shirt_size','jacket_size','uniform_notes']));
    }
};
