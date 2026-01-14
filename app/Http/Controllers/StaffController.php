<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Business;
use App\Models\Cafe;
use App\Models\Observation;
use App\Models\Staff;
use App\Models\Staff_clothes;
use App\Models\Staff_file;
use App\Models\Staff_financial;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('staff/Index', [
            'cafes' => Cafe::with('unit')->get(),
            'staff' => Staff::with([
                'photo',
                'staff_files',
                'staff_financial',
                'staff_clothes',
                'observations' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                },
                'observations.user',
                'staffable' => function ($query) {
                    $query->morphWith([
                        Cafe::class => ['unit'], // Solo cargará 'unit' si el modelo es Cafe
                        Area::class => ['headquarters', 'headquarters.business'],       // No carga nada extra si es Area
                    ]);
                }
            ])->get(),
            'roles' => Role::all(),
            'units' => Unit::with('cafes')->get(),
            'businneses' => Business::with(['headquarters', 'headquarters.areas'])->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $validatedData = $request->validate([
            'name' => 'required',
            'dni' => 'required|unique:staff|max:8',
            'cell' => 'required'
        ]);

        if ($request->cafeId && !$request->areaId) {
            $cafe = Cafe::find($request->cafeId);

            $staff = $cafe->staffs()->create([
                'name' => $request->name,
                'dni' => $request->dni,
                'cell' => $request->cell,
                'birthdate' => $request->birthdate,
                'age' => $request->age,
                'sex' => $request->sex,
                'email' => $request->email,
                'country' => $request->country,
                'civilstatus' => $request->civilstatus,
                'contactname' => $request->contactname,
                'contactcell' => $request->contactcell,
                'status' => 1,
                'user_id' => Auth::id()
            ]);
        } else if ($request->areaId  && !$request->cafeId) {
            $area = Area::find($request->areaId);

            $staff = $area->staffs()->create([
                'name' => $request->name,
                'dni' => $request->dni,
                'cell' => $request->cell,
                'birthdate' => $request->birthdate,
                'age' => $request->age,
                'sex' => $request->sex,
                'email' => $request->email,
                'country' => $request->country,
                'civilstatus' => $request->civilstatus,
                'contactname' => $request->contactname,
                'contactcell' => $request->contactcell,
                'status' => 1,
                'user_id' => Auth::id()
            ]);
        } else {
            $staff = Staff::create([
                'name' => $request->name,
                'dni' => $request->dni,
                'cell' => $request->cell,
                'birthdate' => $request->birthdate,
                'age' => $request->age,
                'sex' => $request->sex,
                'email' => $request->email,
                'country' => $request->country,
                'civilstatus' => $request->civilstatus,
                'contactname' => $request->contactname,
                'contactcell' => $request->contactcell,
                'status' => 1,
                'user_id' => Auth::id()
            ]);
        }


        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $index => $file) {
                $originalName = $file->getClientOriginalName();
                $chunksName = explode("_", $originalName);

                $fileName = time() . '_' . $originalName;
                $filePath = $file->storeAs('files', $fileName, 'public');

                $label = 'default'; // Default or extract from filename

                $staff_file = Staff_file::create([
                    'staff_id' => $staff->id,
                    'file_type' => $chunksName[0], // You need to determine this differently
                    'file_path' => $filePath,
                    'expiration_date' => $request->filesData[$index]['expirationDate'] == '-' ? null : $request->filesData[$index]['expirationDate']
                ]);
            }
        }

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos', 'public');
            $staff->photo()->create([
                'url' => $path
            ]);
        }

        $staff_financial = Staff_financial::create([
            'staff_id' => $staff->id,
            'district' => $request->district,
            'province' => $request->province,
            'department' => $request->department,
            'start_date' => $request->startDate,
            'children' => $request->children,
            'afp' => $request->afp,
            'onp' => $request->onp,
            'address' => $request->address,
            'account_number' => $request->account_number,
            'system_work' => $request->workSystem,
            'replacement' => $request->replacement,
            'salary' => $request->salary,
            'observations' => $request->observations,
            'account_number' => $request->cc,
            'bank_entity' => $request->bankEntity,
            'pensioncontribution' => $request->pensioncontrbution,
            'cci' => $request->cci,
            'contract_end_date' => $request->contractEndDate
        ]);


        foreach ($request->prendas as $clothe) {
            if ($clothe['talla']) {
                $staff_clothes = Staff_clothes::create([
                    'staff_id' => $staff->id,
                    'clothe_name' => $clothe['label'],
                    'clothing_size' => $clothe['talla']
                ]);
            }
        }

        return redirect()->route('staff.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {

        $staff = Staff::with(['staff_financial', 'staff_clothes', 'photo'])->find($id);

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('photos', 'public');
            if ($staff->photo) {
                Storage::disk('public')->delete($staff->photo->url);
                $staff->photo()->update([
                    'url' => $path
                ]);
            } else {
                $staff->photo()->create([
                    'url' => $path
                ]);
            }
        }

        $staff->update([
            'name' => $request->name,
            'dni' => $request->dni,
            'cell' => $request->cell,
            'birthdate' =>
            empty($request->birthdate) || $request->birthdate === 'null'
                ? null
                : $request->birthdate,
            'age' => $request->age,
            'sex' => $request->sex,
            'email' => $request->email,
            'country' => $request->country,
            'civilstatus' => $request->civilstatus,
            'contactname' => $request->contactname,
            'contactcell' => $request->contactcell,
            'role_id' => $request->roleId,
            'user_id' => Auth::id()
        ]);

        if ($request->workplace == 1 && $request->areaId) {
            $staff->staffable_id = $request->areaId;
            $staff->staffable_type = Area::class;
            $staff->save();
        } else if ($request->workplace == 2 && $request->cafeId) {
            $staff->staffable_id = $request->cafeId;
            $staff->staffable_type = Cafe::class;
            $staff->save();
        }

        $staff->staff_financial->update([
            'district' => $request->district,
            'province' => $request->province,
            'department' => $request->department,
            'start_date' => $request->fechaIngreso,
            'children' => $request->children,
            'afp' => $request->afp,
            'onp' => $request->onp,
            'address' => $request->address,
            'account_number' => $request->account_number,
            'system_work' => $request->workSystem,
            'replacement' => $request->replacement,
            'salary' => $request->salary,
            'observations' => $request->observations,
            'account_number' => $request->cc,
            'bank_entity' => empty($request->bankEntity) || $request->bankEntity === 'null'
                ? null
                : $request->bankEntity,
            'pensioncontribution' => $request->pensioncontrbution,
            'cci' => $request->cci,
            'contract_end_date' => empty($request->contractEndDate) || $request->contractEndDate === 'null'
                ? null
                : $request->contractEndDate
        ]);

        $staff->staff_clothes->each->delete();

        foreach ($request->prendas as $clothe) {
            if ($clothe['talla']) {
                $staff_clothes = Staff_clothes::create([
                    'staff_id' => $staff->id,
                    'clothe_name' => $clothe['label'],
                    'clothing_size' => $clothe['talla']
                ]);
            }
        }

        return redirect()->route('staff.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $staff = Staff::find($id);
        $staff_files = Staff_file::where('staff_id', $id)->get();
        if ($staff->photo) {
            Storage::disk('public')->delete($staff->photo->url);
            $staff->photo()->delete();
        }
        foreach ($staff_files as $file) {
            Storage::disk('public')->delete($file->file_path);
        }
        $staff_files->each->delete();
        $staff->delete();
    }

    public function banStaff(string $id)
    {
        $staff_files = Staff_file::where('staff_id', $id)->get();
        $staff_financial = Staff_financial::where('staff_id', $id)->first();
        $staff_clothes = Staff_clothes::where('staff_id', $id)->get();

        foreach ($staff_files as $file) {
            Storage::disk('public')->delete($file->file_path);
        }

        $staff_files->each->delete();

        if ($staff_financial) {
            $staff_financial->delete();
        }

        $staff_clothes->each->delete();

        $staff = Staff::find($id);
        $staff->update([
            'status' => 0
        ]);

        return response()->json([
            'cafes' => Cafe::with('unit')->get(),
            Staff::with(['staff_files', 'observations', 'observations.user'])->where('status', '!=', 0)->get(),
            'roles' => Role::all()
        ]);
    }

    public function updateStatusStaff(Request $request)
    {
        $staff = Staff::find($request->staff_id);
        $staff->update([
            'status' => $request->status
        ]);

        if ($request->observation != '') {
            $observation = Observation::create([
                'staff_id' => $request->staff_id,
                'user_id' => $request->user_id,
                'observation' => $request->observation,
                'date' => date('Y-m-d')
            ]);
        }
    }

    public function uploadFile(Request $request)
    {

        if ($request->fileId == 0) {

            if ($request->hasFile('file')) {
                $originalName = $request->file->getClientOriginalName();
                $chunksName = explode("_", $originalName);

                $fileName = time() . '_' . $request->fileTypeKey . '_' . $originalName;
                $filePath = $request->file->storeAs('files', $fileName, 'public');

                $label = 'default'; // Default or extract from filename

                $staff_file = Staff_file::create([
                    'staff_id' => $request->staffId,
                    'file_type' => $request->fileTypeKey, // You need to determine this differently
                    'file_path' => $filePath,
                    'expiration_date' => $request->expirationDate
                ]);
            }
        } else {
            $staff_file = Staff_file::find($request->fileId);

            Storage::disk('public')->delete($staff_file->file_path);

            if ($request->hasFile('file')) {
                $originalName = $request->file->getClientOriginalName();
                $chunksName = explode("_", $originalName);

                $fileName = time() . '_' . $request->fileTypeKey . '_' . $originalName;
                $filePath = $request->file->storeAs('files', $fileName, 'public');

                $label = 'default'; // Default or extract from filename

                $staff_file->update([
                    'file_type' => $request->fileTypeKey, // You need to determine this differently
                    'file_path' => $filePath,
                    'expiration_date' => $request->expirationDate
                ]);
            }
        }
    }

    public function uploadFileDate(Request $request)
    {
        $staff_file = Staff_file::find($request->fileId);

        $staff_file->update([
            'expiration_date' => $request->expirationDate
        ]);
    }

    public function deleteFile($id)
    {
        $staff_file = Staff_file::find($id);
        Storage::disk('public')->delete($staff_file->file_path);
        $staff_file->delete();
    }
}
