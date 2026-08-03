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
}

const props = defineProps<Props>();

/* ====================== Variables ====================== */
const page = usePage<any>();
const fileInput = ref<HTMLInputElement | null>(null);
const cardFileInput = ref<HTMLInputElement | null>(null);
const activeSection = ref("carousel");
const isDragging = ref(false);
const previews = ref<string[]>([]);
const cardPreviews = ref<string[]>([]);

/* ====================== useForm ====================== */
const form = useForm<{ images: File[]; descriptions: string[] }>({
    images: [],
    descriptions: [],
});
const cardForm = useForm<{ category: string; images: File[] }>({
    category: props.cardCategories[0] ?? "Gimnasio",
    images: [],
});

/* ====================== Computed ====================== */
const can = computed<string[]>(() => page.props.auth.permissions ?? []);
const selectedCount = computed(() => form.images.length);
const selectedCategoryCount = computed(() => {
    return props.homeCards.filter((card) => card.category === cardForm.category).length;
});
const availableCardSpaces = computed(() => 2 - selectedCategoryCount.value);
const cardSelectionSpaces = computed(() => availableCardSpaces.value - cardForm.images.length);

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

/* ====================== Lifecycle ====================== */
onUnmounted(() => {
    clearPreviews();
    cardPreviews.value.forEach((preview) => URL.revokeObjectURL(preview));
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
                <div v-if="can.includes('website-content.store')">
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        class="d-none"
                        @change="selectFiles"
                    />

                    <div
                        class="upload-zone"
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
                        <v-icon icon="mdi-cloud-upload-outline" size="54" color="primary" />
                        <div class="text-h6 mt-2">Arrastra tus imágenes aquí</div>
                        <div class="text-body-2 text-medium-emphasis my-1">o haz clic para seleccionarlas</div>
                        <v-chip size="small" variant="tonal" color="primary">
                            JPG, PNG o WebP · mínimo 1200 × 800 px · máximo 20 MB
                        </v-chip>
                    </div>

                    <v-alert
                        v-if="form.errors.images"
                        type="error"
                        variant="tonal"
                        class="mt-3"
                    >
                        {{ form.errors.images }}
                    </v-alert>

                    <template v-if="selectedCount">
                        <div class="d-flex align-center justify-space-between mt-6 mb-3">
                            <div class="text-subtitle-1 font-weight-bold">
                                {{ selectedCount }} imagen(es) seleccionada(s)
                            </div>
                            <v-btn variant="text" color="error" size="small" @click="clearSelection">
                                Limpiar selección
                            </v-btn>
                        </div>

                        <v-row>
                            <v-col
                                v-for="(image, index) in form.images"
                                :key="`${image.name}-${image.lastModified}`"
                                cols="12"
                                sm="6"
                                lg="4"
                            >
                                <v-card variant="outlined" class="h-100">
                                    <div class="position-relative">
                                        <v-img :src="previews[index]" height="190" cover />
                                        <v-btn
                                            class="remove-image-button"
                                            icon="mdi-close"
                                            size="x-small"
                                            color="error"
                                            @click="removeSelected(index)"
                                        />
                                    </div>
                                    <v-card-text>
                                        <div class="text-caption text-truncate mb-2" :title="image.name">
                                            {{ image.name }}
                                        </div>
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
                        </v-row>

                        <div class="d-flex justify-end mt-4">
                            <v-btn
                                color="primary"
                                size="large"
                                prepend-icon="mdi-upload"
                                :loading="form.processing"
                                @click="save"
                            >
                                Subir {{ selectedCount }} imagen(es)
                            </v-btn>
                        </div>
                    </template>
                </div>

                <v-divider class="my-7" />

                <div class="d-flex align-center ga-2 mb-4">
                    <div class="text-h6">Imágenes guardadas</div>
                    <v-chip size="small" color="primary" variant="tonal">
                        {{ carouselImages.length }}
                    </v-chip>
                </div>

                <v-row v-if="carouselImages.length">
                    <v-col
                        v-for="image in carouselImages"
                        :key="image.id"
                        cols="12"
                        sm="6"
                        lg="4"
                    >
                        <v-card variant="outlined" class="h-100">
                            <v-img :src="image.image_url" height="220" cover />
                            <v-card-text v-if="image.description" class="text-subtitle-1">
                                {{ image.description }}
                            </v-card-text>
                            <v-card-actions>
                                <v-spacer />
                                <BaseButton
                                    v-if="can.includes('website-content.destroy')"
                                    action="delete"
                                    @click="destroy(image)"
                                />
                            </v-card-actions>
                        </v-card>
                    </v-col>
                </v-row>

                <v-alert v-else type="info" variant="tonal">
                    Todavía no hay imágenes en el carrusel.
                </v-alert>
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
                <v-tabs
                    v-model="cardForm.category"
                    color="primary"
                    show-arrows
                    class="mb-5"
                >
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
                            <div class="saved-image-label">Imagen guardada</div>
                            <BaseButton
                                v-if="can.includes('website-content.destroy')"
                                class="remove-image-button"
                                action="delete"
                                @click="destroyCard(card)"
                            />
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

                    <v-col
                        v-if="can.includes('website-content.store') && cardSelectionSpaces > 0"
                        cols="12"
                        sm="6"
                        md="4"
                    >
                        <div
                            class="upload-zone card-upload-zone"
                            role="button"
                            tabindex="0"
                            @click="openCardFilePicker"
                            @keydown.enter="openCardFilePicker"
                        >
                            <v-icon icon="mdi-image-plus-outline" size="46" color="primary" />
                            <div class="text-subtitle-1 font-weight-bold mt-2">Agregar imagen</div>
                            <div class="text-caption text-medium-emphasis mt-1">
                                1000 × 1000 px · máximo 20 MB
                            </div>
                        </div>
                    </v-col>
                </v-row>

                <v-alert v-if="cardForm.errors.images" type="error" variant="tonal" class="mt-4">
                    {{ cardForm.errors.images }}
                </v-alert>

                <div v-if="cardForm.images.length" class="d-flex justify-end mt-4">
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

.remove-image-button {
    position: absolute;
    top: 8px;
    right: 8px;
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
