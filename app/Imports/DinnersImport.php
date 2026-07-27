<?php

namespace App\Imports;

use App\Models\Dinner;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Concerns\ToModel;

class DinnersImport implements ToModel
{
    protected ?int $subdealershipId;
    protected ?int $cafeId;

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

        $dni = trim($row['dni'] ?? $row[1] ?? '');
        if (!$dni) {
            return null;
        }

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
}
