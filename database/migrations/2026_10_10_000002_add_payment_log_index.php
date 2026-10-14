<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('log_index')->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_network_transaction_hash_unique');
        });

        DB::statement(
            'CREATE UNIQUE INDEX payments_network_tx_log_unique
             ON payments (network, transaction_hash, log_index)
             WHERE log_index IS NOT NULL'
        );

        DB::statement(
            'CREATE UNIQUE INDEX payments_network_tx_legacy_unique
             ON payments (network, transaction_hash)
             WHERE log_index IS NULL'
        );

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION arcflow_guard_payment_log_index()
                RETURNS trigger AS $$
                BEGIN
                    PERFORM pg_advisory_xact_lock(
                        hashtextextended(NEW.network || ':' || NEW.transaction_hash, 0)
                    );

                    IF EXISTS (
                        SELECT 1
                        FROM payments AS existing
                        WHERE existing.network = NEW.network
                          AND existing.transaction_hash = NEW.transaction_hash
                          AND existing.id <> COALESCE(NEW.id, -1)
                          AND (existing.log_index IS NULL)
                              <> (NEW.log_index IS NULL)
                    ) THEN
                        RAISE EXCEPTION
                            'Legacy and indexed payment records cannot share a transaction hash'
                            USING ERRCODE = '23505';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER arcflow_guard_payment_log_index_insert
                BEFORE INSERT ON payments
                FOR EACH ROW
                EXECUTE FUNCTION arcflow_guard_payment_log_index()
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER arcflow_guard_payment_log_index_update
                BEFORE UPDATE OF network, transaction_hash, log_index ON payments
                FOR EACH ROW
                EXECUTE FUNCTION arcflow_guard_payment_log_index()
            SQL);
        } elseif ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER arcflow_guard_payment_log_index_insert
                BEFORE INSERT ON payments
                WHEN EXISTS (
                    SELECT 1
                    FROM payments AS existing
                    WHERE existing.network = NEW.network
                      AND existing.transaction_hash = NEW.transaction_hash
                      AND existing.id <> COALESCE(NEW.id, -1)
                      AND ((existing.log_index IS NULL)
                           <> (NEW.log_index IS NULL))
                )
                BEGIN
                    SELECT RAISE(
                        ABORT,
                        'Legacy and indexed payment records cannot share a transaction hash'
                    );
                END
            SQL);

            DB::unprepared(<<<'SQL'
                CREATE TRIGGER arcflow_guard_payment_log_index_update
                BEFORE UPDATE OF network, transaction_hash, log_index ON payments
                WHEN EXISTS (
                    SELECT 1
                    FROM payments AS existing
                    WHERE existing.network = NEW.network
                      AND existing.transaction_hash = NEW.transaction_hash
                      AND existing.id <> NEW.id
                      AND ((existing.log_index IS NULL)
                           <> (NEW.log_index IS NULL))
                )
                BEGIN
                    SELECT RAISE(
                        ABORT,
                        'Legacy and indexed payment records cannot share a transaction hash'
                    );
                END
            SQL);
        }
    }

    public function down(): void
    {
        // Check first so a refused rollback leaves the protective triggers intact.
        $duplicates = DB::table('payments')
            ->select('network', 'transaction_hash')
            ->groupBy('network', 'transaction_hash')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'Cannot roll back: multiple payment events share a transaction hash.'
            );
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS arcflow_guard_payment_log_index_insert ON payments'
            );
            DB::unprepared(
                'DROP TRIGGER IF EXISTS arcflow_guard_payment_log_index_update ON payments'
            );
            DB::unprepared(
                'DROP FUNCTION IF EXISTS arcflow_guard_payment_log_index()'
            );
        } elseif ($driver === 'sqlite') {
            DB::unprepared(
                'DROP TRIGGER IF EXISTS arcflow_guard_payment_log_index_insert'
            );
            DB::unprepared(
                'DROP TRIGGER IF EXISTS arcflow_guard_payment_log_index_update'
            );
        }

        DB::statement('DROP INDEX payments_network_tx_log_unique');
        DB::statement('DROP INDEX payments_network_tx_legacy_unique');

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(
                ['network', 'transaction_hash'],
                'payments_network_transaction_hash_unique'
            );
            $table->dropColumn('log_index');
        });
    }
};
