<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, onUnmounted, ref, watch } from "vue";

/* ====================== Props ====================== */
interface Props {
    carouselImages: any[];
    homeCards: any[];
    cardCategories: string[];
    virtualTourCategories: any[];
    events: any[];
    eventTypes: any[];
}

const props = defineProps<Props>();

/* ====================== Variables ====================== */
const page = usePage<any>();
const fileInput = ref<HTMLInputElement | null>(null);
const cardFileInput = ref<HTMLInputElement | null>(null);
const virtualTourFileInput = ref<HTMLInputElement | null>(null);
const activeSection = ref("carousel");
const showCategoryForm = ref(false);
const isDragging = ref(false);
const previews = ref<string[]>([]);
const cardPreviews = ref<string[]>([]);
const virtualTourPreviews = ref<string[]>([]);
const defaultVirtualTourCategories = [
    "Interior",
    "Exterior",
    "Servicios",
    "Actividad física",
    "Estacionamiento",
];

/* ====================== useForm ====================== */
const form = useForm<{ images: File[]; descriptions: string[] }>({
    images: [],
    descriptions: [],
});
const cardForm = useForm<{ category: string; images: File[] }>({
    category: props.cardCategories[0] ?? "Gimnasio",
    images: [],
});
const categoryForm = useForm<{ name: string }>({
    name: "",
});
const virtualTourForm = useForm<{ category_id: number | null; images: File[]; titles: string[] }>({
    category_id: props.virtualTourCategories[0]?.id ?? null,
    images: [],
    titles: [],
});
const eventForm = useForm<{ id: number | null; title: string; event_date: string; type: string }>({
    id: null,
    title: "",
    event_date: "",
    type: "activity",
});

/* ====================== Computed ====================== */
const can = computed<string[]>(() => page.props.auth.permissions ?? []);
const selectedCount = computed(() => form.images.length);
const selectedCategoryCount = computed(() => {
    return props.homeCards.filter((card) => card.category === cardForm.category).length;
});
const availableCardSpaces = computed(() => 2 - selectedCategoryCount.value);
const cardSelectionSpaces = computed(() => availableCardSpaces.value - cardForm.images.length);
const selectedVirtualTourCategory = computed(() => {
    return props.virtualTourCategories.find((category) => category.id === virtualTourForm.category_id);
});
const availableVirtualTourSpaces = computed(() => {
    const savedImages = selectedVirtualTourCategory.value?.images.length ?? 0;
    return 6 - savedImages - virtualTourForm.images.length;
});

/* ====================== Funciones ====================== */
const openFilePicker = () => {
    fileInput.value?.click();
};

const addFiles = (files: File[]) => {
    const imageFiles = files.filter((file) => file.type.startsWith("image/"));
    const available = 20 - form.images.length;

    if (imageFiles.length > available) {
        customToastSwal({
            title: "Puedes seleccionar hasta 20 imágenes por carga",
            icon: "warning",
        });
    }

    form.images = [...form.images, ...imageFiles.slice(0, available)];
};

const selectFiles = (event: Event) => {
    const input = event.target as HTMLInputElement;
    addFiles(Array.from(input.files ?? []));
    input.value = "";
};

const dropFiles = (event: DragEvent) => {
    isDragging.value = false;
    addFiles(Array.from(event.dataTransfer?.files ?? []));
};

const removeSelected = (index: number) => {
    form.images = form.images.filter((_, imageIndex) => imageIndex !== index);
    form.descriptions = form.descriptions.filter((_, descriptionIndex) => descriptionIndex !== index);
};

const clearSelection = () => {
    form.reset();
    form.clearErrors();
};

const save = () => {
    form.post(route("website-content.store"), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "Imágenes guardadas correctamente",
                icon: "success",
            });
            clearSelection();
        },
        onError: (errors) => {
            customToastSwal({
                title: Object.values(errors)[0] || "No se pudieron guardar las imágenes",
                icon: "error",
            });
        },
    });
};

const destroy = (image: any) => {
    customConfirmSwal({
        title: "¿Eliminar esta imagen del carrusel?",
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route("website-content.destroy", image.id), {
                preserveScroll: true,
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "Imagen eliminada correctamente",
                        icon: "success",
                    });
                },
            });
        }
    });
};

