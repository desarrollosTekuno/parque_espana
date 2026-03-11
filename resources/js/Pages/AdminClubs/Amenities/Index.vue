<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import FormDescripcion from "@/Components/Form/FormDescripcion.vue";
import FormIcon from "@/Components/Form/FormIcon.vue";
import FormImage from "@/Components/Form/FormImage.vue";
import FormName from "@/Components/Form/FormName.vue";
import FormNumber from "@/Components/Form/FormNumber.vue";
import { required, maxLength } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Form, Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch, computed } from "vue";

const page = usePage();
const can = usePage().props.auth.permissions;
const imageRef = ref<any>(null);
const iconRef = ref<any>(null);

const isSaveDisabled = computed(() => {
const imageInvalid =
      imageRef.value &&
      form.background_image &&
      imageRef.value.isValid === false;

const iconInvalid =
      iconRef.value &&
      form.icon &&
      iconRef.value.isValid === false;
    return imageInvalid || iconInvalid;
});
interface Props {
    amenities?: any;
}

interface Amenity {
    id: number | null;
    name: string;
    icon: File | null;
    icon_path?: string | null;
    remove_icon: boolean;
    background_image: File | null;
    background_image_path?: string | null;
    remove_background_image: boolean;
    description: string;
    reservation_type: string;
    capacity: number | null;
    is_active: boolean;
    slot_duration_minutes: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    amenities: null,
});

let showModal = ref(false);
const formSendRef = ref();
const imagePreview = ref<string | null>(null);
const iconPreview = ref<string | null>(null);

const form = useForm<Amenity>({
    id: null,
    name: "",
    icon: null,
    icon_path: null,
    remove_icon: false,
    background_image: null,
    background_image_path: null,
    remove_background_image: false,
    description: "",
    reservation_type: null,
    capacity: null,
    is_active: true,
    slot_duration_minutes: null,
});

const create = () => {
    form.reset();
    imagePreview.value = null;
    iconPreview.value = null;
    showModal.value = true;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid }) => {
        if (!valid) return;
        if (form.id) {
            form
                .transform((data) => ({
                    ...data,
                    _method: "PUT"
                }))
                .post(route("amenities.update", form.id), {
                    forceFormData: true,
                    onSuccess: () => {
                        customToastSwal({
                            title: "Amenidad actualizada con éxito!",
                            icon: "success"
                        });
                        showModal.value = false;
                        form.reset();
                        form.transform(data => data);
                        imagePreview.value = null;
                        iconPreview.value = null;
                        fetchItems();
                    }
                });
        } else {
            form
                .transform(data => data) 
                .post(route("amenities.store"), {

                    forceFormData: true,

                    onSuccess: () => {

                        customToastSwal({
                            title: "Amenidad creada con éxito!",
                            icon: "success"
                        });

                        showModal.value = false;
                        form.reset();
                        imagePreview.value = null;
                        iconPreview.value = null;

                        fetchItems();
                    }
                });

        }

    });

};

const edit = (data: any) => {
    form.id = data.id;
    form.name = data.name;
    form.description = data.description;
    form.reservation_type = data.reservation_type;
    form.capacity = data.capacity;
    form.is_active = data.is_active;
    form.slot_duration_minutes = data.slot_duration_minutes;
    form.icon = null;
    form.icon_path = data.icon || null;
    form.remove_icon = false;
    form.background_image = null;
    form.remove_background_image = false;
    form.background_image_path = data.background_image || null;

    iconPreview.value = data.icon ? `/storage/${data.icon}` : null;
    imagePreview.value = data.background_image ? `/storage/${data.background_image}` : null;
    showModal.value = true;
};

const destroy = (data: any) => {
    customConfirmSwal({ title: "¿Está segur@ que desea eliminar este registro?" }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("amenities.destroy", data.id), {
                onSuccess: () => {
                    customToastSwal({ title: "Registro eliminado correctamente", icon: "success" });
                    fetchItems();
                },
            });
        }
    });
};

const close = () => {
    form.reset();
    iconPreview.value = null;
    imagePreview.value = null;
    showModal.value = false;
};

watch(() => form.icon, (file) => {
    if (file instanceof File) {
        iconPreview.value = URL.createObjectURL(file);
    } else if (file && form.icon_path) {
        iconPreview.value = `/storage/${form.icon_path}`;
    } else {
        iconPreview.value = null;
    }
});

watch(() => form.background_image, (file) => {
    if (file instanceof File) {
        imagePreview.value = URL.createObjectURL(file);
        form.remove_background_image = false;
    } else if (file === null) {
        imagePreview.value = null;
        form.background_image_path = null;
        form.remove_background_image = true;
    }

});

const headers = [
    { title: "ID", key: "id" },
    { title: "Nombre", key: "name" },
    { title: "Icono", key: "icon", sortable: false },
    { title: "Imagen", key: "background_image", sortable: false },
    { title: "Capacidad", key: "capacity" },
    { title: "Activo", key: "is_active" },
    { title: "Acciones", key: "actions", sortable: false },
];

const items = ref([]);
const total = ref(0);
const loading = ref(false);
const search = ref("");
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [{ key: "id", order: "desc" }] });
const prefix = "amenities";

const fetchItems = async () => {
    loading.value = true;
    const params = {
        club_id: page.props.auth.currentClub,
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "id",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "desc",
    };
    router.get(route("amenities.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            items.value = page.props[prefix]?.data ?? [];
            total.value = page.props[prefix]?.total ?? 0;
            loading.value = false;
        },
    });
};

