    <script setup lang="ts">

    import '@/../css/amenities.css';
    import "@vueup/vue-quill/dist/vue-quill.snow.css";
    import { QuillEditor } from "@vueup/vue-quill";
    import BaseButton from "@/Components/BaseButton.vue";
    import FormDateTimePicker from "@/Components/Form/FormDateTimePicker.vue";
    import FormDescripcion from "@/Components/Form/FormDescripcion.vue";
    import FormImage from "@/Components/Form/FormImage.vue";
    import FormName from "@/Components/Form/FormName.vue";
    import { required, maxLength, fileMaxSizeRule, fileTypeRule } from "@/constants/validationRules";
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { customConfirmSwal, customToastSwal } from "@/utils/swal";
    import { Head, router, useForm, usePage } from "@inertiajs/vue3";
    import { debounce } from "lodash";
    import { ref, watch, computed, onMounted  } from "vue";

    const page = usePage();
    const can = page.props.auth.permissions;

    interface Props {
        announcements?: any;
    }
    const props = withDefaults(
        defineProps<Props>(), {
        announcements: null,
        amenities: []
    });
    const amenitiesList = ref(props.amenities ?? []);
    const resourcesList = ref(props.resources ?? []);
    const showModal = ref(false);
    const imageRef = ref<any>(null);
    const formSendRef = ref();
    const imagePreview = ref<string | null>(null);
    const form = useForm({
        id: null,
        club_id: page.props.auth.currentClub,
        title: "",
        content: "",
        type: null,
        image: null,
        image_path: null,
        remove_image: false,
        is_active: true,
        publish_at: null,
        expires_at: null,
        resource_id: null,
        capacity: null,
        starts_at: null,
        ends_at: null,
        status: "draft"
    });
    const isSaveDisabled = computed(() => {
        return imageRef.value?.isValid === false;
    });
    const create = () => {
        form.reset();
        form.is_active = true;
        form.resource_id = null;
        form.capacity = null;
        form.starts_at = null;
        form.ends_at = null;
        imagePreview.value = null;
        showModal.value = true;
    };
    const formatDateForInput = (val: string | null) => {
        if (!val) return null;
            if (val.includes('T')) {
                return val.slice(0, 16);
            }
        return val.replace(' ', 'T').slice(0, 16);
    };
