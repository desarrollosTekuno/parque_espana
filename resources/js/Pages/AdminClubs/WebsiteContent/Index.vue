<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import axios from "axios";
import { computed, onUnmounted, ref, watch } from "vue";

/* ====================== Props ====================== */
interface Props {
    carouselImages: any[];
    homeCards: any[];
    virtualTourSections: any[];
    events: any[];
    eventTypes: any[];
}

interface PendingVirtualTourImage {
    category: string;
    title: string;
    image: File;
    preview: string;
    error?: string;
}

const props = defineProps<Props>();

/* ====================== Variables ====================== */
const page = usePage<any>();
const fileInput = ref<HTMLInputElement | null>(null);
const cardFileInput = ref<HTMLInputElement | null>(null);
const virtualTourFileInput = ref<HTMLInputElement | null>(null);
const activeSection = ref("carousel");
const activeVirtualTourSection = ref(props.virtualTourSections[0]?.name ?? "");
const isDragging = ref(false);
const previews = ref<string[]>([]);
const cardPreview = ref<string | null>(null);
const virtualTourPreview = ref<string | null>(null);
const pendingVirtualTourImages = ref<PendingVirtualTourImage[]>([]);
const savingVirtualTourImages = ref(false);
const virtualTourImageError = ref("");

/* ====================== useForm ====================== */
const form = useForm<{ images: File[]; descriptions: string[] }>({
    images: [],
    descriptions: [],
});
const cardForm = useForm<{ category: string; image: File | null }>({
    category: "",
    image: null,
});
const virtualTourForm = useForm<{ category: string; title: string; image: File | null }>({
    category: "",
    title: "",
    image: null,
});
const eventForm = useForm<{ id: number | null; title: string; start_date: string; end_date: string; type: string }>({
    id: null,
    title: "",
    start_date: "",
    end_date: "",
    type: "activity",
});

/* ====================== Computed ====================== */
const can = computed<string[]>(() => page.props.auth.permissions ?? []);
const selectedCount = computed(() => form.images.length);
const cardLimitReached = computed(() => props.homeCards.length >= 8);

/* ====================== Funciones ====================== */
const openFilePicker = () => {
    fileInput.value?.click();
};

const addFiles = (files: File[]) => {
    const imageFiles = files.filter((file) => file.type.startsWith("image/"));
    const available = 5 - props.carouselImages.length - form.images.length;

    if (imageFiles.length > available) {
        customToastSwal({
            title: "El carrusel puede tener máximo 5 imágenes",
            icon: "warning",
        });
    }

    form.images = [...form.images, ...imageFiles.slice(0, Math.max(available, 0))];
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
            title: "Primero escribe el nombre de la categoría",
            icon: "warning",
        });
        return;
    }

    cardFileInput.value?.click();
};

const selectCardFiles = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = Array.from(input.files ?? []).find((item) => item.type.startsWith("image/"));
    cardForm.image = file ?? null;
    input.value = "";
};

const clearCardForm = () => {
    cardForm.reset();
    cardForm.clearErrors();
};

