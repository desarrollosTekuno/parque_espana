<script setup lang="ts">
import '@/../css/amenities.css';
import BaseButton from "@/Components/BaseButton.vue";
import FormDescripcion from "@/Components/Form/FormDescripcion.vue";
import FormIcon from "@/Components/Form/FormIcon.vue";
import FormImage from "@/Components/Form/FormImage.vue";
import FormName from "@/Components/Form/FormName.vue";
import FormNumber from "@/Components/Form/FormNumber.vue";
import TimePicker from "@/Components/TimePicker.vue";
import { required, maxLength } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Form, Head, router, useForm, usePage } from "@inertiajs/vue3";
import { mdiCalendar } from '@mdi/js';
import { debounce } from "lodash";
import { ref, watch, computed, reactive } from "vue";

const tab = ref('amenities');
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
const formSchedule = useForm({
    amenity_id: null,
    days: [
        { day: 'monday', open: null, close: null, active: false },
        { day: 'tuesday', open: null, close: null, active: false },
        { day: 'wednesday', open: null, close: null, active: false },
        { day: 'thursday', open: null, close: null, active: false },
        { day: 'friday', open: null, close: null, active: false },
        { day: 'saturday', open: null, close: null, active: false },
        { day: 'sunday', open: null, close: null, active: false },
    ]
});
const scheduleErrors = reactive<Record<string, string>>({})
const showCapacity = computed(() => {
    return form.reservation_type === 'capacity_based'
})

const activeDaysCount = computed(() => {
    return formSchedule.days.filter(day => day.active).length
})

const amenityName = ref('')

const create = () => {
    form.reset();
    imagePreview.value = null;
    iconPreview.value = null;
    showModal.value = true;
};