const formatToPicker = (val: string | null) => {
    if (!val) return null;

    // 👉 elimina milisegundos y zona
    let clean = val.replace('T', ' ').split('.')[0];

    // 👉 si trae Z o timezone lo quitamos
    clean = clean.replace('Z', '');

    return clean;
};
    const edit = (item: any) => {
        form.reset();

        form.id = item.id;
        form.title = item.title;
        form.content = item.content;
        form.type = item.type;
        form.status = item.status;

        form.publish_at = formatToPicker(item.publish_at);  
        form.expires_at = formatToPicker(item.expires_at); 

        form.is_active = item.is_active;

        form.image = null;
        form.image_path = item.image_url;
        imagePreview.value = item.image_url ?? null;

        showModal.value = true;
    };
    const save = () => {
        formSendRef.value
            ?.validate()
            .then(({ valid }) => {
                if (!valid) return;
                const publish = normalizeDate(form.publish_at);
                const expires = normalizeDate(form.expires_at);
                const now = Date.now();

                if (!publish) {
                    customToastSwal({ title: "Fecha de publicación inválida", icon: "error" });
                    return;
                }

                if (publish < now) {
                    customToastSwal({ title: "La publicación no puede ser en el pasado", icon: "error" });
                    return;
                }

                if (!expires) {
                    customToastSwal({ title: "Fecha de expiración inválida", icon: "error" });
                    return;
                }

                if (expires <= publish) {
                    customToastSwal({ title: "La expiración debe ser mayor a publicación", icon: "error" });
                    return;
                }
                form.transform((data: any) => {
                let payload:any = {
                    ...data,
                };
                if (!(data.image instanceof File)) {
                    delete payload.image;
                }
                if (form.id) {
                    payload._method = "PUT";
                }
                return payload;
                }).post(
                    form.id
                        ? route(
                            "announcements.update",
                            form.id
                        ) : route(
                            "announcements.store"
                        ),
                    {
                        forceFormData: true,
                        onSuccess: () => {
                            customToastSwal({
                                title: page.props.flash.success || "",
                                icon: "success"
                            });
                            showModal.value = false;
                            form.reset();
                            imagePreview.value = null;
                            fetchItems();
                        },
                        onError: () => {
                            console.log("ERRORES", form.errors);
                            const firstError =
                                Object.values(form.errors)[0];
                            customToastSwal({
                                title: "Horario no disponible",
                                text: firstError,
                                icon: "error",
                                timer: 8000
                            });
                        }
                    }
                );
            });
    };
    const destroy = (item: any) => {
        customConfirmSwal({
            title: "¿Eliminar anuncio?"
        })
            .then(result => {
                if (result.isConfirmed) {
                    router.delete(
                        route("announcements.destroy",
                            item.id
                        ),
                        {
                            onSuccess: () => {
                                customToastSwal({ 
                                    title: page.props.flash.success || "",
                                    icon: "success" 
                                });
                                fetchItems();
                            },
                            onError: () => {
                                customToastSwal({
                                    title: `Error: ${form.errors.messageError}`,
                                    text: `${form.errors.exception}`,
                                    icon: "error",
                                });
                            }
                        }
                    );
                }
            });
    };
 
    const isSameDay = (start:string|null,end:string|null) => {
    if(!start || !end) return false;
        const s = new Date(start);
        const e = new Date(end);
        return s.toDateString() === e.toDateString();
};
const parseLocalDate = (dateStr: string) => {
    if (!dateStr) return null;

    const parts = dateStr.split(' ');
    const date = parts[0];
    const time = parts[1] ?? '00:00:00'; // 👈 fallback

    const [year, month, day] = date.split('-').map(Number);
    const [hour, minute, second] = time.split(':').map(Number);

    return new Date(
        year,
        (month ?? 1) - 1,
        day ?? 1,
        hour ?? 0,
        minute ?? 0,
        second ?? 0
    );
};
const formatEventDate = (start:string|null,end:string|null) => {
    if(!start) return "-";

    const s = parseLocalDate(start);

    const datePart = s.toLocaleDateString("es-MX", {
        day:"2-digit",
        month:"short",
        year:"numeric"
    });

    const startTime = s.toLocaleTimeString("es-MX", {
        hour:"numeric",
        minute:"2-digit",
        hour12:true
    });

    if(!end) return `${datePart} · ${startTime}`;

    const e = parseLocalDate(end);

    const endTime = e.toLocaleTimeString("es-MX", {
        hour:"numeric",
        minute:"2-digit",
        hour12:true
    });

    if(isSameDay(start,end)){
        return `${datePart} · ${startTime} - ${endTime}`;
    }

    const endDatePart = e.toLocaleDateString("es-MX", {
        day:"2-digit",
        month:"short",
        year:"numeric"
    });

    return `${datePart} ${startTime}
            → ${endDatePart} ${endTime}`;
};

    const removeImage = () => {
        form.image = null;
        form.image_path = null;
        form.remove_image = true;
        imagePreview.value = null;
    };

    const headers = [
        {title: "Título",key: "title"},
        {title: "Tipo",key: "type"},
        {title: "Imagen",key: "image"},
        {title: "Fecha de publicación y expiración",key: "event_date"},
        {title: "Acciones",key: "actions",sortable: false}
    ];
    const typeLabel: any = {
        comunicado: {"label": "Comunicado", "color": "grey"},
        torneo: {"label": "Torneo", "color": "purple"},
        evento: {"label": "Evento", "color": "blue"},
        info_parque: {"label": "Info parque", "color": "green"}
    };

    const items = ref([]);
    const total = ref(0);
    const loading = ref(false);
    const search = ref(
        page.props.ziggy?.query?.announcements_search ?? ""
    );
    const options = ref({
        page: Number(
            page.props.ziggy?.query?.announcements_page ?? 1),
        itemsPerPage: Number(
            page.props.ziggy
                ?.query?.announcements_per_page ?? 10),
        sortBy: [
            {
                key:
                    page.props.ziggy?.query?.announcements_sort ?? "id",
                order:
                    page.props.ziggy?.query?.announcements_order ?? "desc"
            }
        ]
    });