const openCardFilePicker = () => {
    if (!cardForm.category) {
        customToastSwal({
            title: "Primero selecciona una categoría",
            icon: "warning",
        });
        return;
    }

    if (!cardSelectionSpaces.value) {
        customToastSwal({
            title: "Esta categoría ya tiene sus 2 imágenes",
            icon: "warning",
        });
        return;
    }

    cardFileInput.value?.click();
};

const selectCardFiles = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []).filter((file) => file.type.startsWith("image/"));

    if (files.length > cardSelectionSpaces.value) {
        customToastSwal({
            title: `Solo quedan ${cardSelectionSpaces.value} espacio(s) en esta categoría`,
            icon: "warning",
        });
    }

    cardForm.images = [...cardForm.images, ...files.slice(0, cardSelectionSpaces.value)];
    input.value = "";
};

const removeSelectedCard = (index: number) => {
    cardForm.images = cardForm.images.filter((_, imageIndex) => imageIndex !== index);
};

const saveCards = () => {
    cardForm.post(route("website-content.cards.store"), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "Cards guardadas correctamente",
                icon: "success",
            });
            cardForm.images = [];
            cardForm.clearErrors();
        },
        onError: (errors) => {
            customToastSwal({
                title: Object.values(errors)[0] || "No se pudieron guardar las cards",
                icon: "error",
            });
        },
    });
};

const destroyCard = (card: any) => {
    customConfirmSwal({
        title: "¿Eliminar esta card de inicio?",
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route("website-content.cards.destroy", card.id), {
                preserveScroll: true,
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "Card eliminada correctamente",
                        icon: "success",
                    });
                },
            });
        }
    });
};

const createVirtualTourCategory = () => {
    categoryForm.post(route("website-content.virtual-tour.categories.store"), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "Categoría guardada correctamente",
                icon: "success",
            });
            categoryForm.reset();
            showCategoryForm.value = false;
        },
        onError: (errors) => {
            customToastSwal({
                title: Object.values(errors)[0] || "No se pudo guardar la categoría",
                icon: "error",
            });
        },
    });
};

const openVirtualTourFilePicker = () => {
    virtualTourFileInput.value?.click();
};

const getVirtualTourIcon = (category: string) => {
    const icons: Record<string, string> = {
        Interior: "mdi-sofa-outline",
        Exterior: "mdi-tree-outline",
        Servicios: "mdi-coffee-outline",
        "Actividad física": "mdi-basketball",
        Estacionamiento: "mdi-car-outline",
    };

    return icons[category] || "mdi-image-multiple-outline";
};

const selectVirtualTourFiles = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []).filter((file) => file.type.startsWith("image/"));
    const available = availableVirtualTourSpaces.value;

    if (files.length > available) {
        customToastSwal({
            title: `Solo quedan ${available} espacio(s) en esta categoría`,
            icon: "warning",
        });
    }

    virtualTourForm.images = [...virtualTourForm.images, ...files.slice(0, available)];
    input.value = "";
};

const removeVirtualTourSelected = (index: number) => {
    virtualTourForm.images = virtualTourForm.images.filter((_, imageIndex) => imageIndex !== index);
    virtualTourForm.titles = virtualTourForm.titles.filter((_, titleIndex) => titleIndex !== index);
};

const saveVirtualTourImages = () => {
    virtualTourForm.post(route("website-content.virtual-tour.images.store"), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "Imágenes guardadas correctamente",
                icon: "success",
            });
            virtualTourForm.images = [];
            virtualTourForm.titles = [];
            virtualTourForm.clearErrors();
        },
        onError: (errors) => {
            customToastSwal({
                title: Object.values(errors)[0] || "No se pudieron guardar las imágenes",
                icon: "error",
            });
        },
    });
};

const destroyVirtualTourImage = (image: any) => {
    customConfirmSwal({
        title: "¿Eliminar esta imagen de la vista virtual?",
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route("website-content.virtual-tour.images.destroy", image.id), {
                preserveScroll: true,
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "Imagen eliminada correctamente",
                        icon: "success",
                    });
                },
            });
        }
    });
};

const destroyVirtualTourCategory = (category: any) => {
    customConfirmSwal({
        title: `¿Eliminar la categoría ${category.name} y todas sus imágenes?`,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route("website-content.virtual-tour.categories.destroy", category.id), {
                preserveScroll: true,
                onSuccess: () => {
                    virtualTourForm.category_id = props.virtualTourCategories[0]?.id ?? null;
                    customToastSwal({
                        title: page.props.flash.success || "Categoría eliminada correctamente",
                        icon: "success",
                    });
                },
            });
        }
    });
};

