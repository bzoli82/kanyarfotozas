<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSTRAINT = 'contact_messages_contact_type_check';

    public function up(): void
    {
        DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT '.self::CONSTRAINT);
        DB::statement(
            'ALTER TABLE contact_messages ADD CONSTRAINT '.self::CONSTRAINT.
            " CHECK (contact_type::text = ANY (ARRAY['support','photographer','other','photographer_application']::text[]))"
        );
    }

    public function down(): void
    {
        DB::statement('UPDATE contact_messages SET contact_type = \'other\' WHERE contact_type = \'photographer_application\'');
        DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT '.self::CONSTRAINT);
        DB::statement(
            'ALTER TABLE contact_messages ADD CONSTRAINT '.self::CONSTRAINT.
            " CHECK (contact_type::text = ANY (ARRAY['support','photographer','other']::text[]))"
        );
    }
};
