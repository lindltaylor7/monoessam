import { ref, ComputedRef } from 'vue';

export function useTabNavigation(isEditMode: ComputedRef<boolean>) {
    const activeTab = ref('personal');
    const tabs = ['personal', 'adjuntos', 'financiero', 'tallas'];

    const getAvailableTabs = () => {
        return isEditMode.value ? tabs.filter((tab) => tab !== 'adjuntos') : tabs;
    };

    const shouldShowTab = (tabName: string) => {
        return getAvailableTabs().includes(tabName);
    };

    const nextTab = () => {
        const availableTabs = getAvailableTabs();
        const currentIndex = availableTabs.indexOf(activeTab.value);

        if (currentIndex < availableTabs.length - 1) {
            activeTab.value = availableTabs[currentIndex + 1];
        }
    };

    const prevTab = () => {
        const availableTabs = getAvailableTabs();
        const currentIndex = availableTabs.indexOf(activeTab.value);

        if (currentIndex > 0) {
            activeTab.value = availableTabs[currentIndex - 1];
        }
    };

    const resetTab = () => {
        activeTab.value = 'personal';
    };

    return { activeTab, nextTab, prevTab, resetTab, shouldShowTab };
}