const save = () => {
    formSendRef.value?.validate().then(({ valid }) => {
        if (!valid) return;

        form
            .transform((data) => {

                const payload: any = { ...data };

                if (form.id) {
                    payload._method = "PUT";
                }
                if (!data.icon && !data.remove_icon) {
                    delete payload.icon;
                }

                if (!data.background_image && !data.remove_background_image) {
                    delete payload.background_image;
                }

                return payload;
            })
            .post(
                form.id
                    ? route("amenities.update", form.id)
                    : route("amenities.store"),
                {
                    forceFormData: true,
                    onSuccess: () => {

                        customToastSwal({
                            title: page.props.flash.success || "",
                            icon: "success"
                        });

                        showModal.value = false;
                        form.reset();
                        form.transform(data => data);

                        imagePreview.value = null;
                        iconPreview.value = null;

                        fetchItems();
                    },
                    onError: () => {

                        customToastSwal({
                            title: `Error: ${form.errors.messageError}`,
                            text: `${form.errors.exception}`,
                            icon: "error",
                        });

                    },
                }
            );
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
const schedule = () => {
    Object.keys(scheduleErrors).forEach(key => delete scheduleErrors[key])
    let hasError = false
    formSchedule.days.forEach(day => {
        if (!day.active) return
        if (!day.open) {
            scheduleErrors[`${day.day}_open`] = "Requerido"
            hasError = true
        }
        if (!day.close) {
            scheduleErrors[`${day.day}_close`] = "Requerido"
            hasError = true
        }
        if (day.open && day.close && day.close <= day.open) {
            scheduleErrors[`${day.day}_close`] = "Error"
            customToastSwal({
                title: "El horario de cierre debe ser mayor al de apertura",
                icon: "warning"
            })
            hasError = true
        }
    })

    if (hasError) return;

    const schedules = formSchedule.days
        .filter(day => day.active)
        .map(day => ({
            day_of_week: day.day,
            open_time: day.open,
            close_time: day.close,
            amenity_id: formSchedule.amenity_id
        }));
    router.post(route('amenitySchedule.store'), {
        schedules: schedules
    }, {
        onSuccess: () => {
            customToastSwal({
                title: "Horarios guardados correctamente",
                icon: "success"
            });

            closeScheduleModal();
            fetchItems();
        }
    });
};

const destroy = (data: any) => {
    customConfirmSwal({ title: "¿Está segur@ que desea eliminar este registro?" }).then((result) => {
        if (result.isConfirmed) {
            form.delete(route("amenities.destroy", data.id), {
                onSuccess: () => {
                    customToastSwal({ title: page.props.flash.success || "", icon: "success" });
                    fetchItems();
                },
                onError: () => {
                    customToastSwal({
                        title: `Error: ${form.errors.messageError}`,
                        text: `${form.errors.exception}`,
                        icon: "error",
                    });
                    // console.log(form.errors);
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

//   Tabla, items y fetch de amenidades    
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


//   Tabla, items y fetch de recursos    
const resourceHeaders = [
    { title: "ID", key: "id" },
    { title: "Amenidad", key: "amenity_name" },
    { title: "Nombre", key: "name" },
    { title: "Capacidad", key: "capacity" },
    { title: "Activo", key: "is_active" },
    { title: "Acciones", key: "actions", sortable: false },
];
const resourceItems = ref([]);
const resourceTotal = ref(0);
const resourceLoading = ref(false);
const fetchResources = () => {

    resourceLoading.value = true

    router.get(route('amenityResources.index'), {
        club_id: page.props.auth.currentClub
    }, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {

            resourceItems.value = page.props.resources?.data ?? []
            resourceTotal.value = page.props.resources?.total ?? 0
            resourceLoading.value = false

        }
    })

}


const showScheduleModal = ref(false);

const openScheduleModal = (amenity: any) => {

    amenityName.value = amenity.name;
    formSchedule.amenity_id = amenity.id;
    formSchedule.days.forEach(day => {
        day.active = false
        day.open = null
        day.close = null
    })

    if (amenity.schedules) {
        amenity.schedules.forEach((schedule: any) => {

            const map = {
                0: 'monday',
                1: 'tuesday',
                2: 'wednesday',
                3: 'thursday',
                4: 'friday',
                5: 'saturday',
                6: 'sunday'
            }

            const dayName = map[schedule.day_of_week]

            const day = formSchedule.days.find(d => d.day === dayName)

            if (day) {
                day.active = true
                day.open = schedule.open_time
                day.close = schedule.close_time
            }

        })
    }

    showScheduleModal.value = true
}

const closeScheduleModal = () => {
    showScheduleModal.value = false
    Object.keys(scheduleErrors).forEach(key => delete scheduleErrors[key])
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


//    Formulario de recursos y sus funciones
const showResourceModal = ref(false)
const resourceForm = useForm({
    id: null,
    amenity_id: null,
    name: "",
    capacity: 1,
    is_active: true
})
const createResource = () => {
    resourceForm.reset()
    showResourceModal.value = true

}
const editResource = (resource: any) => {
    resourceForm.id = resource.id
    resourceForm.amenity_id = resource.amenity_id
    resourceForm.name = resource.name
    resourceForm.capacity = resource.capacity
    resourceForm.is_active = resource.is_active
    showResourceModal.value = true

}
const saveResource = () => {
    resourceForm
        .transform((data) => {
            const payload: any = { ...data }

            if (resourceForm.id) {
                payload._method = "PUT"
            }
            return payload
        })
        .post(
            resourceForm.id
                ? route('amenityResources.update', resourceForm.id)
                : route('amenityResources.store'),
            {
                onSuccess: () => {
                    customToastSwal({
                        title: "Recurso guardado",
                        icon: "success"
                    })

                    showResourceModal.value = false
                    fetchResources()
                }
            })

}
const deleteResource = (item: any) => {
    customConfirmSwal({
        title: "¿Eliminar recurso?"
    }).then(result => {
        if (result.isConfirmed) {
            router.delete(route('amenityResources.destroy', item.id), {
                onSuccess: () => {
                    customToastSwal({
                        title: "Recurso eliminado",
                        icon: "success"
                    })

                    fetchResources()
                }
            })
        }
    })
}
watch([options, search], debounce(fetchItems, 400), { deep: true });
watch(() => page.props.auth.currentClub, () => {
    fetchItems();
});
watch(() => form.reservation_type, (type) => {

    if (type === 'exclusive') {
        form.capacity = null
    }

})
watch(
    () => formSchedule.days,
    (days) => {
        days.forEach(day => {
            if (day.open) delete scheduleErrors[`${day.day}_open`]
            if (day.close) delete scheduleErrors[`${day.day}_close`]
        })
    },
    { deep: true }
)
</script>

<template>

    <Head title="Amenidades" />
    <AppLayout>
        <template #header>Amenidades</template>
        <template #options>
            <BaseButton variant="elevated" :icon-only="false" @click="create" action="add"
                v-if="can.includes('amenities.store')" />
            <BaseButton v-if="tab === 'amenities' && can.includes('amenities.store')" variant="elevated"
                :icon-only="false" @click="create" action="add" label="Agregar Amenidad" />
        </template>

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <v-tabs v-model="tab" bg-color="white">
                <v-tab value="amenities">Amenidades</v-tab>
                <v-tab value="resources">Recursos</v-tab>
            </v-tabs>
            <v-window v-model="tab">
                <v-window-item value="amenities">
                    <v-row>
                        <v-col cols="12">
                            <v-data-table-server fixed-header hover height="500px" :headers="headers" :items="items"
                                :items-length="total" :loading="loading" v-model:options="options" class="elevation-1"
                                :items-per-page-options="[10, 25, 50, 100]" items-per-page-text="Mostrar"
                                no-data-text="No hay registros para mostrar">
                                <template #top>
                                    <v-text-field v-model="search" label="Buscar amenidad" class="mx-4 mt-2"
                                        clearable />
                                </template>
                                <template #item.icon="{ item }">
                                    <v-img v-if="item.icon" :src="`/storage/${item.icon}`" max-height="30"
                                        max-width="30" class="rounded-lg" />
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
                                    <BaseButton text="Agregar horario" action="add" icon="mdi-calendar"
                                        @click="openScheduleModal(item)" />

                                    <BaseButton action="edit" @click="edit(item)"
                                        v-if="can.includes('amenities.update')" />

                                    <BaseButton action="delete" @click="destroy(item)"
                                        v-if="can.includes('amenities.destroy')" />
                                </template>
                            </v-data-table-server>
                        </v-col>
                    </v-row>

                </v-window-item>
                <!-- TAB RECURSOS -->
                <v-window-item value="resources">
                    <v-row>
                        <v-col cols="12">
                            <BaseButton text="Nuevo recurso" action="add" @click="createResource" />
                            <v-data-table-server :headers="resourceHeaders" :items="resourceItems"
                                :items-length="resourceTotal" :loading="resourceLoading" class="elevation-1"
                                no-data-text="No hay recursos registrados">
                                <template #item.is_active="{ item }">
                                    <v-chip :color="item.is_active ? 'green' : 'red'">
                                        {{ item.is_active ? 'Activo' : 'Inactivo' }}
                                    </v-chip>
                                </template>
                                <template #item.actions="{ item }">
                                    <BaseButton action="edit" @click="editResource(item)" />
                                    <BaseButton action="delete" @click="deleteResource(item)" />
                                </template>
                            </v-data-table-server>
                        </v-col>
                    </v-row>
                </v-window-item>
            </v-window>
        </div>

        <v-dialog v-model="showModal" max-width="600" persistent>
            <v-form @submit.prevent="save" ref="formSendRef">
                <v-card :title="`${form.id ? 'Editar Amenidad' : 'Nueva Amenidad'}`">
                    <v-card-text class="overflow-y-auto h-full">
                        <v-row>
                            <v-col cols="12">
                                <FormName v-model="form.name" label="Nombre" :rules="[required, maxLength(50)]" />
                            </v-col>

                            <v-col cols="6">
                                <FormIcon v-model="form.icon" label="Icono" ref="iconRef" />

                                <v-card height="150" variant="outlined"
                                    class="mt-2 pa-2 d-flex flex-column align-center justify-center imagePreview">
                                    <v-img v-if="iconPreview" :src="iconPreview" width="90" height="60" cover
                                        class="rounded" />
                                    <v-icon v-else size="40" color="grey">
                                        mdi-image-outline
                                    </v-icon>

                                    <v-btn v-if="iconPreview" size="x-small" color="error" variant="text" class="mt-2"
                                        @click="removeIcon">
                                        Eliminar
                                    </v-btn>
                                </v-card>
                            </v-col>
                            <v-col cols="6">
                                <FormImage v-model="form.background_image" label="Imagen de fondo" ref="imageRef" />

                                <v-card height="150" variant="outlined"
                                    class="mt-2 d-flex flex-column align-center justify-center imagePreview">
                                    <v-img v-if="imagePreview" :src="imagePreview" height="90" width="200" cover
                                        class="rounded" />

                                    <v-icon v-else size="40" color="grey">
                                        mdi-image-outline
                                    </v-icon>

                                    <v-btn v-if="imagePreview" size="x-small" color="error" variant="text" class="mt-2"
                                        @click="removeBackgroundImage">
                                        Eliminar
                                    </v-btn>
                                </v-card>
                            </v-col>

                            <v-col cols="12">
                                <FormDescripcion v-model="form.description" label="Descripción" rows="3"
                                    :required="false" :min-length="0" auto-grow />
                            </v-col>
                            <v-col cols="12">
                                <v-select v-model="form.reservation_type" prepend-inner-icon="mdi-calendar-check"
                                    label="Tipo de reserva" placeholder=" " :items="[
                                        { title: 'Uso exclusivo (1 reserva por horario)', value: 'exclusive' },
                                        { title: 'Por capacidad (múltiples reservas por horario)', value: 'capacity_based' }
                                    ]" item-title="title" item-value="value" :rules="[required]" />
                            </v-col>
                            <!--<v-col cols="12" v-if="showCapacity">
                                <FormNumber v-model="form.capacity" label="Capacidad" :min="0" />
                            </v-col>-->
                            <v-col cols="12">
                                <FormNumber v-model="form.slot_duration_minutes" label="Espacio de reserva en minutos"
                                    :min="0" :rules="[required]" />
                            </v-col>
                            <v-col cols="12" v-if="form.id">
                                <v-switch v-model="form.is_active" color="green"
                                    :label="form.is_active ? 'Activo' : 'Inactivo'" inset />
                            </v-col>
                        </v-row>
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
        <v-dialog v-model="showScheduleModal" max-width="700" persistent>
            <v-form @submit.prevent="schedule">
                <v-card>
                    <v-card-title style="max-height: 60vh;">
                        <div class="title-container">
                            <span class="title"><v-icon size="25">mdi-calendar-clock</v-icon> Horarios de la
                                amenidad</span>
                            <span class="subtitle">
                                {{ amenityName }} · {{ activeDaysCount }} días activos
                            </span>
                        </div>
                    </v-card-title>
                    <v-card-text class="schedule-container overflow-y-auto h-full">
                        <div v-for="day in formSchedule.days" :key="day.day" class="schedule-row"
                            :class="{ inactive: !day.active }">
                            <div class="day-section">
                                <v-switch v-model="day.active" hide-details inset color="black" />
                                <span class="day-label">
                                    {{
                                        {
                                            monday: 'Lun',
                                            tuesday: 'Mar',
                                            wednesday: 'Mié',
                                            thursday: 'Jue',
                                            friday: 'Vie',
                                            saturday: 'Sáb',
                                            sunday: 'Dom'
                                        }[day.day]
                                    }}
                                </span>
                            </div>
                            <transition name="fade-slide">
                                <div v-if="day.active" class="time-section">
                                    <v-icon size="16">mdi-clock-outline</v-icon>
                                    <TimePicker v-model="day.open" label-menu="Apertura" density="compact"
                                        class="time-input custom-time" :rules="[required]"
                                        :error="!!scheduleErrors[`${day.day}_open`]"
                                        :error-messages="scheduleErrors[`${day.day}_open`]" />
                                    <span class="time-separator">-</span>
                                    <TimePicker v-model="day.close" label-menu="Cierre" density="compact"
                                        class="time-input custom-time" :rules="[required]"
                                        :error="!!scheduleErrors[`${day.day}_close`]"
                                        :error-messages="scheduleErrors[`${day.day}_close`]" />

                                </div>
                                <div v-else class="not-available">
                                    No disponible
                                </div>
                            </transition>
                        </div>
                    </v-card-text>
                    <v-card-actions class="sticky-footer">
                        <v-spacer />
                        <BaseButton :text="'Cancelar'" variant="tonal" :icon-only="false" action="cancel"
                            @click="closeScheduleModal" />
                        <BaseButton :text="'Guardar horario'" variant="flat" :icon-only="false" type="submit"
                            action="save" />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
        <v-dialog v-model="showResourceModal" max-width="500">
            <v-form @submit.prevent="saveResource">
                <v-card title="Recurso">
                    <v-card-text>
                        <v-row>
                            <v-col cols="12">
                                <v-select v-model="resourceForm.amenity_id" label="Amenidad" :item-title="'name'"
                                    :item-value="'id'" :items="items" />
                            </v-col>
                            <v-col cols="12">
                                <FormName v-model="resourceForm.name" label="Nombre" />
                            </v-col>
                            <v-col cols="12">
                                <FormNumber v-model="resourceForm.capacity" label="Capacidad" />
                            </v-col>
                            <v-col cols="12">
                                <v-switch v-model="resourceForm.is_active" label="Activo" />
                            </v-col>
                        </v-row>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer />
                        <BaseButton text="Cancelar" variant="tonal" action="cancel" @click="showResourceModal = false" />
                        <BaseButton text="Guardar" variant="flat" action="save" type="submit" />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>