const saveCards = () => {
    cardForm.post(route("website-content.cards.store"), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            customToastSwal({
                title: page.props.flash.success || "Categoría guardada correctamente",
                icon: "success",
            });
            clearCardForm();
        },
        onError: (errors) => {
            customToastSwal({
                title: Object.values(errors)[0] || "No se pudo guardar la categoría",
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

const selectVirtualTourSlot = (category: string, title: string) => {
    virtualTourForm.category = category;
    virtualTourForm.title = title;
    virtualTourForm.clearErrors();
    virtualTourImageError.value = "";
    virtualTourFileInput.value?.click();
};

const selectVirtualTourFile = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = Array.from(input.files ?? []).find((item) => item.type.startsWith("image/"));

    if (file) {
        const index = pendingVirtualTourImages.value.findIndex((item) => {
            return item.category === virtualTourForm.category && item.title === virtualTourForm.title;
        });

        if (index >= 0) {
            URL.revokeObjectURL(pendingVirtualTourImages.value[index].preview);
            pendingVirtualTourImages.value[index] = {
                category: virtualTourForm.category,
                title: virtualTourForm.title,
                image: file,
                preview: URL.createObjectURL(file),
            };
        } else {
            pendingVirtualTourImages.value.push({
                category: virtualTourForm.category,
                title: virtualTourForm.title,
                image: file,
                preview: URL.createObjectURL(file),
            });
        }
    }

    input.value = "";
};

const clearVirtualTourSelection = () => {
    pendingVirtualTourImages.value.forEach((item) => URL.revokeObjectURL(item.preview));
    pendingVirtualTourImages.value = [];
    virtualTourForm.reset();
    virtualTourForm.clearErrors();
    virtualTourImageError.value = "";
};

const removePendingVirtualTourImage = (category: string, title: string) => {
    const image = pendingVirtualTourImages.value.find((item) => {
        return item.category === category && item.title === title;
    });

    if (image) {
        URL.revokeObjectURL(image.preview);
    }

    pendingVirtualTourImages.value = pendingVirtualTourImages.value.filter((item) => {
        return item.category !== category || item.title !== title;
    });
};

const getPendingVirtualTourImage = (category: string, title: string) => {
    return pendingVirtualTourImages.value.find((item) => {
        return item.category === category && item.title === title;
    });
};

const saveVirtualTourImages = async () => {
    savingVirtualTourImages.value = true;
    const failedImages: PendingVirtualTourImage[] = [];
    let savedImages = 0;

    for (const pendingImage of pendingVirtualTourImages.value) {
        try {
            const formData = new FormData();
            formData.append("category", pendingImage.category);
            formData.append("title", pendingImage.title);
            formData.append("image", pendingImage.image);

            await axios.post(route("website-content.virtual-tour.images.store"), formData);

            URL.revokeObjectURL(pendingImage.preview);
            savedImages++;
        } catch (error: any) {
            failedImages.push({
                ...pendingImage,
                error: error.response?.data?.errors?.image?.[0] || "No se pudo guardar esta imagen.",
            });
        }
    }

    pendingVirtualTourImages.value = failedImages;

    if (savedImages) {
        router.reload({ only: ["virtualTourSections"] });
        customToastSwal({
            title: savedImages === 1 ? "Imagen guardada correctamente" : "Imágenes guardadas correctamente",
            icon: "success",
        });
    }

    savingVirtualTourImages.value = false;
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
    eventForm.start_date = event.start_date;
    eventForm.end_date = event.end_date;
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
    () => cardForm.image,
    (image) => {
        if (cardPreview.value) {
            URL.revokeObjectURL(cardPreview.value);
        }

        cardPreview.value = image ? URL.createObjectURL(image) : null;
    },
);

/* ====================== Lifecycle ====================== */
onUnmounted(() => {
    clearPreviews();

    if (cardPreview.value) {
        URL.revokeObjectURL(cardPreview.value);
    }

    clearVirtualTourSelection();
});
</script>

<template>
    <Head title="Página web" />

    <AppLayout>
        <template #header>Página web</template>

        <v-card class="website-tabs mb-4">
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

        <v-card v-show="activeSection === 'carousel'" class="content-panel">
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-view-carousel-outline" />
                Carrusel de inicio
            </v-card-title>
            <v-card-subtitle>Máximo 5 imágenes.</v-card-subtitle>

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
                        <v-card variant="outlined" class="media-card h-100 position-relative">
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
                        <v-card variant="outlined" class="media-card h-100 position-relative">
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
                        v-if="can.includes('website-content.store') && carouselImages.length + selectedCount < 5"
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
                            <div class="mt-2 text-subtitle-1 font-weight-bold">Agregar imágenes</div>
                            <div class="mt-1 text-caption text-medium-emphasis">
                                1200 × 800 px · máximo 5 MB
                            </div>
                        </div>
                    </v-col>
                </v-row>

                <v-alert v-if="form.errors.images" type="error" variant="tonal" class="mt-4">
                    {{ form.errors.images }}
                </v-alert>

                <div v-if="selectedCount" class="justify-end mt-4 d-flex align-center ga-2">
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

        <v-card v-show="activeSection === 'cards'" class="content-panel">
            <v-card-title class="flex-wrap d-flex align-center ga-2">
                <v-icon icon="mdi-view-grid-outline" />
                Cards de inicio
            </v-card-title>
            <v-card-subtitle>
                Cada categoría tiene una sola imagen. Máximo 8 cards.
            </v-card-subtitle>

            <v-card-text>
                <v-alert v-if="cardLimitReached" type="warning" variant="tonal" class="mb-5">
                    Llegaste al máximo de 8 cards. Elimina una existente para agregar otra.
                </v-alert>

                <v-row>
                    <v-col v-if="can.includes('website-content.store') && !cardLimitReached" cols="12" sm="6" md="4">
                        <v-card variant="outlined" class="media-card h-100 position-relative">
                            <input
                                ref="cardFileInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="d-none"
                                @change="selectCardFiles"
                            />

                            <div v-if="cardPreview" class="position-relative">
                                <v-img :src="cardPreview" aspect-ratio="1" cover />
                                <div class="pending-image-label">Pendiente por subir</div>
                                <v-btn
                                    class="remove-image-button"
                                    icon="mdi-close"
                                    size="x-small"
                                    color="error"
                                    @click="cardForm.image = null"
                                />
                            </div>
                            <div
                                v-else
                                class="upload-zone card-upload-zone"
                                role="button"
                                tabindex="0"
                                @click="openCardFilePicker"
                                @keydown.enter="openCardFilePicker"
                            >
                                <v-icon icon="mdi-image-plus-outline" size="46" color="primary" />
                                <div class="mt-2 text-subtitle-1 font-weight-bold">Agregar imagen</div>
                                <div class="mt-1 text-caption text-medium-emphasis">
                                    1000 × 1000 px · máximo 5 MB
                                </div>
                            </div>

                            <v-card-text>
                                <v-text-field
                                    v-model="cardForm.category"
                                    label="Nombre de la categoría"
                                    maxlength="30"
                                    density="compact"
                                    hide-details="auto"
                                    :error-messages="cardForm.errors.category"
                                />
                                <v-alert v-if="cardForm.errors.image" type="error" variant="tonal" class="mt-3">
                                    {{ cardForm.errors.image }}
                                </v-alert>
                            </v-card-text>

                            <v-card-actions class="justify-end pt-0">
                                <v-btn variant="text" @click="clearCardForm">Cancelar</v-btn>
                                <v-btn
                                    color="primary"
                                    prepend-icon="mdi-content-save"
                                    :loading="cardForm.processing"
                                    :disabled="!cardForm.category || !cardForm.image"
                                    @click="saveCards"
                                >
                                    Guardar
                                </v-btn>
                            </v-card-actions>
                        </v-card>
                    </v-col>

                    <v-col
                        v-for="card in homeCards"
                        :key="card.id"
                        cols="12"
                        sm="6"
                        md="4"
                    >
                        <v-card variant="outlined" class="media-card position-relative">
                            <v-img :src="card.image_url" aspect-ratio="1" cover />
                            <v-card-title>{{ card.category }}</v-card-title>
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
                </v-row>

                <v-alert v-if="!homeCards.length" type="info" variant="tonal">
                    No hay categorías registradas.
                </v-alert>
            </v-card-text>
        </v-card>

        <v-card v-show="activeSection === 'virtual-tour'" class="content-panel">
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-panorama-variant-outline" />
                Vista virtual de instalaciones
            </v-card-title>
            <v-card-subtitle>
                Selecciona un cajón para cargar o cambiar su imagen.
            </v-card-subtitle>

            <v-card-text>
                <input
                    ref="virtualTourFileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="d-none"
                    @change="selectVirtualTourFile"
                />

                <v-card v-if="virtualTourPreview" variant="tonal" class="soft-panel mb-5">
                    <v-card-title>Imagen seleccionada</v-card-title>
                    <v-card-text class="flex-wrap d-flex align-center ga-4">
                        <v-img :src="virtualTourPreview" width="180" aspect-ratio="1.5" cover />
                        <div>
                            <div class="text-subtitle-1 font-weight-bold">{{ virtualTourForm.title }}</div>
                            <div class="text-caption">Sección: {{ virtualTourForm.category }}</div>
                        </div>
                        <v-spacer />
                        <v-btn variant="text" @click="clearVirtualTourSelection">Cancelar</v-btn>
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-content-save"
                            :loading="virtualTourForm.processing"
                            @click="saveVirtualTourImages"
                        >
                            Guardar imagen
                        </v-btn>
                    </v-card-text>
                </v-card>

                <v-alert v-if="virtualTourImageError" type="error" variant="tonal" class="mb-5">
                    {{ virtualTourImageError }}
                </v-alert>

                <v-tabs v-model="activeVirtualTourSection" color="primary" show-arrows class="mb-5">
                    <v-tab v-for="section in virtualTourSections" :key="section.name" :value="section.name">
                        {{ section.name }}
                        <v-chip size="x-small" class="ml-2">
                            {{ section.slots.filter(slot => slot.image).length }}/{{ section.slots.length }}
                        </v-chip>
                    </v-tab>
                </v-tabs>

                <div v-for="section in virtualTourSections" v-show="activeVirtualTourSection === section.name" :key="section.name">
                    <v-row>
                        <v-col v-for="slot in section.slots" :key="slot.title" cols="12" sm="6" lg="4">
                            <v-card
                                v-if="getPendingVirtualTourImage(section.name, slot.title)"
                                variant="outlined"
                                class="media-card h-100 position-relative"
                            >
                                <v-img :src="getPendingVirtualTourImage(section.name, slot.title)?.preview" aspect-ratio="1.5" cover />
                                <v-btn
                                    class="remove-image-button"
                                    icon="mdi-close"
                                    size="x-small"
                                    color="error"
                                    @click="removePendingVirtualTourImage(section.name, slot.title)"
                                />
                                <v-card-text class="text-subtitle-1 font-weight-bold">
                                    {{ slot.title }}
                                </v-card-text>
                                <v-alert
                                    v-if="getPendingVirtualTourImage(section.name, slot.title)?.error"
                                    type="error"
                                    variant="tonal"
                                    density="compact"
                                    class="mx-4 mb-4"
                                >
                                    {{ getPendingVirtualTourImage(section.name, slot.title)?.error }}
                                </v-alert>
                            </v-card>
                            <v-card v-else-if="slot.image" variant="outlined" class="media-card h-100">
                                <v-img :src="slot.image.image_url" aspect-ratio="1.5" cover />
                                <v-card-text class="text-subtitle-1 font-weight-bold">
                                    {{ slot.title }}
                                </v-card-text>
                                <div class="saved-delete-button">
                                    <BaseButton
                                        v-if="can.includes('website-content.destroy')"
                                        action="delete"
                                        @click="destroyVirtualTourImage(slot.image)"
                                    />
                                </div>
                            </v-card>
                            <div
                                v-else
                                class="upload-zone carousel-upload-zone virtual-tour-upload-zone"
                                role="button"
                                tabindex="0"
                                @click="selectVirtualTourSlot(section.name, slot.title)"
                                @keydown.enter="selectVirtualTourSlot(section.name, slot.title)"
                            >
                                <v-icon icon="mdi-image-plus-outline" size="46" color="primary" />
                                <div class="mt-2 text-subtitle-1 font-weight-bold">{{ slot.title }}</div>
                                <div class="mt-1 text-body-2 text-medium-emphasis">Subir imagen</div>
                            </div>
                        </v-col>
                    </v-row>
                    <div v-if="pendingVirtualTourImages.length" class="justify-end mt-4 d-flex align-center ga-2">
                        <v-btn variant="text" color="error" @click="clearVirtualTourSelection">
                            Limpiar selección
                        </v-btn>
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-upload"
                            :loading="savingVirtualTourImages"
                            @click="saveVirtualTourImages"
                        >
                            Guardar imágenes
                        </v-btn>
                    </div>
                </div>
            </v-card-text>
        </v-card>

        <v-card v-show="activeSection === 'events'" class="content-panel">
            <v-card-title class="d-flex align-center ga-2">
                <v-icon icon="mdi-calendar-month-outline" />
                Eventos del calendario
            </v-card-title>
            <v-card-subtitle>
                Captura una fecha, un título y el tipo de evento.
            </v-card-subtitle>

            <v-card-text>
                <v-card
                    v-if="can.includes('website-content.store')"
                    variant="tonal"
                    class="soft-panel mb-6"
                >
                    <v-card-text>
                        <div class="mb-3 text-subtitle-1 font-weight-bold">
                            {{ eventForm.id ? "Editar evento" : "Nuevo evento" }}
                        </div>
                        <v-row align="start">
                            <v-col cols="12" md="3">
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
                                    v-model="eventForm.start_date"
                                    label="Fecha de inicio"
                                    type="date"
                                    hide-details="auto"
                                    :error-messages="eventForm.errors.start_date"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="3">
                                <v-text-field
                                    v-model="eventForm.end_date"
                                    label="Fecha de fin"
                                    type="date"
                                    hide-details="auto"
                                    :error-messages="eventForm.errors.end_date"
                                />
                            </v-col>
                            <v-col cols="12" sm="6" md="3">
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

                        <div class="justify-end mt-4 d-flex ga-2">
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
                </div>

                <v-table v-if="events.length" hover>
                    <thead>
                        <tr>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="event in events" :key="event.id">
                            <td class="text-no-wrap">{{ event.start_date }}</td>
                            <td class="text-no-wrap">{{ event.end_date }}</td>
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
.website-tabs,
.content-panel {
    border: 1px solid #e6ebf0;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

.content-panel {
    overflow: hidden;
}

.media-card {
    border: 1px solid #dce4ea !important;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
}

.soft-panel {
    border: 1px solid #dcecf2;
    border-radius: 12px;
    box-shadow: none;
}

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

.empty-virtual-tour-slot {
    aspect-ratio: 1.5;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
}
</style>
