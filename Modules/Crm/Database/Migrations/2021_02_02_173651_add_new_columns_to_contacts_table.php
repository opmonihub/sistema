<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddNewColumnsToContactsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->integer('converted_by')->nullable()->after('created_by');
            $table->datetime('converted_on')->nullable()->after('converted_by');

            $table->string('custom_field5')->nullable();
            $table->string('custom_field6')->nullable();
            $table->string('custom_field7')->nullable();
            $table->string('custom_field8')->nullable();
            $table->string('custom_field9')->nullable();
            $table->string('custom_field10')->nullable();

            $table->string('prefix')->after('name')->nullable();
            $table->string('first_name')->after('prefix')->nullable();
            $table->string('middle_name')->after('first_name')->nullable();
            $table->string('last_name')->after('middle_name')->nullable();

            $table->string('zip_code')->nullable();
            $table->date('dob')->after('zip_code')->nullable();
            $table->text('address_line_1');
            $table->text('address_line_2');
            // $table->text('address_line_2')->after('landmark')->nullable();

            // DB::statement("ALTER TABLE contacts CHANGE landmark address_line_1 text;");
            // DB::statement("UPDATE contacts SET first_name=name;");

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
