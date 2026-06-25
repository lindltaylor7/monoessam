<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Business;
use App\Models\Cafe;
use App\Models\Cloth;
use App\Models\Guard;
use App\Models\Observation;
use App\Models\Staff;
use App\Models\Staff_clothes;
use App\Models\Staff_file;
use App\Models\Staff_financial;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    public function index()
    {
        return Inertia::render('staff/Index', [
            'cafes'       => Cafe::with('unit')->get(),
            'staff'       => Staff::with([
                'photo',
                'staff_files',
                'staff_financial',
                'staff_clothes',
                'observations'   => fn($q) => $q->orderBy('created_at', 'desc'),
                'observations.user',
                'staffable'      => fn($q) => $q->morphWith([
                    Cafe::class => ['unit'],
                    Area::class => ['headquarters', 'headquarters.business'],
                ]),
                'guardRole.guardSelected',
            ])->orderBy('id', 'desc')->get(),
            'roles'       => Role::all(),
            'units'       => Unit::with(['cafes', 'mine'])->get(),
            'businneses'  => Business::with(['headquarters', 'headquarters.areas'])->get(),
            'roleClothes' => $this->buildRoleClothesMap(),
        ]);
    }

    public function create() {}

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'dni'  => 'required|unique:staff|max:8',
            'cell' => 'required',
        ]);

        $staff = $this->createStaff($request);
        $this->syncGuardRole($request, $staff);
        $this->handleStaffFiles($request, $staff);
        $this->handleStaffPhoto($request, $staff);
        Staff_financial::create(['staff_id' => $staff->id] + $this->buildFinancialAttributes($request));
        $this->syncStaffClothes($request, $staff);

        return redirect()->route('staff.index');
    }

    public function show(string $id) {}

    public function edit(string $id) {}

    public function update(Request $request, string $id)
    {
        $staff = Staff::with(['staff_financial', 'staff_clothes', 'photo'])->findOrFail($id);

        $this->handleStaffPhoto($request, $staff, replacing: true);

        $staff->update([
            'name'        => $request->name,
            'dni'         => $request->dni,
            'cell'        => $request->cell,
            'birthdate'   => $this->nullIfEmpty($request->birthdate),
            'age'         => $request->age,
            'sex'         => $request->sex,
            'email'       => $request->email,
            'country'     => $request->country,
            'civilstatus' => $request->civilstatus,
            'contactname' => $request->contactname,
            'contactcell' => $request->contactcell,
            'role_id'     => $request->roleId,
            'user_id'     => Auth::id(),
        ]);

        $this->updateStaffable($request, $staff);

        if ($staff->staff_financial) {
            $staff->staff_financial->update(
                $this->buildFinancialAttributes($request, isUpdate: true)
            );
        }

        $staff->staff_clothes->each->delete();
        $this->syncStaffClothes($request, $staff);

        return redirect()->route('staff.index');
    }

    public function destroy(string $id)
    {
        $staff      = Staff::findOrFail($id);
        $staffFiles = Staff_file::where('staff_id', $id)->get();

        if ($staff->photo) {
            Storage::disk('public')->delete($staff->photo->url);
            $staff->photo()->delete();
        }

        foreach ($staffFiles as $file) {
            Storage::disk('public')->delete($file->file_path);
        }

        $staffFiles->each->delete();
        $staff->delete();
    }

    public function banStaff(string $id)
    {
        $staffFiles     = Staff_file::where('staff_id', $id)->get();
        $staffFinancial = Staff_financial::where('staff_id', $id)->first();
        $staffClothes   = Staff_clothes::where('staff_id', $id)->get();

        foreach ($staffFiles as $file) {
            Storage::disk('public')->delete($file->file_path);
        }
        $staffFiles->each->delete();
        $staffFinancial?->delete();
        $staffClothes->each->delete();

        Staff::findOrFail($id)->update(['status' => 0]);

        return response()->json([
            'cafes' => Cafe::with('unit')->get(),
            'staff' => Staff::with(['staff_files', 'observations', 'observations.user'])->where('status', '!=', 0)->get(),
            'roles' => Role::all(),
        ]);
    }

    public function updateStatusStaff(Request $request)
    {
        Staff::findOrFail($request->staff_id)->update(['status' => $request->status]);

        if ($request->observation !== '') {
            Observation::create([
                'staff_id'    => $request->staff_id,
                'user_id'     => $request->user_id,
                'observation' => $request->observation,
                'date'        => date('Y-m-d'),
            ]);
        }
    }

    public function uploadFile(Request $request)
    {
        if ($request->fileId == 0) {
            if ($request->hasFile('file')) {
                Staff_file::create([
                    'staff_id'        => $request->staffId,
                    'file_type'       => $request->fileTypeKey,
                    'file_path'       => $this->storeUploadedFile($request),
                    'expiration_date' => $request->expirationDate,
                ]);
            }
        } else {
            $staffFile = Staff_file::findOrFail($request->fileId);
            Storage::disk('public')->delete($staffFile->file_path);

            if ($request->hasFile('file')) {
                $staffFile->update([
                    'file_type'       => $request->fileTypeKey,
                    'file_path'       => $this->storeUploadedFile($request),
                    'expiration_date' => $request->expirationDate,
                ]);
            }
        }
    }

    public function uploadFileDate(Request $request)
    {
        Staff_file::findOrFail($request->fileId)->update([
            'expiration_date' => $request->expirationDate,
        ]);
    }

    public function updateFileStatus(Request $request)
    {
        $staffFile = Staff_file::find($request->fileId);
        $staffFile?->update(['status' => $request->status]);
    }

    public function deleteFile(string $id)
    {
        $staffFile = Staff_file::findOrFail($id);
        Storage::disk('public')->delete($staffFile->file_path);
        $staffFile->delete();
    }

    public function massUploadSctr(Request $request)
    {
        $request->validate([
            'staffIds'              => 'required|array',
            'sctr_vida_ley'         => 'nullable|file|mimes:pdf',
            'sctr_vida_ley_exp'     => 'nullable|date',
            'sctr_pension_salud'    => 'nullable|file|mimes:pdf',
            'sctr_pension_salud_exp' => 'nullable|date',
            'sctr_socavon'          => 'nullable|file|mimes:pdf',
            'sctr_socavon_exp'      => 'nullable|date',
        ]);

        $config = [
            'SCTR Vida Ley'        => ['file' => $request->file('sctr_vida_ley'),      'exp' => $request->sctr_vida_ley_exp],
            'SCTR Pensión y Salud' => ['file' => $request->file('sctr_pension_salud'), 'exp' => $request->sctr_pension_salud_exp],
            'SCTR Socavón'         => ['file' => $request->file('sctr_socavon'),        'exp' => $request->sctr_socavon_exp],
        ];

        foreach ($config as $type => $data) {
            if (!$data['file']) {
                continue;
            }

            $filePath = $data['file']->storeAs(
                'files',
                time() . '_' . str_replace(' ', '_', $type) . '.pdf',
                'public'
            );

            foreach ($request->staffIds as $staffId) {
                Staff_file::updateOrCreate(
                    ['staff_id' => $staffId, 'file_type' => $type],
                    ['file_path' => $filePath, 'expiration_date' => $data['exp']]
                );
            }
        }

        return back()->with('success', 'Documentos cargados exitosamente');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildRoleClothesMap(): array
    {
        $map = [];
        foreach (Cloth::with('roles')->get() as $cloth) {
            foreach ($cloth->roles as $role) {
                $key = $role->pivot->cafe_id ?: 'all';
                $map[$role->id][$key][] = $cloth;
            }
        }
        return $map;
    }

    private function buildStaffAttributes(Request $request): array
    {
        return [
            'name'        => $request->name,
            'dni'         => $request->dni,
            'cell'        => $request->cell,
            'birthdate'   => $request->birthdate,
            'age'         => $request->age,
            'sex'         => $request->sex,
            'email'       => $request->email,
            'country'     => $request->country,
            'civilstatus' => $request->civilstatus,
            'contactname' => $request->contactname,
            'contactcell' => $request->contactcell,
            'status'      => 1,
            'user_id'     => Auth::id(),
        ];
    }

    private function createStaff(Request $request): Staff
    {
        $attributes = $this->buildStaffAttributes($request) + ['role_id' => $request->roleId];

        if ($request->cafeId && !$request->areaId) {
            return Cafe::findOrFail($request->cafeId)->staffs()->create($attributes);
        }

        if ($request->areaId && !$request->cafeId) {
            return Area::findOrFail($request->areaId)->staffs()->create($attributes);
        }

        return Staff::create($this->buildStaffAttributes($request));
    }

    private function syncGuardRole(Request $request, Staff $staff): void
    {
        if (!$request->cafeId && !$request->areaId) {
            return;
        }

        $guardData = $request->cafeId
            ? ['cafe_id' => $request->cafeId, 'name' => $request->guard]
            : ['name' => $request->guard];

        $guard = Guard::firstOrCreate($guardData);

        DB::table('guard_roles')->insert([
            'guard_id' => $guard->id,
            'role_id'  => $request->roleId,
            'staff_id' => $staff->id,
        ]);
    }

    private function handleStaffFiles(Request $request, Staff $staff): void
    {
        if (!$request->hasFile('files')) {
            return;
        }

        foreach ($request->file('files') as $index => $file) {
            $originalName = $file->getClientOriginalName();
            $filePath     = $file->storeAs('files', time() . '_' . $originalName, 'public');

            Staff_file::create([
                'staff_id'        => $staff->id,
                'file_type'       => explode('_', $originalName)[0],
                'file_path'       => $filePath,
                'expiration_date' => $request->filesData[$index]['expirationDate'] === '-'
                    ? null
                    : $request->filesData[$index]['expirationDate'],
            ]);
        }
    }

    private function handleStaffPhoto(Request $request, Staff $staff, bool $replacing = false): void
    {
        if (!$request->hasFile('photo')) {
            return;
        }

        $path = $request->file('photo')->store('photos', 'public');

        if ($replacing && $staff->photo) {
            Storage::disk('public')->delete($staff->photo->url);
            $staff->photo()->update(['url' => $path]);
        } else {
            $staff->photo()->create(['url' => $path]);
        }
    }

    private function buildFinancialAttributes(Request $request, bool $isUpdate = false): array
    {
        return [
            'district'            => $request->district,
            'province'            => $request->province,
            'department'          => $request->department,
            'start_date'          => $isUpdate ? $request->fechaIngreso : $request->startDate,
            'children'            => $request->children,
            'afp'                 => $request->afp,
            'onp'                 => $request->onp,
            'address'             => $request->address,
            'account_number'      => $request->cc,
            'system_work'         => $request->workSystem,
            'replacement'         => $request->replacement,
            'salary'              => $request->salary,
            'salary_type'         => $request->salaryType,
            'observations'        => $request->observations,
            'bank_entity'         => $this->nullIfEmpty($request->bankEntity),
            'pensioncontribution' => $request->pensioncontrbution,
            'cci'                 => $request->cci,
            'contract_end_date'   => $this->nullIfEmpty($request->contractEndDate),
        ];
    }

    private function syncStaffClothes(Request $request, Staff $staff): void
    {
        foreach ($request->prendas as $clothe) {
            if (empty($clothe['talla'])) {
                continue;
            }
            Staff_clothes::create([
                'staff_id'      => $staff->id,
                'clothe_name'   => $clothe['label'],
                'clothing_size' => $clothe['talla'],
                'cloth_id'      => $clothe['id'] ?? null,
            ]);
        }
    }

    private function updateStaffable(Request $request, Staff $staff): void
    {
        if ($request->cafeId && !$request->areaId) {
            $staff->staffable_id   = $request->cafeId;
            $staff->staffable_type = Cafe::class;
            $staff->save();

            $guard = Guard::firstOrCreate(['cafe_id' => $request->cafeId, 'name' => $request->guard]);
            DB::table('guard_roles')->insert([
                'guard_id' => $guard->id,
                'role_id'  => $request->roleId,
                'staff_id' => $staff->id,
            ]);
        } elseif ($request->areaId && !$request->cafeId) {
            $staff->staffable_id   = $request->areaId;
            $staff->staffable_type = Area::class;
            $staff->save();
        }
    }

    private function storeUploadedFile(Request $request): string
    {
        $originalName = $request->file->getClientOriginalName();
        return $request->file->storeAs(
            'files',
            time() . '_' . $request->fileTypeKey . '_' . $originalName,
            'public'
        );
    }

    private function nullIfEmpty(mixed $value): mixed
    {
        return empty($value) || $value === 'null' ? null : $value;
    }
}
