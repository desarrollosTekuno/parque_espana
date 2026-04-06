    <script setup lang="ts">

    import '@/../css/amenities.css';
    import BaseButton from "@/Components/BaseButton.vue";
    import FormDescripcion from "@/Components/Form/FormDescripcion.vue";
    import FormImage from "@/Components/Form/FormImage.vue";
    import FormName from "@/Components/Form/FormName.vue";
    import { required, maxLength } from "@/constants/validationRules";
    import AppLayout from "@/Layouts/AppLayout.vue";
    import { customConfirmSwal, customToastSwal } from "@/utils/swal";
    import { Head, router, useForm, usePage } from "@inertiajs/vue3";
    import { debounce } from "lodash";
    import { ref, watch, computed, onMounted  } from "vue";

    const page = usePage();
    const can = page.props.auth.permissions;

    interface Props {
        announcements?: any;
        amenities?: any;
        resources?: any;
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
        summary: "",
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
        ends_at: null
    });
    const showEventFields = computed(() => {
        return form.type === "torneo"
            || form.type === "evento";
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
        return val.replace(" ", "T").slice(0, 16);
    };
    const edit = (item: any) => {
        form.reset();
        form.id = item.id;
        form.title = item.title;
        form.summary = item.summary;
        form.content = item.content;
        form.type = item.type;
        form.publish_at = formatDateForInput(item.publish_at);
        form.expires_at = formatDateForInput(item.expires_at);
        form.is_active = item.is_active;
        form.resource_id = item.detail?.resource_id ?? null;
        form.capacity = item.detail?.capacity ?? null;
        form.starts_at = formatDateForInput(item.detail?.starts_at);
        form.ends_at = formatDateForInput(item.detail?.ends_at);
        form.image = null;
        form.image_path = item.image;
        imagePreview.value = item.image ? `/storage/${item.image}` : null;
        showModal.value = true;
    };
    const save = () => {
        formSendRef.value
            ?.validate()
            .then(({ valid }) => {
                if (!valid) return;
                form.transform((data: any) => {
                    const normalizeDate = (val:any) => {
                    return val ? val.replace("T"," ") + ":00" : null;
                };
                let payload:any = {
                    ...data,
                    publish_at: normalizeDate(data.publish_at),
                    expires_at: normalizeDate(data.expires_at),
                    starts_at: normalizeDate(data.starts_at),
                    ends_at: normalizeDate(data.ends_at),
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
                                icon: "error"
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
    const formatDateTable = (val: string | null) => {
        if (!val) return "-";
        const normalized = val.replace("T", " ");
        const parts = normalized.split(" ");
        if (parts.length < 2) return val;
        const [datePart, timePart] = parts;
        const [year, month, day] = datePart.split("-");
        const [hour, minute] = timePart.split(":");
        return `${day}/${month}/${year} ${hour}:${minute}`;
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
        {title: "Locación", key: "resource" },
        {title: "Imagen",key: "image"},
        {title: "Publicación",key: "publish_at"},
        {title: "Activo",key: "is_active"},
        {title: "Acciones",key: "actions",sortable: false}
    ];
    const typeLabel: any = {
        comunicado: "Comunicado",
        torneo: "Torneo",
        evento: "Evento",
        info_parque: "Info parque"
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
    const endsAfterStart = (value:any) => {
        if (!value || !form.starts_at) return true;

        return new Date(value) >= new Date(form.starts_at)
            || "La fecha de fin no puede ser menor que la de inicio";
    };
    const notPastDate = (value:any) => {
        if (!value) return true;
        return new Date(value) >= new Date()
            || "No puedes seleccionar una fecha pasada";
    };
    const expiresAfterPublish = (value:any) => {
        if (!value || !form.publish_at) return true;
        return new Date(value) > new Date(form.publish_at)
            || "Debe ser mayor que la fecha de publicación";
    };
    const startAfterPublish = (value:any) => {
        if (!value || !form.publish_at) return true;
        return new Date(value) >= new Date(form.publish_at)
            || "Debe ser posterior a la publicación";
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
watch(
    () => form.image,
    (file) => {
        if (file instanceof File) {
            imagePreview.value =
                URL.createObjectURL(file);
            form.remove_image = false;
        } 
    }
);
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
                <v-data-table-server fixed-header hover height="500px" :headers="headers" :items="items"
                    :items-length="total" :loading="loading" v-model:options="options" class="elevation-1"
                    :items-per-page-options="[10, 25, 50, 100]" items-per-page-text="Mostrar"
                    no-data-text="No hay anuncios">
                    <template #top>
                        <v-text-field v-model="search" label="Buscar anuncio..." prepend-inner-icon="mdi-magnify"
                            variant="outlined" density="comfortable" class="mx-4 mt-4" />
                    </template>
                    <template #item.resource="{ item }">
                        {{ item.detail?.resource?.amenity?.name }}
                        -
                        {{ item.detail?.resource?.name }}
                    </template>
                    <template #item.image="{ item }">
                        <v-img v-if="item.image" :src="`/storage/${item.image}`" max-height="70" max-width="100"
                            class="rounded" />
                    </template>
                    <template #item.type="{ item }">
                        <v-chip size="small" variant="tonal">
                            {{ typeLabel[item.type] }}
                        </v-chip>
                    </template>
                    <template #item.publish_at="{ item }">
                        {{ formatDateTable(item.publish_at) }}
                    </template>
                    <template #item.is_active="{ item }">
                        <v-chip size="small" :color="item.is_active ? 'green' : 'red'">
                            {{ item.is_active ? 'Activo' : 'Inactivo' }}
                        </v-chip>
                    </template>
                    <template #item.actions="{ item }">
                        <BaseButton action="edit" @click="edit(item)" v-if="can.includes('announcements.update')" />
                        <BaseButton action="delete" @click="destroy(item)" v-if="can.includes('announcements.destroy')" />
                    </template>
                </v-data-table-server>
            </div>
            <v-dialog v-model="showModal" max-width="650" persistent>
                <v-form ref="formSendRef" @submit.prevent="save">
                    <v-card :title="form.id ? 'Editar anuncio' : 'Nuevo anuncio'">
                        <v-card-text style="max-height:70vh; overflow:auto">
                            <v-row>
                                <v-col cols="12">
                                    <FormName v-model="form.title" label="Título" :rules="[required, maxLength(150)]" />
                                </v-col>
                                <v-col cols="12">
                                    <FormDescripcion v-model="form.summary" label="Resumen" rows="2" />
                                </v-col>
                                <v-col cols="12">
                                    <FormDescripcion v-model="form.content" label="Contenido" rows="4" auto-grow />
                                </v-col>
                                <v-col cols="12">
                                    <v-select v-model="form.type" label="Tipo de anuncio" prepend-inner-icon="mdi-shape"
                                        :items="[
                                            { title: 'Comunicado', value: 'comunicado' },
                                            { title: 'Torneo', value: 'torneo' },
                                            { title: 'Evento', value: 'evento' },
                                            { title: 'Información del parque', value: 'info_parque' }
                                        ]" item-title="title" item-value="value" :rules="[required]" />
                                </v-col>
                                <v-col cols="12">
                                    <FormImage v-model="form.image" label="Imagen" ref="imageRef" />
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
                                </v-col>
                                <template v-if="showEventFields">
                                    <v-col cols="6">
                                        <v-select v-model="form.resource_id" label="Locación"
                                            prepend-inner-icon="mdi-map-marker" :items="resourcesList" item-title="name"
                                            item-value="id" :rules="showEventFields ? [required] : []" />
                                    </v-col>
                                    <v-col cols="6">
                                        <v-text-field v-model="form.capacity" label="Capacidad" type="number"
                                            prepend-inner-icon="mdi-account-group" />
                                    </v-col>
                                    <v-col cols="6">
                                        <v-text-field v-model="form.starts_at" label="Inicio" type="datetime-local"
                                            prepend-inner-icon="mdi-calendar" :rules="showEventFields ? [required, notPastDate, startAfterPublish] : []" :error-messages="form.errors.starts_at"/>
                                    </v-col>
                                    <v-col cols="6">
                                        <v-text-field v-model="form.ends_at" label="Fin" type="datetime-local"
                                            prepend-inner-icon="mdi-calendar-check" :rules="showEventFields ? [required, notPastDate, endsAfterStart] : []" :error-messages="form.errors.ends_at"/>
                                    </v-col>
                                </template>
                                <v-col cols="6">
                                    <v-text-field v-model="form.publish_at" label="Fecha publicación" type="datetime-local"
                                        prepend-inner-icon="mdi-calendar" :rules="[required, notPastDate]" />
                                </v-col>
                                <v-col cols="6">
                                    <v-text-field v-model="form.expires_at" label="Fecha expiración" type="datetime-local"
                                        prepend-inner-icon="mdi-calendar-remove" :rules="[required, notPastDate, expiresAfterPublish]"/>
                                </v-col>
                                <v-col cols="6" v-if="form.id">
                                    <v-switch v-model="form.is_active" color="green"
                                        :label="form.is_active ? 'Activo' : 'Inactivo'" inset />
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
        </AppLayout>
    </template>