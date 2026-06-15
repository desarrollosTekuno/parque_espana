<script setup lang="ts">
import BaseButton from "@/Components/BaseButton.vue";
import { required } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import { ref, watch } from "vue";

const page = usePage();
const can  = page.props.auth.permissions;

interface Props {
    classSchedules?: any;
    coaches?:         any[];
    amenityResources?: any[];
}

const props = defineProps<Props>();
const showModal   = ref(false);
const formSendRef = ref();
const saving      = ref(false);

const DAYS = [
    { label: "Lunes",     value: 1 },
    { label: "Martes",    value: 2 },
    { label: "Miércoles", value: 3 },
    { label: "Jueves",    value: 4 },
    { label: "Viernes",   value: 5 },
    { label: "Sábado",    value: 6 },
    { label: "Domingo",   value: 0 },
];

const TYPES = [
    { label: "Adultos", value: "adults" },
    { label: "Niños",   value: "kids" },
];

const form = useForm({
    id:                  null as number | null,
    name:                "",
    type:                "adults" as string,
    coach_id:            null as number | null,
    amenity_resource_id: null as number | null,
    day_of_week:         null as number | null,
    start_time:          "",
    end_time:            "",
    capacity:            1,
    is_active:           true,
});

const create = () => {
    form.reset();
    showModal.value = true;
};

const edit = (item: any) => {
    form.reset();
    form.id                  = item.id;
    form.name                = item.name;
    form.type                = item.type;
    form.coach_id            = item.coach_id;
    form.amenity_resource_id = item.amenity_resource_id;
    form.day_of_week         = item.day_of_week;
    form.start_time          = item.start_time?.substring(0, 5) ?? "";
    form.end_time            = item.end_time?.substring(0, 5)   ?? "";
    form.capacity            = item.capacity;
    form.is_active           = item.is_active;
    showModal.value          = true;
};

const save = async () => {
    const { valid } = await formSendRef.value?.validate();
    if (!valid) return;

    if (saving.value) return;
    saving.value = true;

    const callbacks = {
        onSuccess: () => {
            customToastSwal({ title: page.props.flash.success, icon: "success" });
            showModal.value = false;
            fetchItems();
            saving.value = false;
        },
        onError: () => {
            const firstError = Object.values(form.errors)[0] as string;
            customToastSwal({ title: firstError ?? "Error al guardar", icon: "error" });
            saving.value = false;
        },
    };

    if (form.id) {
        form.put(route("classSchedules.update", form.id), callbacks);
        return;
    }

    form.post(route("classSchedules.store"), callbacks);
};

const destroy = (item: any) => {
    customConfirmSwal({ title: "¿Eliminar horario?" }).then((r) => {
        if (!r.isConfirmed) return;
        router.delete(route("classSchedules.destroy", item.id), {
            onSuccess: () => {
                customToastSwal({ title: page.props.flash.success, icon: "success" });
                fetchItems();
            },
        });
    });
};

const dayLabel  = (d: number)  => DAYS.find((x) => x.value === d)?.label  ?? "—";
const typeLabel = (t: string)  => TYPES.find((x) => x.value === t)?.label ?? t;
const coachName = (coach: any) =>
    coach
        ? `${coach.first_name} ${coach.last_name} ${coach.second_last_name ?? ""}`.trim()
        : "—";

// ── Tabla ─────────────────────────────────────────────────────────────────────
const headers = [
    { title: "Clase",      key: "name" },
    { title: "Tipo",       key: "type" },
    { title: "Entrenador", key: "coach",            sortable: false },
    { title: "Cancha",     key: "amenity_resource",  sortable: false },
    { title: "Día",        key: "day_of_week" },
    { title: "Horario",    key: "schedule",          sortable: false },
    { title: "Cupo",       key: "capacity" },
    { title: "Estado",     key: "is_active" },
    { title: "Acciones",   key: "actions",           sortable: false },
];

const items   = ref<any[]>([]);
const total   = ref(0);
const loading = ref(false);
const search  = ref("");
const options = ref({
    page:         1,
    itemsPerPage: 10,
    sortBy:       [] as any[],
});

const fetchItems = () => {
    loading.value = true;
    router.get(
        route("classSchedules.index"),
        { page: options.value.page, per_page: options.value.itemsPerPage, search: search.value },
        { preserveState: true, replace: true, only: ["classSchedules"] }
    );
};

watch(
    () => props.classSchedules,
    (val) => {
        items.value   = val?.data  ?? [];
        total.value   = val?.total ?? 0;
        loading.value = false;
    },
    { immediate: true }
);

watch([options, search], debounce(fetchItems, 400), { deep: true });
</script>

