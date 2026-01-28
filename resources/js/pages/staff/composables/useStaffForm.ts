import { Unit } from '@/types';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

export function useStaffForm() {
    const form = useForm({
        name: '',
        dni: '',
        cell: '',
        birthdate: '',
        age: 0,
        sex: 0 as number | string,
        email: '',
        country: '',
        civilstatus: 0 as number | string,
        contactname: '',
        contactcell: '',
        cafeId: null as number | null,
        photo: null as any,
        tipoContrato: '',
        regimenLaboral: '',
        children: 0,
        prendas: [] as any[],
        district: '',
        province: '',
        department: '',
        afp: '',
        onp: '',
        position: '',
        address: '',
        workSystem: '',
        replacement: '',
        unitId: 0,
        salary: 0.0,
        observations: '',
        bankEntity: 0 as number | string,
        pensioncontribution: '',
        cc: '',
        cci: '',
        startDate: '',
        contractEndDate: '',
        fondo: 0,
        roleId: null as number | null,
        unitSelectedText: '',
        filesData: [] as any[],
        files: [] as any[],
        areaId: 0,
        workplace: '' as string | number,
        guard: '',
    });

    const errorsSend = ref<any>([]);
    const showErrors = ref(false);

    const prendasFijas = ref<Array<{ label: string; talla: string }>>([]);

    const cafesUnitSelected = ref<any[]>([]);

    const handleSubmit = (onSuccess: () => void, filesReq: any) => {
        // Convertir a array primero (maneja Proxy, Ref, y arrays normales)
        let filesArray;

        if (Array.isArray(filesReq)) {
            filesArray = filesReq;
        } else if (filesReq && typeof filesReq === 'object') {
            // Si es un Proxy o tiene una propiedad value (Ref)
            filesArray = Array.isArray(filesReq.value) ? filesReq.value : Object.values(filesReq);
        } else {
            filesArray = [];
        }

        filesArray.forEach((newFile: any) => {
            if (newFile && newFile.expirationDate === null) {
                form.filesData.push({ expirationDate: '-' });
            } else {
                form.filesData.push({ expirationDate: newFile.expirationDate });
            }
        });

        console.log('filesArray:', filesArray);

        // Extraer solo los objetos File del array
        const validFiles = filesArray
            .filter((item: any) => item && typeof item === 'object')
            .map((item: any) => item.file)
            .filter((file: any) => file instanceof File);

        console.log('Archivos válidos a subir:', validFiles.length, validFiles);

        /* if (validFiles.length === 0) {
            console.error('No hay archivos válidos para subir');
            showErrors.value = true;
            errorsSend.value = { files: ['No hay archivos seleccionados'] };
            return;
        } */

        form.prendas = prendasFijas.value;
        form.files = validFiles;

        form.post(route('staff.store'), {
            forceFormData: true,
            preserveState: true,
            onSuccess: (res) => {
                form.reset();
                onSuccess();
            },
            onError: (errors) => {
                showErrors.value = true;
                errorsSend.value = errors;
                console.error('Error al subir el archivo:', errors);
            },
        });
    };

    const updateStaff = (onSuccess: () => void, staffId: number) => {
        console.log(form);
        form.prendas = prendasFijas.value;
        form.transform((data) => ({
            ...data,
            _method: 'PUT',
        })).post(route('staff.update', staffId), {
            forceFormData: true,
            onSuccess: (res) => {
                //form.reset();
                onSuccess();
            },
            onError: (errors) => {
                showErrors.value = true;
                errorsSend.value = errors;
                console.error('Error al subir el archivo:', errors);
            },
        });
    };

    const selectCafe = (cafe: any) => {
        form.cafeId = cafe.id;
    };

    const selectUnit = (unit: Unit) => {
        form.unitId = unit.id;
        cafesUnitSelected.value = unit.cafes;
        form.unitSelectedText = unit.name;
    };

    const selectRole = (role: any) => {
        form.roleId = role.id;
    };

    const selectArea = (areaId: number) => {
        form.areaId = areaId;
    };

    const selectGuard = (guard: string | number) => {
        form.guard = String(guard);
    };

    return {
        form,
        errorsSend,
        showErrors,
        prendasFijas,
        cafesUnitSelected,
        handleSubmit,
        updateStaff,
        selectCafe,
        selectRole,
        selectUnit,
        selectArea,
        selectGuard,
    };
}