const getEventType = (type: string) => {
    return props.eventTypes.find((item) => item.value === type);
};

const resetEventForm = () => {
    eventForm.reset();
    eventForm.clearErrors();
};

const editEvent = (event: any) => {
    eventForm.id = event.id;
    eventForm.title = event.title;
    eventForm.event_date = event.event_date;
    eventForm.type = event.type;
};

const saveEvent = () => {
    eventForm.post(route("website-content.events.save"), {
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "Evento guardado correctamente",
                icon: "success",
            });
            resetEventForm();
        },
        onError: (errors) => {
            customToastSwal({
                title: Object.values(errors)[0] || "No se pudo guardar el evento",
                icon: "error",
            });
        },
    });
};

const destroyEvent = (event: any) => {
    customConfirmSwal({
        title: `¿Eliminar el evento ${event.title}?`,
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route("website-content.events.destroy", event.id), {
                preserveScroll: true,
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "Evento eliminado correctamente",
                        icon: "success",
                    });
                },
            });
        }
    });
};

const clearPreviews = () => {
    previews.value.forEach((preview) => URL.revokeObjectURL(preview));
};

/* ====================== Watchers ====================== */
watch(
    () => form.images,
    (images) => {
        clearPreviews();
        previews.value = images.map((image) => URL.createObjectURL(image));
        form.descriptions = images.map((_, index) => form.descriptions[index] || "");
    },
);

watch(
    () => cardForm.images,
    (images) => {
        cardPreviews.value.forEach((preview) => URL.revokeObjectURL(preview));
        cardPreviews.value = images.map((image) => URL.createObjectURL(image));
    },
);

watch(
    () => cardForm.category,
    () => {
        cardForm.images = [];
        cardForm.clearErrors();
    },
);

watch(
    () => virtualTourForm.images,
    (images) => {
        virtualTourPreviews.value.forEach((preview) => URL.revokeObjectURL(preview));
        virtualTourPreviews.value = images.map((image) => URL.createObjectURL(image));
        virtualTourForm.titles = images.map((_, index) => virtualTourForm.titles[index] || "");
    },
);

watch(
    () => virtualTourForm.category_id,
    () => {
        virtualTourForm.images = [];
        virtualTourForm.titles = [];
        virtualTourForm.clearErrors();
    },
);

/* ====================== Lifecycle ====================== */
onUnmounted(() => {
    clearPreviews();
    cardPreviews.value.forEach((preview) => URL.revokeObjectURL(preview));
    virtualTourPreviews.value.forEach((preview) => URL.revokeObjectURL(preview));
});
</script>

