<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('routetrack:transfer-to-pgsql {--truncate : Empty PostgreSQL target table before copying} {--chunk=1000 : Number of rows copied per batch}', function () {
    $source = DB::connection('mysql');
    $target = DB::connection('pgsql_transfer');
    $targetSchema = Schema::connection('pgsql_transfer');
    $targetTable = 'trac_routetrack';
    $chunkSize = max(1, (int) $this->option('chunk'));

    if (! $targetSchema->hasTable($targetTable)) {
        $targetSchema->create($targetTable, function (Blueprint $table) {
            $table->bigIncrements('entryid');
            $table->bigInteger('routekey')->nullable()->index();
            $table->bigInteger('routecode')->nullable()->index();
            $table->bigInteger('salesmancode')->nullable()->index();
            $table->date('entrydate')->nullable()->index();
            $table->time('entrytime')->nullable();
            $table->string('deviceid', 191)->nullable()->index();
            $table->decimal('latitude', 13, 10)->nullable();
            $table->decimal('longitude', 13, 10)->nullable();
            $table->timestamp('devicetimestamp')->nullable()->index();
        });

        $this->info("Created PostgreSQL table {$targetTable}.");
    }

    if ($this->option('truncate')) {
        $target->table($targetTable)->truncate();
        $this->info("Truncated PostgreSQL table {$targetTable}.");
    }

    $total = $source->table('routetrack')->count();
    $bar = $this->output->createProgressBar($total);
    $bar->start();

    $copied = 0;

    $source->table('routetrack')
        ->select([
            'entryid',
            'routekey',
            'routecode',
            'salesmancode',
            'entrydate',
            'entrytime',
            'deviceid',
            'latitude',
            'longitude',
            'devicetimestamp',
        ])
        ->orderBy('entryid')
        ->chunkById($chunkSize, function ($rows) use ($target, $targetTable, $bar, &$copied) {
            $payload = $rows->map(fn (object $row) => [
                'entryid' => $row->entryid,
                'routekey' => $row->routekey,
                'routecode' => $row->routecode,
                'salesmancode' => $row->salesmancode,
                'entrydate' => $row->entrydate,
                'entrytime' => $row->entrytime,
                'deviceid' => $row->deviceid,
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'devicetimestamp' => $row->devicetimestamp,
            ])->all();

            $target->table($targetTable)->upsert(
                $payload,
                ['entryid'],
                ['routekey', 'routecode', 'salesmancode', 'entrydate', 'entrytime', 'deviceid', 'latitude', 'longitude', 'devicetimestamp']
            );

            $copied += count($payload);
            $bar->advance(count($payload));
        }, 'entryid');

    $bar->finish();
    $this->newLine(2);
    $this->info("Copied {$copied} of {$total} routetrack rows to PostgreSQL table {$targetTable}.");
})->purpose('Copy MySQL trac_routetrack data to PostgreSQL');
