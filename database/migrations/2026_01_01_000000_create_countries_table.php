<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table) {
            $table->id();
            $table->char('alpha2', 2)->unique()->comment('ISO 3166-1 alpha-2');
            $table->char('alpha3', 3)->nullable()->unique()->comment('ISO 3166-1 alpha-3');
            $table->char('numeric_code', 3)->nullable()->index()->comment('ISO 3166-1 numeric');
            $table->string('name');
            $table->string('region')->nullable()->index();
            $table->string('capital')->nullable();
            $table->string('calling_code', 16)->nullable()->comment('International dialing code, without "+"');
            $table->string('flag_emoji', 16)->nullable();
            $table->string('flag_png')->nullable();
            $table->string('flag_svg')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return config('country.table', 'countries');
    }
};
