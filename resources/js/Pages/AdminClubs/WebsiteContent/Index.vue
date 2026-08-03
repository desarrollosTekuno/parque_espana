<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, onUnmounted, ref, watch } from "vue";

/* ====================== Props ====================== */
interface Props {
    carouselImages: any[];
}

const props = defineProps<Props>();

/* ====================== Variables ====================== */
const page = usePage<any>();
const fileInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const previews = ref<string[]>([]);

/* ====================== useForm ====================== */
const form = useForm<{ images: File[]; descriptions: string[] }>({
    images: [],
    descriptions: [],
});

/* ====================== Computed ====================== */
const can = computed<string[]>(() => page.props.auth.permissions ?? []);
const selectedCount = computed(() => form.images.length);

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

/* ====================== Lifecycle ====================== */
onUnmounted(clearPreviews);
</script>

<template>
    <Head title="Página web" />

    <AppLayout>
        <template #header>Página web</template>

        <v-card>
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

.remove-image-button {
    position: absolute;
    top: 8px;
    right: 8px;
}
</style>
