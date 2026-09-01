<?php

namespace App\Imports;

use App\Models\Dinner;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;

class DinnersImport implements ToModel
{
    protected ?int $subdealershipId;
    protected ?int $cafeId;

    protected array $duplicates = [];
    protected int $importedCount = 0;
    protected array $seenDnis = [];

    public function __construct(?int $subdealershipId = null, ?int $cafeId = null)
    {
        $this->subdealershipId = $subdealershipId;
        $this->cafeId          = $cafeId;
    }

    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        $name = trim($row['name'] ?? $row[0] ?? '');
        if (!$name || strtolower($name) === 'nombre' || strtolower($name) === 'nombre completo') {
            return null;
        }

        $rawDni = trim($row['dni'] ?? $row[1] ?? '');
        // Se admite DNI (8 dígitos) y Carné de Extranjería (hasta 12 alfanuméricos).
        $dni = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawDni));
        $dni = substr($dni, 0, 12);
        if (!$dni) {
            return null;
        }

        // Check if DNI is already registered in DB or seen in this batch
        $existingDinner = Dinner::with('mine')->where('dni', $dni)->first();
        $seenInBatch    = in_array($dni, $this->seenDnis, true);

        if ($existingDinner || $seenInBatch) {
            $mineName = $existingDinner?->mine?->name;
            if (!$mineName && Auth::user()?->mine_id) {
                $userMine = Auth::user()->loadMissing('mine')->mine;
                $mineName = $userMine?->name;
            }

            $this->duplicates[] = [
                'name'      => $name,
                'dni'       => $dni,
                'mine_name' => $mineName ?: 'Sin Mina',
                'reason'    => $existingDinner ? 'Ya registrado' : 'Duplicado en Excel',
            ];
            return null;
        }

        $this->seenDnis[] = $dni;
        $this->importedCount++;

        $subId = $this->subdealershipId ?: (isset($row[3]) && is_numeric($row[3]) ? (int) $row[3] : null);

        $data = [
            'name'             => $name,
            'dni'              => $dni,
            'phone'            => trim($row['phone'] ?? $row[2] ?? '') ?: null,
            'subdealership_id' => $subId,
            'mine_id'          => Auth::user()?->mine_id,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('dinners', 'cafe_id')) {
            $data['cafe_id'] = $this->cafeId ?: (isset($row[4]) && is_numeric($row[4]) ? (int) $row[4] : null);
        }

        return new Dinner($data);
    }

    public function getDuplicates(): array
    {
        return $this->duplicates;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
