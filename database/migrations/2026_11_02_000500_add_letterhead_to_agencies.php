<?php

use App\Models\Agency;
use App\Support\KnownLetterheads;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('name');
            $table->text('registrations')->nullable()->after('tagline');   // one line per row on the letterhead
            $table->string('website')->nullable()->after('email');
            $table->string('letterhead_emails')->nullable()->after('website');
            $table->string('phones')->nullable()->after('phone');
            $table->string('logo_path')->nullable()->after('phones');
        });

        // Z Plus's details from its printed letterhead, only where fields are still empty.
        if ($zps = Agency::query()->where('code', 'ZPS')->first()) {
            KnownLetterheads::applyZplus($zps);
        }
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'registrations', 'website', 'letterhead_emails', 'phones', 'logo_path']);
        });
    }
};
