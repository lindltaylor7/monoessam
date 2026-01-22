import { Ref } from 'vue';
import { Staff, Unit } from '@/types';

export function useStaffInitialization(
    form: any,
    prendasFijas: Ref<any[]>,
    imagePreview: Ref<string | null>,
    cafesUnitSelected: Ref<any[]>
) {
    const initializeStaffData = (staff: Staff | undefined, units: Unit[]) => {
        if (!staff) {
            resetForm();
            return;
        }

        // Datos personales
        form.name = staff.name;
        form.dni = staff.dni;
        form.cell = staff.cell;
        form.birthdate = String(staff.birthdate);
        form.age = staff.age;
        form.sex = String(staff.sex);
        form.email = staff.email;
        form.country = staff.country;
        form.civilstatus = String(staff.civilstatus);
        form.contactname = staff.contactname;
        form.contactcell = staff.contactcell;
        form.roleId = staff.role_id;

        // Datos financieros
        if (staff.staff_financial) {
            form.district = staff.staff_financial.district;
            form.province = staff.staff_financial.province;
            form.department = staff.staff_financial.department;
            form.address = staff.staff_financial.address;
            form.workSystem = staff.staff_financial.system_work;
            form.replacement = staff.staff_financial.replacement;
            form.salary = staff.staff_financial.salary;
            form.observations = staff.staff_financial.observations;
            form.bankEntity = String(staff.staff_financial.bank_entity);
            form.cc = staff.staff_financial.account_number;
            form.cci = staff.staff_financial.cci;
            form.pensioncontribution = String(staff.staff_financial.pensioncontribution);
            form.startDate = String(staff.staff_financial.start_date);
            form.contractEndDate = String(staff.staff_financial.contract_end_date);
            form.children = staff.staff_financial.children;
        }

        // Tallas
        prendasFijas.value.forEach((prenda) => (prenda.talla = ''));
        staff.staff_clothes.forEach((clothe) => {
            const prenda = prendasFijas.value.find((p) => p.label === clothe.clothe_name);
            if (prenda) prenda.talla = clothe.clothing_size;
        });

        // Lugar de trabajo
        initializeWorkplace(staff, units);

        // Guardia
        form.guard = staff.guard_role?.guard_selected?.name;

        // Foto
        imagePreview.value = staff.photo
            ? staff.photo.url.startsWith('http')
                ? staff.photo.url
                : `/storage/${staff.photo.url}`
            : null;
    };

    const initializeWorkplace = (staff: Staff, units: Unit[]) => {
        if (staff.staffable_type === 'App\\Models\\Cafe') {
            form.workplace = '2';
            form.cafeId = staff.staffable_id;

            const cafe = staff.staffable as any;
            if (cafe?.unit) {
                form.unitId = cafe.unit.id;
                const unit = units.find((u) => u.id === cafe.unit.id);
                if (unit) cafesUnitSelected.value = unit.cafes;
            }
        } else if (staff.staffable_type === 'App\\Models\\Area') {
            form.workplace = '1';
            form.areaId = staff.staffable_id;
        } else {
            form.workplace = 0;
            form.cafeId = null;
            form.unitId = 0;
            form.areaId = 0;
        }
    };

    const resetForm = () => {
        form.reset();
        form.workplace = 0;
        imagePreview.value = null;
        prendasFijas.value.forEach((prenda) => (prenda.talla = ''));
    };

    return { initializeStaffData };
}