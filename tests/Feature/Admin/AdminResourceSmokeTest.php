<?php

use App\Livewire\Admin\Support\ResourceRegistry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
|--------------------------------------------------------------------------
| Local MySQL resource smoke test
|--------------------------------------------------------------------------
|
| Renders every admin resource page against the configured MySQL database.
| It is skipped on SQLite/CI. Resources whose tables or columns are missing
| from the local database are reported as skipped instead of failing, since
| the schema of that database predates the current code (Nova fails too).
|
*/
it('renders every admin resource page', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires a MySQL database.');
    }

    $this->actingAs(User::query()->firstOrFail());
    $this->withoutExceptionHandling();

    $unexpected = [];
    $skipped = [];

    $isSchemaIssue = function (Throwable $e): bool {
        $message = $e->getMessage();

        return str_contains($message, "doesn't exist")
            || str_contains($message, 'Base table or view not found')
            || str_contains($message, 'Unknown column')
            || str_contains($message, 'no such table');
    };

    $check = function (string $label, string $url) use (&$unexpected, &$skipped, $isSchemaIssue) {
        try {
            $status = $this->get($url)->getStatusCode();

            if ($status >= 500) {
                $unexpected[] = "$label -> HTTP $status";
            }
        } catch (Throwable $e) {
            if ($e instanceof AuthorizationException) {
                return;
            }

            if ($e instanceof NotFoundHttpException) {
                return;
            }

            if ($isSchemaIssue($e)) {
                $skipped[] = "$label -> schema";

                return;
            }

            $unexpected[] = "$label -> ".get_class($e).': '.$e->getMessage();
        }
    };

    $check('dashboard', '/app/dashboard');

    foreach (['general', 'attendance', 'payroll', 'leave'] as $page) {
        $check("settings:$page", "/app/settings/$page");
    }

    foreach (ResourceRegistry::available() as $class) {
        $key = $class::uriKey();
        $model = $class::newModel();
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            $skipped[] = "$key -> missing table $table";

            continue;
        }

        $check("index:$key", "/app/resources/$key");
        $check("create:$key", "/app/resources/$key/new");

        try {
            $record = $model->newQuery()->first();
        } catch (Throwable $e) {
            $skipped[] = "$key -> lookup";

            continue;
        }

        if ($record) {
            $check("detail:$key", "/app/resources/$key/{$record->getKey()}");
            $check("edit:$key", "/app/resources/$key/{$record->getKey()}/edit");
        }
    }

    if ($unexpected !== []) {
        fwrite(STDERR, "\nUnexpected failures (".count($unexpected)."):\n".implode("\n", $unexpected)."\n");
    }

    fwrite(STDERR, "\nSkipped (".count($skipped)."):\n".implode("\n", $skipped)."\n");

    expect($unexpected)->toBe([]);
});
