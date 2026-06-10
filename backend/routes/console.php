<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Domain\Promotions\PromotionWorkflowService;
use App\Services\UexSyncService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('promotions:expire-pending', function (PromotionWorkflowService $workflow) {
    $count = $workflow->expireDueOffers();

    $this->info("Expired {$count} promotion offer(s).");
})->purpose('Expire stale pending promotion offers.');

Artisan::command('uex:sync {scope=all} {--resource=*}', function (UexSyncService $syncService) {
    $scope = (string) $this->argument('scope');
    $resources = collect((array) $this->option('resource'))
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values()
        ->all();

    try {
        $run = $syncService->sync($scope, $resources);
    } catch (\InvalidArgumentException $e) {
        $this->error($e->getMessage());

        return self::FAILURE;
    } catch (\Throwable $e) {
        $this->error('UEX sync failed before completion.');
        $this->line($e->getMessage());

        return self::FAILURE;
    }

    $this->newLine();
    $this->info("UEX sync run #{$run->id} finished with status [{$run->status}].");

    foreach ($run->resource_results ?? [] as $result) {
        $status = strtoupper((string) ($result['status'] ?? 'unknown'));
        $resource = (string) ($result['resource'] ?? 'unknown');
        $records = (int) ($result['records'] ?? 0);

        if (($result['status'] ?? null) === 'success') {
            $this->line("[{$status}] {$resource}: {$records} row(s) processed.");
            continue;
        }

        $this->warn("[{$status}] {$resource}: " . (string) ($result['error'] ?? 'Unknown error'));
    }

    $this->newLine();
    $this->line("Successful resources: {$run->successful_resources}");
    $this->line("Failed resources: {$run->failed_resources}");
    $this->line("Total records processed: {$run->total_records}");

    return $run->status === 'success' ? self::SUCCESS : self::FAILURE;
})->purpose('Sync public UEX reference data into local snapshot tables.');

Schedule::command('promotions:expire-pending')->everyFiveMinutes();
Schedule::command('uex:sync --resource=commodities_prices_all')
    ->dailyAt('03:15')
    ->withoutOverlapping();