<template>
    <Head title="Horarios de clases" />
    <AppLayout>
        <template #options>
            <BaseButton
                v-if="can.includes('classSchedules.store')"
                text="Nuevo horario"
                action="add"
                :icon-only="false"
                variant="elevated"
                @click="create"
            />
        </template>
        <template #header>Horarios de clases</template>

        <v-data-table-server
            :headers="headers"
            :items="items"
            :items-length="total"
            :loading="loading"
            loading-text="Cargando horarios..."
            no-data-text="No hay horarios registrados"
            v-model:options="options"
            class="elevation-1"
        >
            <template #top>
                <v-text-field
                    v-model="search"
                    label="Buscar clase"
                    prepend-inner-icon="mdi-magnify"
                    clearable
                    hide-details
                    class="ma-2"
                />
            </template>

            <template #item.type="{ item }">
                <v-chip
                    size="small"
                    :color="item.type === 'adults' ? 'primary' : 'orange'"
                    variant="tonal"
                >
                    {{ typeLabel(item.type) }}
                </v-chip>
            </template>

            <template #item.coach="{ item }">
                {{ coachName(item.coach) }}
            </template>

            <template #item.amenity_resource="{ item }">
                {{ item.amenity_resource?.name ?? "—" }}
            </template>

            <template #item.day_of_week="{ item }">
                {{ dayLabel(item.day_of_week) }}
            </template>

            <template #item.schedule="{ item }">
                {{ item.start_time?.substring(0, 5) }} – {{ item.end_time?.substring(0, 5) }}
            </template>

            <template #item.is_active="{ item }">
                <v-chip
                    :color="item.is_active ? 'success' : 'error'"
                    variant="tonal"
                    size="small"
                >
                    {{ item.is_active ? "Activa" : "Inactiva" }}
                </v-chip>
            </template>

            <template #item.actions="{ item }">
                <BaseButton
                    v-if="can.includes('classSchedules.update')"
                    action="edit"
                    @click="edit(item)"
                />
                <BaseButton
                    v-if="can.includes('classSchedules.destroy')"
                    action="delete"
                    @click="destroy(item)"
                />
            </template>
        </v-data-table-server>

        <!-- Modal crear / editar -->
        <v-dialog v-model="showModal" max-width="600">
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card :title="form.id ? 'Editar horario' : 'Nuevo horario'">
                    <v-card-text>
                        <v-row>
                            <v-col cols="12">
                                <v-text-field
                                    v-model="form.name"
                                    label="Nombre de la clase"
                                    prepend-inner-icon="mdi-whistle-outline"
                                    :rules="[required]"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="6">
                                <v-select
                                    v-model="form.type"
                                    :items="TYPES"
                                    item-title="label"
                                    item-value="value"
                                    label="Tipo"
                                    prepend-inner-icon="mdi-account-group-outline"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="6">
                                <v-text-field
                                    v-model.number="form.capacity"
                                    label="Cupo máximo"
                                    type="number"
                                    min="1"
                                    prepend-inner-icon="mdi-account-multiple-outline"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-select
                                    v-model="form.coach_id"
                                    :items="coaches ?? []"
                                    :item-title="(c: any) => `${c.first_name} ${c.last_name} ${c.second_last_name ?? ''}`.trim()"
                                    item-value="id"
                                    label="Entrenador"
                                    prepend-inner-icon="mdi-account-star-outline"
                                    :rules="[required]"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-select
                                    v-model="form.amenity_resource_id"
                                    :items="amenityResources ?? []"
                                    item-title="name"
                                    item-value="id"
                                    label="Cancha / recurso"
                                    prepend-inner-icon="mdi-tennis-ball"
                                    :rules="[required]"
                                    clearable
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-select
                                    v-model="form.day_of_week"
                                    :items="DAYS"
                                    item-title="label"
                                    item-value="value"
                                    label="Día de la semana"
                                    prepend-inner-icon="mdi-calendar-week-outline"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="6">
                                <v-text-field
                                    v-model="form.start_time"
                                    label="Hora inicio"
                                    type="time"
                                    prepend-inner-icon="mdi-clock-start"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="6">
                                <v-text-field
                                    v-model="form.end_time"
                                    label="Hora fin"
                                    type="time"
                                    prepend-inner-icon="mdi-clock-end"
                                    :rules="[required]"
                                />
                            </v-col>

                            <v-col cols="12">
                                <v-switch
                                    v-model="form.is_active"
                                    label="Activo"
                                    color="success"
                                    hide-details
                                    inset
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />
                        <BaseButton
                            text="Cancelar"
                            :icon-only="false"
                            action="cancel"
                            variant="elevated"
                            @click="showModal = false"
                        />
                        <BaseButton
                            :text="form.id ? 'Actualizar' : 'Guardar'"
                            :icon-only="false"
                            action="save"
                            type="submit"
                            variant="elevated"
                            :loading="saving"
                        />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>