const publishNotInPast = (value: any) => {
    const publish = normalizeDate(value);
    if (!publish) return true;

    const now = Date.now();

    return publish >= now || "La fecha de publicación no puede ser anterior a hoy";
};
const normalizeDate = (val: any) => {
    if (!val) return null;
    const str = String(val).trim();
    const iso = str.includes('T')
        ? str
        : str.replace(' ', 'T');
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? null : d.getTime();
};
const expiresAfterPublish = (value: any) => {
    const expires = normalizeDate(value);
    const publish = normalizeDate(form.publish_at);

    if (!expires || !publish) return true;

    return expires > publish || "La fecha de expiración debe ser mayor a la publicación";
};
const isToday = (dateStr: string | null) => {
    if (!dateStr) return false;
    const d = new Date(dateStr);
    const now = new Date();
    return d.toDateString() === now.toDateString();
};

    const prefix = "announcements";
    const fetchItems = () => {
    loading.value = true;
    router.get(
        route("announcements.index"),
        {
            announcements_page: options.value.page,
            announcements_per_page: options.value.itemsPerPage,
            announcements_search: search.value,
            announcements_sort: options.value.sortBy?.[0]?.key,
            announcements_order: options.value.sortBy?.[0]?.order
        },
        {
            preserveState: true,
            replace: true,
            only: ["announcements"]
        }
    );
};

/*    Funciones modal galeria    */
const showGalleryModal = ref(false);

const galleryForm = useForm({
    announcement_id: null,
    images: [] as File[],
    existing_images: [] as any[],
    remove_images: [] as number[]
});
const loadingGallery = ref(false);

const openGallery = async (item:any) => {
    galleryForm.reset();
    galleryForm.announcement_id = item.id;
    galleryForm.existing_images = [];
    showGalleryModal.value = true;
    loadingGallery.value = true;
    try{
        const res = await axios.get(
            route(
                "announcements.gallery.index",
                item.id
            )
        );
        galleryForm.existing_images =
            res.data.images ?? [];
    }
    catch(e){
        console.error(e);
        customToastSwal({
            title: "Error cargando galería",
            icon: "error"
        });
    }
    finally{
        loadingGallery.value = false;
    }
};
const previewURL = (file: File) => {
    return window.URL.createObjectURL(file);
};
const MAX_MB = 2 * 1024 * 1024;
const handleImagesSelected = (files:any[]) => {
    if(!files) return;
    const MAX_MB = 2 * 1024 * 1024;
    const MAX_FILES = 5;
    let validFiles:any[] = [];
    for(const file of files){
        if(file.size > MAX_MB){
            customToastSwal({
                title: `${file.name} excede 2MB`,
                icon:"warning"
            });
            continue;
        }
        validFiles.push(file);
    }
    const availableSlots =
        MAX_FILES
        - galleryForm.existing_images.length;
    if(validFiles.length > availableSlots){
        validFiles =
            validFiles.slice(0,availableSlots);
        customToastSwal({
            title: "Máximo 5 imágenes por anuncio",
            icon:"warning"
        });
    }
    galleryForm.images = validFiles;

};
const saveGallery = () => {
    galleryForm
        .post(
            route("announcements.gallery.store"),
            {
                forceFormData: true,
                onSuccess: () => {
                    customToastSwal({
                        title: page.props.flash.success || "",
                        icon: "success"
                    });
                    showGalleryModal.value = false;
                    galleryForm.reset();
                    fetchItems();
                },
                onError: () => {
                    customToastSwal({
                        title: `Error: ${form.errors.messageError}`,
                        text: `${form.errors.exception}`,
                        icon: "error",
                    });
                }
            }
        );
};
const removeExistingImage = (img:any) => {
    galleryForm.remove_images.push(img.id);
    galleryForm.existing_images =
        galleryForm.existing_images.filter(
            i => i.id !== img.id
        );
};
const removeNewImage = (index:number) => {
    galleryForm.images.splice(index,1);
};
const totalImages = computed(()=>{
    return (
        galleryForm.images.length
        + galleryForm.existing_images.length
    );
});
const hasGalleryChanges = computed(()=>{
    return (
        galleryForm.images.length > 0
        || galleryForm.remove_images.length > 0
    );
});
watch(
    () => props.announcements,
    (val) => {
        items.value = val?.data ?? [];
        total.value = val?.total ?? 0;
        loading.value = false; 
    },
    { immediate: true }
);

