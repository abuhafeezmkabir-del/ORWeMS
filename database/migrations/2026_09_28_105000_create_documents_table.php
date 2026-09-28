<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Document metadata only — files live on disk (private), never BLOBs.
        // documentable_* is a morph pair; documentable rows restrictOnDelete
        // is enforced at the application level (see Document model).
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('documentable_type', 120);
            $table->unsignedBigInteger('documentable_id');
            $table->string('document_type', 60);
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Metadata is retained for audit even when a document is
            // withdrawn; the physical file handling is a Phase 3 concern.
            $table->softDeletes();

            $table->index(['documentable_type', 'documentable_id']);
            $table->index('document_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
