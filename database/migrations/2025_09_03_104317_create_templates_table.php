<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('entry_file')->default('index.html')->comment('Fichier d\'entrée du template');
            
            // Hybrid storage fields
            $table->longText('zip_content')->nullable()->comment('Contenu ZIP en base64');
            $table->integer('zip_size')->nullable()->comment('Taille du ZIP en octets');
            $table->boolean('is_extracted')->default(false)->comment('Si le template est extrait');
            $table->timestamp('extracted_at')->nullable()->comment('Date de dernière extraction');
            $table->string('original_filename')->nullable()->comment('Nom du fichier ZIP original');
            $table->string('preview_image')->nullable()->comment('Chemin vers l\'image de prévisualisation');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour les recherches
            $table->index('name');
            $table->index('user_id');
            $table->index('is_extracted');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('templates');
    }
};
