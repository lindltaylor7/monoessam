export const STAFF_STATUSES = [
    'Lista negra',
    'En proceso',
    'Completo - RRHH',
    'Contratado',
    'Cesado',
    'Retirado',
    'Abandono',
    'Cumplió Contrato',
] as const;

export const STAFF_STATUS_COLORS = [
    'bg-zinc-500 text-white',
    'bg-yellow-500 text-white',
    'bg-green-500 text-white',
    'bg-green-500 text-white',
    'bg-gray-500 text-white',
    'bg-red-500 text-white',
    'bg-red-500 text-white',
    'bg-blue-500 text-white',
] as const;

export const getStatusLabel = (statusId: number) => STAFF_STATUSES[statusId] ?? 'Desconocido';
export const getStatusColor = (statusId: number) => STAFF_STATUS_COLORS[statusId] ?? '';
