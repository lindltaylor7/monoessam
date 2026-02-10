import { ref } from 'vue';

const MAX_FILE_SIZE = 15 * 1024 * 1024;

export function useFileUpload() {
    const showAlert = ref(false);
    const alertMessage = ref('');

    const filesRequired = ref([
        { label: 'CV Documentado', file: {} },
        { label: 'Certificado Único Laboral (CUL)', file: {}, expirationDate: null },
        { label: 'Certificado de Estudios', file: {} },
        { label: 'Certificados de Trabajo', file: {} },
        { label: 'DNI escaneado', file: {}, expirationDate: null },
        { label: 'Antecedentes Penales y Policiales', file: {}, expirationDate: null },
        { label: 'Carné de sanidad', file: {}, expirationDate: null },
        { label: 'Carné de vacunación contra el COVID', file: {} },
        { label: 'Examen Medico Ocupacional (EMO)', file: {}, expirationDate: null },
        { label: 'SCTR', file: {}, expirationDate: null },
        { label: 'Contrato', file: {}, expirationDate: null },
    ]);

    const handleFileUpload = (event: any, fileLabel: string, formFiles: any[]) => {
        const files = event.target.files;
        if (files.length > 0) {
            const newFile = files[0];
            if (newFile.size > MAX_FILE_SIZE) {
                alertMessage.value = 'El archivo es demasiado grande. El máximo es 9MB.';
                showAlert.value = true;
            } else {
                const registerFileFound = filesRequired.value.find((file) => file.label == fileLabel);
                if (registerFileFound) {
                    const originalName = newFile.name;
                    const extension = originalName.split('.').pop();
                    const modifiedFile = new File([newFile], `${fileLabel}_${Date.now()}.${extension}`, { type: newFile.type });
                    formFiles.push({
                        file: modifiedFile,
                        label: fileLabel,
                    });
                    registerFileFound.file = modifiedFile;
                }
            }
        }
    };

    const handleDateFile = (dateString: string, index: number) => {
        filesRequired.value[index].expirationDate = dateString;
    };

    return {
        filesRequired,
        showAlert,
        alertMessage,
        handleFileUpload,
        handleDateFile,
    };
}
