import { ref } from 'vue';

export function useImageUpload() {
    const fileInput = ref<HTMLInputElement | null>(null);
    const imagePreview = ref<string | null>(null);
    const selectedFile = ref<File | null>(null);

    const triggerFileInput = () => {
        fileInput.value?.click();
    };

    const handleImageUpload = (event: Event) => {
        const target = event.target as HTMLInputElement;
        const file = target.files?.[0];

        if (file) {
            if (!file.type.startsWith('image/')) {
                alert('Por favor, selecciona solo archivos de imagen');
                return;
            }

            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('La imagen es demasiado grande. Máximo 5MB');
                return;
            }

            selectedFile.value = file;

            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.value = e.target?.result as string;
            };
            reader.readAsDataURL(file);
        }
    };

    const removeImage = () => {
        imagePreview.value = null;
        selectedFile.value = null;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    };

    return {
        fileInput,
        imagePreview,
        selectedFile,
        triggerFileInput,
        handleImageUpload,
        removeImage,
        selectedFile,
    };
}
