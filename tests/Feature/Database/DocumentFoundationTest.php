<?php

namespace Tests\Feature\Database;

use App\Models\Beneficiary;
use App\Models\Document;
use App\Models\Household;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 2 — review of the document foundation.
 *
 * The documents table is metadata only. Files are stored outside the database
 * (private disk) by a later phase; ORWeMS deliberately does not hold BLOBs and
 * does not publish document URLs.
 */
class DocumentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_hold_metadata_and_no_binary_payload(): void
    {
        $columns = Schema::getColumnListing('documents');

        $expected = [
            'id', 'documentable_type', 'documentable_id', 'document_type',
            'original_filename', 'storage_path', 'mime_type', 'file_size',
            'uploaded_by', 'created_at', 'updated_at', 'deleted_at',
        ];

        foreach ($expected as $column) {
            $this->assertContains($column, $columns);
        }

        // No column may carry file contents.
        foreach ($columns as $column) {
            $this->assertDoesNotMatchRegularExpression(
                '/(blob|content|contents|binary|payload|data)$/i',
                $column,
                "The documents table must not store file payloads in [{$column}]."
            );
        }
    }

    public function test_a_document_can_be_attached_to_a_beneficiary(): void
    {
        $beneficiary = Beneficiary::factory()->create();

        $document = $beneficiary->documents()->create([
            'document_type' => 'birth_certificate',
            'original_filename' => 'certificate-scan.pdf',
            'storage_path' => 'beneficiaries/'.$beneficiary->id.'/certificate-scan.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 20480,
        ]);

        $document->refresh();

        $this->assertTrue($document->documentable->is($beneficiary));
        $this->assertSame('birth_certificate', $document->document_type);
        $this->assertSame(20480, $document->file_size);
        $this->assertNull($document->uploader);
    }

    public function test_a_document_can_be_attached_to_a_household(): void
    {
        $household = Household::factory()->create();

        $document = $household->documents()->create([
            'document_type' => 'consent_form',
            'original_filename' => 'consent.pdf',
            'storage_path' => 'households/'.$household->id.'/consent.pdf',
        ]);

        $this->assertTrue($document->refresh()->documentable->is($household));
    }

    public function test_documents_are_soft_deleted_but_retained_for_audit(): void
    {
        $beneficiary = Beneficiary::factory()->create();
        $document = $beneficiary->documents()->create([
            'document_type' => 'school_report',
            'original_filename' => 'report.pdf',
            'storage_path' => 'beneficiaries/'.$beneficiary->id.'/report.pdf',
        ]);

        $document->delete();

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
        $this->assertSame(0, Document::count());

        // Withdrawing a document leaves an auditable trace of its metadata.
        $withdrawn = Document::withTrashed()->findOrFail($document->id);
        $this->assertSame('school_report', $withdrawn->document_type);
        $this->assertSame('beneficiaries/'.$beneficiary->id.'/report.pdf', $withdrawn->storage_path);
    }

    public function test_no_public_url_accessor_is_exposed_on_the_document_model(): void
    {
        // Access control and download URLs are a later phase; nothing here may
        // publish a predictable public path to a child's documents.
        $this->assertFalse(method_exists(Document::class, 'getPublicUrlAttribute'));
        $this->assertFalse(method_exists(Document::class, 'getUrlAttribute'));
        $this->assertFalse(method_exists(Document::class, 'publicUrl'));
    }
}
