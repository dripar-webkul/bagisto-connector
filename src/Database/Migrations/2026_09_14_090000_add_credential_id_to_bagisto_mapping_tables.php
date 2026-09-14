<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'wk_bagisto_attribute_config_mapping' => [
            'unique'  => 'wk_bagisto_attr_map_cred_section_unq',
            'foreign' => 'wk_bagisto_attr_map_cred_fk',
        ],
        'wk_bagisto_category_field_config_mapping' => [
            'unique'  => 'wk_bagisto_cat_map_cred_section_unq',
            'foreign' => 'wk_bagisto_cat_map_cred_fk',
        ],
    ];

    public function up(): void
    {
        $credentialIds = DB::table('wk_bagisto_credential')->orderBy('id')->pluck('id')->all();

        foreach (self::TABLES as $table => $indexNames) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedInteger('credential_id')->nullable()->after('id');
            });

            $this->fanOutExistingRows($table, $credentialIds);

            DB::table($table)->whereNull('credential_id')->delete();

            Schema::table($table, function (Blueprint $blueprint) use ($indexNames) {
                $blueprint->unsignedInteger('credential_id')->nullable(false)->change();

                $blueprint->unique(['credential_id', 'section'], $indexNames['unique']);

                $blueprint->foreign('credential_id', $indexNames['foreign'])
                    ->references('id')
                    ->on('wk_bagisto_credential')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $indexNames) {
            $this->keepOneRowPerSection($table);

            Schema::table($table, function (Blueprint $blueprint) use ($indexNames) {
                $blueprint->dropForeign($indexNames['foreign']);
                $blueprint->dropUnique($indexNames['unique']);
                $blueprint->dropColumn('credential_id');
            });
        }
    }

    /**
     * @param  array<int, int>  $credentialIds
     */
    private function fanOutExistingRows(string $table, array $credentialIds): void
    {
        if ($credentialIds === []) {
            return;
        }

        $shared = DB::table($table)->whereNull('credential_id')->get();

        if ($shared->isEmpty()) {
            return;
        }

        [$owner, $others] = [$credentialIds[0], array_slice($credentialIds, 1)];

        foreach ($others as $credentialId) {
            $copies = $shared->map(function ($row) use ($credentialId) {
                $copy = (array) $row;

                unset($copy['id']);
                $copy['credential_id'] = $credentialId;

                return $copy;
            })->all();

            DB::table($table)->insert($copies);
        }

        DB::table($table)->whereNull('credential_id')->update(['credential_id' => $owner]);
    }

    private function keepOneRowPerSection(string $table): void
    {
        $keep = DB::table($table)
            ->selectRaw('MIN(id) as id')
            ->groupBy('section')
            ->pluck('id')
            ->all();

        DB::table($table)->when($keep !== [], fn ($query) => $query->whereNotIn('id', $keep))->delete();
    }
};
