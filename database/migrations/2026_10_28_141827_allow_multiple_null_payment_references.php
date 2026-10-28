<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::statement('DROP INDEX [payments_reference_unique] ON [dbo].[pagos]');
        DB::statement(
            'CREATE UNIQUE INDEX [payments_reference_unique] ON [dbo].[pagos] ([referencia]) WHERE [referencia] IS NOT NULL',
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        if (DB::table('pagos')->whereNull('referencia')->count() > 1) {
            throw new RuntimeException(
                'Cannot restore the unfiltered payment reference index while multiple NULL references exist.',
            );
        }

        DB::statement('DROP INDEX [payments_reference_unique] ON [dbo].[pagos]');
        DB::statement(
            'CREATE UNIQUE INDEX [payments_reference_unique] ON [dbo].[pagos] ([referencia])',
        );
    }
};
