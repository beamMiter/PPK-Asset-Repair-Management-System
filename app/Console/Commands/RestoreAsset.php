<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Brings back a deleted asset. While it is deleted, its code, serial and HIS number stay taken (the database keeps them
 * unique across deleted rows too), so restoring it is the way to use them again. Its repair history comes back with it.
 */
class RestoreAsset extends Command
{
    protected $signature = 'assets:restore {code : the deleted asset\'s code (asset_code)}';

    protected $description = 'Bring back a deleted asset, with its code, serial and HIS number';

    public function handle(): int
    {
        $code = (string) $this->argument('code');
        $asset = Asset::onlyTrashed()->where('asset_code', $code)->first();

        if (! $asset) {
            $this->error(Asset::where('asset_code', $code)->exists()
                ? "Asset {$code} is not deleted."
                : "There is no asset with the code {$code}.");

            return self::FAILURE;
        }

        $asset->restore();
        Log::info('[assets:restore] restored', ['asset_id' => $asset->id, 'asset_code' => $code]);

        $this->info("Asset {$code} \"{$asset->name}\" is back.");

        return self::SUCCESS;
    }
}
