<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    { 
        if (!Schema::hasTable('d_pat'))
        {
            Schema::connection('mysql')->create('d_pat', function (Blueprint $table) {
                $table->bigIncrements('d_pat_id');
                $table->string('HCODE')->nullable();//
                $table->string('HN')->nullable();//
                $table->string('CHANGWAT')->nullable();//
                $table->string('AMPHUR')->nullable();//
                $table->string('DOB')->nullable();//                  
                $table->string('SEX')->nullable();//  
                $table->string('MARRIAGE')->nullable(); //             
                $table->string('OCCUPA')->nullable(); //  
                $table->string('NATION')->nullable(); // 
                $table->string('PERSON_ID')->nullable(); // 
                $table->string('NAMEPAT')->nullable(); // 
                $table->string('TITLE')->nullable(); // 
                $table->string('FNAME')->nullable(); // 
                $table->string('LNAME')->nullable(); // 
                $table->string('IDTYPE')->nullable(); // 
                $table->string('d_anaconda_id')->nullable(); // 
                $table->string('user_id')->nullable(); //   
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('d_pat');
    }
};
