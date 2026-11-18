<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration {public function up():void{Schema::create('customers',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->string('name');$t->string('email')->nullable();$t->string('phone',30)->nullable();$t->text('notes')->nullable();$t->timestamps();$t->index(['company_id','name']);});}public function down():void{Schema::dropIfExists('customers');}};