<template>
    <Head title="Página web" />

    <AppLayout>
        <template #header>Página web</template>

        <v-card class="mb-4">
            <v-tabs v-model="activeSection" color="primary" grow>
                <v-tab value="carousel" prepend-icon="mdi-view-carousel-outline">
                    Carrusel
                </v-tab>
                <v-tab value="cards" prepend-icon="mdi-view-grid-outline">
                    Cards de inicio
                </v-tab>
                <v-tab value="virtual-tour" prepend-icon="mdi-panorama-variant-outline">
                    Vista virtual
                </v-tab>
                <v-tab value="events" prepend-icon="mdi-calendar-month-outline">
                    Eventos
                </v-tab>
            </v-tabs>
        </v-card>

        <v-card v-show="activeSection === 'carousel'">
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-view-carousel-outline" />
                Carrusel de inicio
            </v-card-title>
            <v-card-subtitle>
                Se recomiendan de 3 a 5 imágenes, pero puedes agregar las que necesites.
            </v-card-subtitle>

            <v-card-text>
                <div class="mb-4 d-flex align-center ga-2">
                    <div class="text-h6">Imágenes guardadas</div>
                    <v-chip size="small" color="primary" variant="tonal">
                        {{ carouselImages.length }}
                    </v-chip>
                    <v-chip v-if="selectedCount" size="small" color="warning" variant="tonal">
                        {{ selectedCount }} pendiente(s)
                    </v-chip>
                </div>

                <input
                    ref="fileInput"
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    class="d-none"
                    @change="selectFiles"
                />

                <v-row>
                    <v-col
                        v-for="image in carouselImages"
                        :key="image.id"
                        cols="12"
                        sm="6"
                        lg="4"
                    >
                        <v-card variant="outlined" class="h-100 position-relative">
                            <v-img :src="image.image_url" aspect-ratio="1.5" cover />
                            <v-card-text class="text-subtitle-1">
                                {{ image.description || "Sin descripción" }}
                            </v-card-text>
                            <div
                                v-if="can.includes('website-content.destroy')"
                                class="saved-delete-button"
                            >
                                <BaseButton action="delete" @click="destroy(image)" />
                            </div>
                        </v-card>
                    </v-col>

                    <v-col
                        v-for="(image, index) in form.images"
                        :key="`${image.name}-${image.lastModified}`"
                        cols="12"
                        sm="6"
                        lg="4"
                    >
                        <v-card variant="outlined" class="h-100 position-relative">
                            <v-img :src="previews[index]" aspect-ratio="1.5" cover />
                            <v-btn
                                class="remove-image-button"
                                icon="mdi-close"
                                size="x-small"
                                color="error"
                                @click="removeSelected(index)"
                            />
                            <v-card-text>
                                <v-text-field
                                    v-model="form.descriptions[index]"
                                    label="Descripción corta"
                                    placeholder="Ej. Natación"
                                    maxlength="100"
                                    counter="100"
                                    density="compact"
                                    hide-details="auto"
                                    :error-messages="(form.errors as any)[`descriptions.${index}`]"
                                />
                            </v-card-text>
                        </v-card>
                    </v-col>

                    <v-col
                        v-if="can.includes('website-content.store') && selectedCount < 20"
                        cols="12"
                        sm="6"
                        lg="4"
                    >
                        <div
                            class="upload-zone carousel-upload-zone"
                            :class="{ 'upload-zone--active': isDragging }"
                            role="button"
                            tabindex="0"
                            @click="openFilePicker"
                            @keydown.enter="openFilePicker"
                            @dragenter.prevent="isDragging = true"
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="dropFiles"
                        >
                            <v-icon icon="mdi-image-plus-outline" size="46" color="primary" />
                            <div class="text-subtitle-1 font-weight-bold mt-2">Agregar imágenes</div>
                            <div class="text-caption text-medium-emphasis mt-1">
                                1200 × 800 px · máximo 20 MB
                            </div>
                        </div>
                    </v-col>
                </v-row>

                <v-alert v-if="form.errors.images" type="error" variant="tonal" class="mt-4">
                    {{ form.errors.images }}
                </v-alert>

                <div v-if="selectedCount" class="d-flex align-center justify-end ga-2 mt-4">
                    <v-btn variant="text" color="error" @click="clearSelection">
                        Limpiar selección
                    </v-btn>
                    <v-btn
                        color="primary"
                        prepend-icon="mdi-upload"
                        :loading="form.processing"
                        @click="save"
                    >
                        Guardar {{ selectedCount }} imagen(es)
                    </v-btn>
                </div>
            </v-card-text>
        </v-card>

        <v-card v-show="activeSection === 'cards'">
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-view-grid-outline" />
                Cards de inicio
            </v-card-title>
            <v-card-subtitle>
                Gimnasio, Alberca, Tenis, Jardines y Cafetería. Máximo 2 imágenes por categoría.
            </v-card-subtitle>

            <v-card-text>
                <v-tabs v-model="cardForm.category" color="primary" show-arrows class="mb-5">
                    <v-tab v-for="category in cardCategories" :key="category" :value="category">
                        {{ category }}
                        <v-chip size="x-small" class="ml-2">
                            {{ homeCards.filter((card) => card.category === category).length }}/2
                        </v-chip>
                    </v-tab>
                </v-tabs>

                <input
                    ref="cardFileInput"
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    class="d-none"
                    @change="selectCardFiles"
                />

                <v-row>
                    <v-col
                        v-for="card in homeCards.filter((item) => item.category === cardForm.category)"
                        :key="card.id"
                        cols="12"
                        sm="6"
                        md="4"
                    >
                        <v-card variant="outlined" class="position-relative">
                            <v-img :src="card.image_url" aspect-ratio="1" cover />
                            <div
                                v-if="can.includes('website-content.destroy')"
                                class="saved-delete-button"
                            >
                                <BaseButton
                                    action="delete"
                                    @click="destroyCard(card)"
                                    color="error"
                                    tooltip="Eliminar"
                                />
                            </div>
                        </v-card>
                    </v-col>

                    <v-col
                        v-for="(image, index) in cardForm.images"
                        :key="`${image.name}-${image.lastModified}`"
                        cols="12"
                        sm="6"
                        md="4"
                    >
                        <v-card variant="outlined" class="position-relative">
                            <v-img :src="cardPreviews[index]" aspect-ratio="1" cover />
                            <div class="pending-image-label">Pendiente por subir</div>
                            <v-btn
                                class="remove-image-button"
                                icon="mdi-close"
                                size="x-small"
                                color="error"
                                @click="removeSelectedCard(index)"
                            />
                        </v-card>
                    </v-col>

                    <v-col v-if="can.includes('website-content.store') && cardSelectionSpaces > 0" cols="12" sm="6" md="4">
                        <div
                            class="upload-zone card-upload-zone"
                            role="button"
                            tabindex="0"
                            @click="openCardFilePicker"
                            @keydown.enter="openCardFilePicker"
                        >
                            <v-icon icon="mdi-image-plus-outline" size="46" color="primary" />
                            <div class="mt-2 text-subtitle-1 font-weight-bold">Agregar imagen</div>
                            <div class="mt-1 text-caption text-medium-emphasis">
                                1000 × 1000 px · máximo 20 MB
                            </div>
                        </div>
                    </v-col>
                </v-row>

                <v-alert v-if="cardForm.errors.images" type="error" variant="tonal" class="mt-4">
                    {{ cardForm.errors.images }}
                </v-alert>

                <div v-if="cardForm.images.length" class="justify-end mt-4 d-flex">
                    <v-btn
                        color="primary"
                        prepend-icon="mdi-upload"
                        :loading="cardForm.processing"
                        @click="saveCards"
                    >
                        Guardar {{ cardForm.images.length }} imagen(es) en {{ cardForm.category }}
                    </v-btn>
                </div>
            </v-card-text>
        </v-card>

        <v-card v-show="activeSection === 'virtual-tour'">
            <v-card-title class="flex-wrap d-flex align-center ga-2">
                <v-icon icon="mdi-panorama-variant-outline" />
                Vista virtual de instalaciones
                <v-spacer />
                <v-btn
                    v-if="can.includes('website-content.store')"
                    variant="outlined"
                    color="primary"
                    prepend-icon="mdi-folder-plus-outline"
                    @click="showCategoryForm = !showCategoryForm"
                >
                    Nueva categoría
                </v-btn>
            </v-card-title>
            <v-card-subtitle>
                Organiza las instalaciones por categoría y agrega un título a cada imagen.
            </v-card-subtitle>

            <v-card-text>
                <v-expand-transition>
                    <v-card v-if="showCategoryForm" variant="tonal" class="mb-5">
                        <v-card-text class="flex-wrap d-flex align-start ga-3">
                            <v-text-field
                                v-model="categoryForm.name"
                                label="Nombre de la categoría"
                                placeholder="Ej. Salones"
                                maxlength="60"
                                hide-details="auto"
                                :error-messages="categoryForm.errors.name"
                                class="category-name-field"
                                @keydown.enter="createVirtualTourCategory"
                            />
                            <v-btn
                                color="primary"
                                prepend-icon="mdi-content-save-outline"
                                :loading="categoryForm.processing"
                                @click="createVirtualTourCategory"
                            >
                                Guardar categoría
                            </v-btn>
                        </v-card-text>
                    </v-card>
                </v-expand-transition>

                <v-tabs
                    v-model="virtualTourForm.category_id"
                    color="primary"
                    show-arrows
                    class="mb-5"
                >
                    <v-tab
                        v-for="category in virtualTourCategories"
                        :key="category.id"
                        :value="category.id"
                        :prepend-icon="getVirtualTourIcon(category.name)"
                    >
                        {{ category.name }}
                        <v-chip size="x-small" class="ml-2">{{ category.images.length }}/6</v-chip>
                    </v-tab>
                </v-tabs>

                <div v-if="selectedVirtualTourCategory">
                    <div class="mb-4 d-flex align-center">
                        <div class="text-h6">{{ selectedVirtualTourCategory.name }}</div>
                        <v-spacer />
                        <v-btn
                            v-if="
                                can.includes('website-content.destroy') &&
                                !defaultVirtualTourCategories.includes(selectedVirtualTourCategory.name)
                            "
                            variant="text"
                            color="error"
                            prepend-icon="mdi-delete-outline"
                            @click="destroyVirtualTourCategory(selectedVirtualTourCategory)"
                        >
                            Eliminar categoría
                        </v-btn>
                    </div>

                    <input
                        ref="virtualTourFileInput"
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        class="d-none"
                        @change="selectVirtualTourFiles"
                    />

                    <v-row>
                        <v-col
                            v-for="image in selectedVirtualTourCategory.images"
                            :key="image.id"
                            cols="12"
                            sm="6"
                            lg="4"
                        >
                            <v-card variant="outlined" class="h-100 position-relative">
                                <v-img :src="image.image_url" aspect-ratio="1.5" cover />
                                <v-card-text class="text-subtitle-1 font-weight-bold">
                                    {{ image.title }}
                                </v-card-text>
                                <div
                                    v-if="can.includes('website-content.destroy')"
                                    class="saved-delete-button"
                                >
                                    <BaseButton
                                        action="delete"
                                        @click="destroyVirtualTourImage(image)"
                                    />
                                </div>
                            </v-card>
                        </v-col>

                        <v-col
                            v-for="(image, index) in virtualTourForm.images"
                            :key="`${image.name}-${image.lastModified}`"
                            cols="12"
                            sm="6"
                            lg="4"
                        >
                            <v-card variant="outlined" class="h-100 position-relative">
                                <v-img :src="virtualTourPreviews[index]" aspect-ratio="1.5" cover />
                                <v-btn
                                    class="remove-image-button"
                                    icon="mdi-close"
                                    size="x-small"
                                    color="error"
                                    @click="removeVirtualTourSelected(index)"
                                />
                                <v-card-text>
                                    <v-text-field
                                        v-model="virtualTourForm.titles[index]"
                                        label="Título de la imagen"
                                        placeholder="Ej. Recepción"
                                        maxlength="100"
                                        counter="100"
                                        density="compact"
                                        hide-details="auto"
                                        :error-messages="(virtualTourForm.errors as any)[`titles.${index}`]"
                                    />
                                </v-card-text>
                            </v-card>
                        </v-col>

                        <v-col
                            v-if="can.includes('website-content.store') && availableVirtualTourSpaces > 0"
                            cols="12"
                            sm="6"
                            lg="4"
                        >
                            <div
                                class="upload-zone virtual-tour-upload-zone"
                                role="button"
                                tabindex="0"
                                @click="openVirtualTourFilePicker"
                                @keydown.enter="openVirtualTourFilePicker"
                            >
                                <v-icon icon="mdi-image-plus-outline" size="46" color="primary" />
                                <div class="mt-2 text-subtitle-1 font-weight-bold">Agregar imágenes</div>
                                <div class="mt-1 text-caption text-medium-emphasis">
                                    1200 × 800 px · máximo 20 MB
                                </div>
                            </div>
                        </v-col>
                    </v-row>

                    <v-alert
                        v-if="virtualTourForm.errors.images"
                        type="error"
                        variant="tonal"
                        class="mt-4"
                    >
                        {{ virtualTourForm.errors.images }}
                    </v-alert>

                    <div v-if="virtualTourForm.images.length" class="justify-end mt-4 d-flex">
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-upload"
                            :loading="virtualTourForm.processing"
                            @click="saveVirtualTourImages"
                        >
                            Guardar {{ virtualTourForm.images.length }} imagen(es)
                        </v-btn>
                    </div>
                </div>
            </v-card-text>
        </v-card>

        <v-card v-show="activeSection === 'events'">
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-calendar-month-outline" />
                Eventos del calendario
            </v-card-title>
            <v-card-subtitle>
                Captura una fecha, un título y el tipo de evento. El color se asigna automáticamente.
            </v-card-subtitle>

            <v-card-text>
                <v-card
                    v-if="can.includes('website-content.store')"
                    variant="tonal"
                    class="mb-6"
                >
                    <v-card-text>
                        <div class="mb-3 text-subtitle-1 font-weight-bold">
                            {{ eventForm.id ? "Editar evento" : "Nuevo evento" }}
                        </div>
                        <v-row align="start">
                            <v-col cols="12" md="5">
                                <v-text-field
                                    v-model="eventForm.title"
                                    label="Título"
                                    placeholder="Ej. Curso de verano"
                                    maxlength="100"
                                    hide-details="auto"
                                    :error-messages="eventForm.errors.title"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="3">
                                <v-text-field
                                    v-model="eventForm.event_date"
                                    label="Fecha"
                                    type="date"
                                    hide-details="auto"
                                    :error-messages="eventForm.errors.event_date"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="4">
                                <v-select
                                    v-model="eventForm.type"
                                    :items="eventTypes"
                                    item-title="title"
                                    item-value="value"
                                    label="Tipo"
                                    hide-details="auto"
                                    :error-messages="eventForm.errors.type"
                                >
                                    <template #item="{ props: itemProps, item }">
                                        <v-list-item v-bind="itemProps">
                                            <template #prepend>
                                                <span
                                                    class="event-color-dot"
                                                    :style="{ backgroundColor: item.raw.color }"
                                                />
                                            </template>
                                        </v-list-item>
                                    </template>
                                </v-select>
                            </v-col>
                        </v-row>

                        <div class="d-flex justify-end ga-2 mt-4">
                            <v-btn
                                v-if="eventForm.id"
                                variant="text"
                                @click="resetEventForm"
                            >
                                Cancelar edición
                            </v-btn>
                            <v-btn
                                color="primary"
                                prepend-icon="mdi-content-save-outline"
                                :loading="eventForm.processing"
                                @click="saveEvent"
                            >
                                {{ eventForm.id ? "Actualizar evento" : "Guardar evento" }}
                            </v-btn>
                        </div>
                    </v-card-text>
                </v-card>

                <div class="mb-3 d-flex align-center ga-2">
                    <div class="text-h6">Eventos registrados</div>
                    <v-chip size="small" color="primary" variant="tonal">{{ events.length }}</v-chip>
                </div>

                <v-table v-if="events.length" hover>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="event in events" :key="event.id">
                            <td class="text-no-wrap">{{ event.event_date }}</td>
                            <td>{{ event.title }}</td>
                            <td>
                                <v-chip
                                    size="small"
                                    :color="getEventType(event.type)?.color"
                                    variant="tonal"
                                >
                                    {{ getEventType(event.type)?.title }}
                                </v-chip>
                            </td>
                            <td class="text-right text-no-wrap">
                                <BaseButton
                                    v-if="can.includes('website-content.store')"
                                    action="edit"
                                    @click="editEvent(event)"
                                />
                                <BaseButton
                                    v-if="can.includes('website-content.destroy')"
                                    action="delete"
                                    @click="destroyEvent(event)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </v-table>

                <v-alert v-else type="info" variant="tonal">
                    Todavía no hay eventos para mostrar en el calendario.
                </v-alert>
            </v-card-text>
        </v-card>
    </AppLayout>
