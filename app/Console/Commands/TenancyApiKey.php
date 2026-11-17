<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TenancyApiKey extends Command
{
    protected $signature = 'tenancy:api-key {company : Company id} {--rotate : Replace an existing key}';

    protected $description = 'Generate a hashed API key for a company integration';

    public function handle(): int
    {
        $company = Company::query()->find($this->argument('company'));

        if ($company === null) {
            $this->error('Company not found.');

            return self::FAILURE;
        }

        if ($company->api_key !== null && ! $this->option('rotate')) {
            $this->warn('This company already has an API key. Use --rotate to replace it.');

            return self::FAILURE;
        }

        $plain = Str::random(48);

        $company->forceFill(['api_key' => hash('sha256', $plain)])->save();

        $this->info("API key for {$company->name} (store it now, it is not shown again):");
        $this->line($plain);

        return self::SUCCESS;
    }
}
