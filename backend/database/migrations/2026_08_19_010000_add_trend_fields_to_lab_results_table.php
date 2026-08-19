<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add time-series fields to lab_results (Phase 3 — Lab Result Trends).
     *
     * `user_id` is denormalized from the owning medical_documents row so the
     * `(user_id, name, collected_at)` index can serve cross-report trend queries.
     */
    public function up(): void
    {
        Schema::table('lab_results', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('document_extraction_id');
            $table->string('loinc', 20)->nullable()->after('unit');
            $table->string('normalized_name', 255)->nullable()->after('name');
            $table->timestamp('collected_at')->nullable()->after('status');
            $table->index(['user_id', 'normalized_name', 'collected_at'], 'lab_results_user_name_collected_idx');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        $this->backfillTrendFields();
    }

    /**
     * Backfill denormalized fields from the owning document chain.
     *
     * Done in PHP (not raw SQL) so the migration runs on MySQL and SQLite alike.
     */
    private function backfillTrendFields(): void
    {
        $rows = DB::table('lab_results')
            ->join('document_extractions', 'document_extractions.id', '=', 'lab_results.document_extraction_id')
            ->join('medical_documents', 'medical_documents.id', '=', 'document_extractions.medical_document_id')
            ->whereNull('lab_results.user_id')
            ->select('lab_results.id', 'lab_results.name', 'medical_documents.user_id', 'medical_documents.created_at')
            ->get();

        foreach ($rows as $row) {
            DB::table('lab_results')
                ->where('id', $row->id)
                ->update([
                    'user_id' => $row->user_id,
                    'normalized_name' => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $row->name))),
                    'collected_at' => $row->created_at,
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_results', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex('lab_results_user_name_collected_idx');
            $table->dropColumn(['user_id', 'loinc', 'normalized_name', 'collected_at']);
        });
    }
};