</template>

<style scoped>
.upload-zone {
    border: 2px dashed #90a4ae;
    border-radius: 14px;
    padding: 36px 20px;
    text-align: center;
    cursor: pointer;
    background: #f8fafc;
    transition: all 0.2s ease;
}

.upload-zone:hover,
.upload-zone--active {
    border-color: rgb(var(--v-theme-primary));
    background: rgba(var(--v-theme-primary), 0.06);
}

.upload-zone--disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.card-upload-zone {
    aspect-ratio: 1;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.carousel-upload-zone,
.virtual-tour-upload-zone {
    aspect-ratio: 1.5;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.category-name-field {
    min-width: 260px;
    flex: 1;
}

.remove-image-button {
    position: absolute;
    top: 8px;
    right: 8px;
}

.saved-delete-button {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 2;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.92);
    box-shadow: 0 2px 7px rgba(0, 0, 0, 0.2);
}

.event-color-dot {
    width: 12px;
    height: 12px;
    display: inline-block;
    border-radius: 50%;
}

.saved-image-label,
.pending-image-label {
    position: absolute;
    left: 8px;
    bottom: 8px;
    padding: 4px 9px;
    border-radius: 12px;
    color: white;
    font-size: 12px;
    background: rgba(0, 0, 0, 0.65);
}

.pending-image-label {
    background: rgb(var(--v-theme-primary));
}
</style>
