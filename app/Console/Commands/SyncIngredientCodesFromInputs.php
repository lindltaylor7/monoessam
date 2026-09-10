<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncIngredientCodesFromInputs extends Command
{
    protected $signature = 'ingredients:sync-codes {--fresh : Limpia code/unit_medida antes de re-emparejar}';

    protected $description = 'Copia code y unidad de medida desde inputs a ingredients emparejando por name (sin distinguir mayúsculas).';

    public function handle(): int
    {
        if ($this->option('fresh')) {
            DB::table('ingredients')->update(['code' => null, 'unit_medida' => null]);
            $this->warn('code / unit_medida reiniciados en ingredients.');
        }

        $matched = 0;

        DB::table('inputs')
            ->select('code', 'name', 'unit_of_measure')
            ->orderBy('id')
            ->chunk(500, function ($inputs) use (&$matched) {
                foreach ($inputs as $input) {
                    $name = trim(mb_strtolower($input->name));
                    if ($name === '') {
                        continue;
                    }

                    $matched += DB::table('ingredients')
                        ->whereRaw('LOWER(TRIM(name)) = ?', [$name])
                        ->update([
                            'code'        => $input->code,
                            'unit_medida' => $input->unit_of_measure,
                        ]);
                }
            });

        $withCode = DB::table('ingredients')->whereNotNull('code')->count();
        $total = DB::table('ingredients')->count();

        $this->info("Filas actualizadas: {$matched}. Ingredientes con código: {$withCode} / {$total}.");

        return self::SUCCESS;
    }
}