watch(
    () => form.image,
    (file) => {
        if (!(file instanceof File)) return;
            if (file.size > MAX_MB) {
                customToastSwal({
                    title: "La imagen excede 2MB",
                    icon: "error"
                });
                form.image = null;
                imagePreview.value = null;
                return;
            }
        imagePreview.value = URL.createObjectURL(file);
        form.remove_image = false;
    }
);
watch(
    () => props.amenities,
    (val) => {
        console.log("amenities props", val);

        amenitiesList.value = val ?? [];
    },
    { immediate: true }
);
watch(
    () => props.resources,
    (val) => {
        resourcesList.value = val ?? [];
    },
    { immediate: true }
);
watch(
    [options, search],
    debounce(fetchItems, 400),
    { deep: true }
);
watch(
    () => form.image,
    (val) => {
        console.log("IMAGE VALUE", val);
        console.log("IS FILE", val instanceof File);
    }
);
watch(
    () => form.resource_id,
    (val) => {
        if (form.id) return;
        const selected =
            resourcesList.value.find(
                r => r.id === val
            );
        if (selected) {
            form.capacity =
                selected.capacity ?? null;
        }
    }
);
    </script>

    <template>
        <Head title="Noticias y Avisos" />
        <AppLayout>
            <template #options>
                <BaseButton v-if="can.includes('announcements.store')" variant="elevated" :text="'Nuevo anuncio'" action="add" 
                     :icon-only="false" @click="create" />
            </template>
            <template #header>
                Noticias y Avisos
            </template>
            <div class="pa-4 bg-grey-lighten-4 rounded-xl mt-5">
                <v-data-table-server class="elevation-1" fixed-header hover height="500px" 
                    :headers="headers" 
                    :items="items"
                    :items-length="total" 
                    :loading="loading" 
                    loading-text="Cargando anuncios..."
                    v-model:options="options" 
                    :items-per-page-options="[10, 25, 50, 100]" 
                    items-per-page-text="Mostrar"
                    no-data-text="No hay anuncios"
                >
                    <template #top>
                        <v-text-field v-model="search" label="Buscar anuncio..." prepend-inner-icon="mdi-magnify"
                            variant="outlined" density="comfortable" class="mx-4 mt-4" />
                    </template>
                    <template #item.type="{ item }">
                        <v-chip
                            size="small"
                            variant="tonal"
                            :color="typeLabel[item.type]?.color"
                        >
                            {{ typeLabel[item.type]?.label }}
                        </v-chip>
                    </template>
                    <template #item.resource="{ item }">
                        {{ item.detail?.resource?.amenity?.name }}
                        -
                        {{ item.detail?.resource?.name }}
                    </template>
                    <template #item.image="{ item }">
                        <v-img v-if="item.image_url" :src="item.image_url" max-height="70" max-width="100"
                            class="rounded" />
                    </template>
                    <template #item.event_date="{ item }">
                        {{ formatEventDate(
                            item.publish_at_formatted,
                            item.expires_at_formatted
                        ) }}
                    </template>
                    <template #item.actions="{ item }">
                        <v-tooltip text="Agregar galería">
                            <template #activator="{ props }">
                                <BaseButton v-bind="props" icon="mdi-camera" color="green" @click="openGallery(item)"/>
                            </template>
                        </v-tooltip>
                        <BaseButton action="edit" @click="edit(item)" v-if="can.includes('announcements.update')" />
                        <BaseButton action="delete" @click="destroy(item)" v-if="can.includes('announcements.destroy')" />
                    </template>
                </v-data-table-server>
            </div>
            <v-dialog v-model="showModal" max-width="650" persistent>
                <v-form ref="formSendRef" @submit.prevent="save">
                    <v-card :title="form.id ? 'Editar aviso' : 'Nuevo aviso'">
                        <v-card-text style="max-height:70vh; overflow:auto">
                            <v-row>
                                <v-col cols="12">
                                    <v-select v-model="form.type" label="Tipo de aviso" prepend-inner-icon="mdi-shape"
                                        :items="[
                                            { title: 'Comunicado', value: 'comunicado' },
                                            { title: 'Torneo', value: 'torneo' },
                                            { title: 'Evento', value: 'evento' },
                                            { title: 'Información del parque', value: 'info_parque' }
                                        ]" item-title="title" item-value="value" :rules="[required]" />
                                </v-col>
                                <v-col cols="12">
                                    <FormName v-model="form.title" label="Título" :rules="[required, maxLength(150)]" />
                                </v-col>
                                <v-col cols="12" style="margin-bottom:50px">
                                    <QuillEditor v-model:content="form.content" 
                                        contentType="html" 
                                        theme="snow" 
                                        toolbar="toolbarCompact" 
                                        placeholder="Escribe el contenido del aviso..."
                                        style="min-height:150px;"/>
                                </v-col>
                                <v-col cols="12">
                                    <FormImage v-model="form.image" 
                                        label="Imagen" 
                                        ref="imageRef"
                                        :rules="[
                                            fileMaxSizeRule(2),
                                            fileTypeRule(['jpg','jpeg','png','webp'])
                                        ]" />
                                    <v-card height="150" variant="outlined" class="mt-2 d-flex align-center justify-center">
                                        <v-img v-if="imagePreview" :src="imagePreview" height="120" class="rounded" />
                                        <v-icon v-else size="40" color="grey">
                                            mdi-image-outline
                                        </v-icon>
                                    </v-card>
                                    <v-btn v-if="imagePreview" size="x-small" variant="text" color="error" class="mt-1"
                                        @click="removeImage">
                                        Eliminar imagen
                                    </v-btn>
                                    <div class="text-caption text-medium-emphasis">
                                        Tamaño recomendado 1200x800px · Máximo 2MB · Formatos JPG, PNG
                                    </div>
                                </v-col>
                                <v-col cols="6">
                                    <FormDateTimePicker v-model="form.publish_at" label="Fecha de publicación" :rules="[required, publishNotInPast]" />
                                </v-col>
                                <v-col cols="6">
                                    <FormDateTimePicker 
                                        v-model="form.expires_at" 
                                        label="Fecha de expiración"
                                        prepend-inner-icon="mdi-calendar-remove" 
                                        :error="!!form.errors.expires_at"
                                        :error-messages="form.errors.expires_at"
                                        :rules="[required, expiresAfterPublish]"/>
                                </v-col>
                                <v-col cols="12">
                                    <v-select
                                        v-model="form.status"
                                        label="Estado"
                                        :items="[
                                            { title: 'Borrador', value: 'draft' },
                                            { title: 'Publicado', value: 'published' }
                                        ]"
                                        item-title="title"
                                        item-value="value"
                                    />
                                </v-col>
                            </v-row>
                        </v-card-text>
                        <v-card-actions>
                            <v-spacer />
                            <BaseButton :text="'Cancelar'" variant="tonal" action="cancel" @click="showModal = false"  :icon-only="false" />
                            <BaseButton :text="form.id ? 'Actualizar' : 'Guardar'" variant="flat" action="save"
                                type="submit"  :icon-only="false" />
                        </v-card-actions>
                    </v-card>
                </v-form>
            </v-dialog>
            <v-dialog v-model="showGalleryModal" max-width="700" persistent>
                <v-card title="Galería de imágenes">
                    <v-card-text>
                        <v-file-input v-model="galleryForm.images"
                            multiple
                            accept="image/*"
                            label="Subir imágenes"
                            prepend-icon="mdi-image-multiple"
                            show-size
                            chips
                            @update:model-value="handleImagesSelected"
                        />
                        <v-alert
                            type="info"
                            variant="tonal"
                            density="compact"
                            class="mt-2"
                            icon="mdi-information-outline">
                            2MB por archivo • formatos JPG, PNG o WEBP
                        </v-alert>
                <!-- preview nuevas -->
                <div class="d-flex flex-wrap mt-3">
                    <v-card v-for="(img,index) in galleryForm.images"
                        :key="'new'+index"
                        class="ma-2 position-relative"
                        width="110">
                        <v-img :src="previewURL(img)"
                            height="90"
                            cover/>
                        <v-btn
                            icon="mdi-close"
                            size="x-small"
                            color="error"
                            class="position-absolute top-0 right-0"
                            @click="removeNewImage(index)"/>
                    </v-card>
                </div>
                <!-- imágenes existentes -->
                <div class="d-flex flex-wrap mt-4">
                    <v-card v-for="img in galleryForm.existing_images"
                        :key="img.id"
                        class="ma-2 position-relative"
                        width="110">
                        <v-img :src="img.image_url"
                            height="90"
                            cover/>
                        <v-btn
                            icon="mdi-close"
                            size="x-small"
                            color="error"
                            class="position-absolute top-0 right-0"
                            @click="removeExistingImage(img)"
                        />
                    </v-card>
                </div>
                <div class="d-flex flex-wrap mt-3">
                    <v-chip
                        size="small"
                        color="indigo"
                        class="mb-2"
                    >
                    {{ galleryForm.existing_images.length + galleryForm.images.length }}
                    </v-chip>
                    <v-progress-linear
                        v-if="loadingGallery"
                        indeterminate
                        color="primary"
                        class="mb-2"/>
                </div>
                </v-card-text>
                    <v-card-actions>
                        <v-spacer/>
                        <BaseButton text="Cancelar" action="cancel" variant="flat" @click="showGalleryModal=false" :icon-only="false" />
                        <BaseButton :text="'Guardar'" action="save" @click="saveGallery" :icon-only="false" :disabled="totalImages > 5 || !hasGalleryChanges" variant="flat"/>
                    </v-card-actions>
                </v-card>
            </v-dialog>
        </AppLayout>
    </template>