const removeBackgroundImage = () => {
    form.background_image = null
    form.background_image_path = null
    form.remove_background_image = true
    imagePreview.value = null
}

const removeIcon = () => {
    form.icon = null
    form.icon_path = null
    form.remove_icon = true
    iconPreview.value = null
}

watch([options, search], debounce(fetchItems, 400), { deep: true });
watch(() => page.props.auth.currentClub, () => {
    fetchItems();
});

</script>

<template>
    <Head title="Amenidades" />
    <AppLayout>
        <template #header>Amenidades</template>
        <template #options>
            <BaseButton variant="elevated" :icon-only="false" @click="create" action="add" />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-row><v-col cols="12">
                    <v-data-table-server fixed-header hover height="500px" :headers="headers" :items="items"
                        :items-length="total" :loading="loading" v-model:options="options" class="elevation-1"
                        :items-per-page-options="[10, 25, 50, 100]" items-per-page-text=" Mostrar"
                        no-data-text="No hay registros para mostrar">

                        <template #top>
                            <v-text-field v-model="search" label="Buscar amenidad" class="mx-4 mt-2" clearable />
                        </template>

                        <template #item.icon="{ item }">
                            <v-img v-if="item.icon" :src="`/storage/${item.icon}`"
                                max-height="30" max-width="30" class="rounded-lg" />
                        </template>

                        <template #item.background_image="{ item }">
                            <v-img v-if="item.background_image" :src="`/storage/${item.background_image}`"
                                max-height="80" max-width="80" class="rounded-lg" />
                        </template>

                        <template #item.is_active="{ item }">
                            <v-chip :color="item.is_active ? 'green' : 'red'" dark>
                                {{ item.is_active ? 'Activo' : 'Inactivo' }}
                            </v-chip>
                        </template>

                        <template #item.actions="{ item }">
                            <BaseButton action="edit" @click="edit(item)"
                                v-if="can.includes('amenities.update')" />
                            <BaseButton action="delete" @click="destroy(item)"
                                v-if="can.includes('amenities.destroy')" />
                        </template>

                    </v-data-table-server>
                </v-col></v-row>
        </div>

        <v-dialog v-model="showModal" max-width="600" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card :title="`${form.id ? 'Editar Amenidad' : 'Nueva Amenidad'}`">
                    <v-card-text class="overflow-y-auto h-full">

                        <v-col cols="12">
                            <FormName 
                                v-model="form.name" 
                                label="Nombre" 
                                :rules="[required, maxLength(50)]" />
                        </v-col>
                        <v-col cols="12">
                            <FormIcon 
                                v-model="form.icon" 
                                label="Icono"
                                ref="iconRef" />
                        </v-col>
                        <v-col cols="12" v-if="iconPreview">
                            <v-img 
                                :src="iconPreview" 
                                max-width="80"
                                max-height="80" 
                                cover
                                class="rounded-lg" 
                            />
                            <v-btn
                                color="error"
                                size="small"
                                @click="removeIcon"
                                >
                                Eliminar imagen
                            </v-btn>
                        </v-col>
                        <v-col cols="12">
                            <FormImage 
                                v-model="form.background_image" 
                                label="Imagen de fondo"
                                ref="imageRef" />
                        </v-col>
                        <v-col cols="12" v-if="imagePreview">
                            <v-img 
                                :src="imagePreview" 
                                max-height="200" cover
                                class="rounded-lg" 
                            />
                            <v-btn
                                color="error"
                                size="small"
                                @click="removeBackgroundImage"
                                >
                                Eliminar imagen
                            </v-btn>
                        </v-col>
                        <v-col cols="12">
                            <FormDescripcion 
                                v-model="form.description" 
                                label="Descripción" rows="3" 
                                :required="false"
                                :min-length="0"
                                auto-grow 
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-select 
                                v-model="form.reservation_type"  
                                prepend-inner-icon="mdi-calendar-check"
                                label="Tipo de reserva"
                                placeholder=" "
                                :items="[
                                    { title: 'Uso exclusivo (1 reserva por horario)', value: 'exclusive' },
                                    { title: 'Por capacidad (múltiples reservas por horario)', value: 'capacity_based' }
                                ]" 
                                item-title="title" 
                                item-value="value" 
                                :rules="[required]"
                            />
                        </v-col>
                        <v-col cols="12">
                            <FormNumber
                                v-model="form.capacity" 
                                label="Capacidad"
                                :min="0"
                            />
                        </v-col>
                        <v-col cols="12">
                            <FormNumber
                                v-model="form.slot_duration_minutes"
                                label="Espacio de reserva en minutos"
                                :min="0"
                            />
                        </v-col>
                        <v-col cols="12">
                            <v-switch 
                                v-model="form.is_active" 
                                color="green"
                                :label="form.is_active ? 'Activo' : 'Inactivo'" 
                                hide-details inset 
                                :rules="[required]"
                            />
                        </v-col>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer></v-spacer>
                        <BaseButton :text="'Cancelar'" variant="tonal" :icon-only="false" action="cancel"
                            @click="close" />
                        <BaseButton :text="form.id ? 'Actualizar' : 'Guardar'" variant="flat" :icon-only="false"
                            type="submit" action="save" :disabled="isSaveDisabled" />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>