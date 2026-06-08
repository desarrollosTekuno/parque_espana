<script setup lang="ts">

import BaseButton from "@/Components/BaseButton.vue";
import FormDescripcion from "@/Components/Form/FormDescripcion.vue";
import { required } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { ref, watch, computed } from "vue";
import { debounce } from "lodash";

const page = usePage();
const can = page.props.auth.permissions;

interface Props {
    blockedPeriods?: any;
    resources?: any;
}

const props = defineProps<Props>();
const resourcesList = ref(props.resources ?? []);
const showModal = ref(false);
const formSendRef = ref();

const form = useForm({
    id: null,
    club_id: page.props.auth.currentClub,
    resource_id: null,
    reason: "",
    start_time: null,
    end_time: null,
});

const resourcesGrouped = computed(()=>{
    if(!resourcesList.value?.length)
        return [];
    const sorted = [...resourcesList.value]
        .sort((a:any,b:any)=>{
            const amenityA =
                a.amenity?.name ?? "";
            const amenityB =
                b.amenity?.name ?? "";
            if(amenityA === amenityB){
                return a.name.localeCompare(b.name);
            }
            return amenityA.localeCompare(amenityB);
        });
    const result:any[] = [];
    let currentAmenity = null;
    for(const r of sorted){
        const amenityName =
            r.amenity?.name
            ?? "Sin amenidad";
        // agregar header
        if(currentAmenity !== amenityName){
            result.push({
                type: "header",
                title: amenityName
            });
            currentAmenity = amenityName;
        }
        // agregar item
        result.push({
            type: "item",
            title: r.name,
            value: r.id
        });
    }
    return result;
});

const create = () => {
    form.reset();
    showModal.value = true;
};

const edit = (item:any) => {
    form.reset();
    form.id = item.id;
    form.resource_id = item.resource_id;
    form.reason = item.reason;
    form.start_time = formatDateForInput(item.start_time);
    form.end_time = formatDateForInput(item.end_time);
    showModal.value = true;
};

const savingBlocked = ref(false)

const save = async () => {

    const { valid } = await formSendRef.value?.validate()
    if (!valid) return

    const result = await customConfirmSwal({
        title: form.id 
            ? "¿Actualizar bloqueo?" 
            : "¿Crear bloqueo?",
        text: "Se guardará el periodo bloqueado"
    })

    if (!result.isConfirmed) return

    if (savingBlocked.value) return
    savingBlocked.value = true

    form
        .transform((data: any) => {

            const normalize = (val: any) =>
                val ? val.replace("T", " ") + ":00" : null

            let payload: any = {
                ...data,
                start_time: normalize(data.start_time),
                end_time: normalize(data.end_time)
            }

            if (form.id) {
                payload._method = "PUT"
            }

            return payload
        })
        .post(
            form.id
                ? route("blockedPeriods.update", form.id)
                : route("blockedPeriods.store"),
            {
                onSuccess: () => {

                    customToastSwal({
                        title: page.props.flash.success,
                        icon: "success",
                    })

                    showModal.value = false
                    fetchItems()

                    savingBlocked.value = false
                },

                onError: () => {

                    const firstError = Object.values(form.errors)[0]

                    customToastSwal({
                        title: "Horario no disponible",
                        text: firstError,
                        icon: "error",
                        timer: 8000
                    })

                    savingBlocked.value = false
                }
            }
        )
};

const destroy = (item:any) => {
    customConfirmSwal({
        title:"¿Eliminar bloqueo?"
    })
    .then(r=>{
        if(r.isConfirmed){
            router.delete(
                route("blockedPeriods.destroy",item.id),
                {
                    onSuccess:()=>{
                        customToastSwal({
                            title: page.props.flash.success,
                            icon:"success"
                        });
                        fetchItems();
                    }
                }
            );
        }
    });
};
// ── Validaciones de fechas ────────────────────────────────────────────────────
const nowDatetime = computed(() => {
    const now = new Date()
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`
})

const startTimeRules = computed(() => [
    (v: string | null) => !!v || 'La fecha de inicio es requerida',
    (v: string | null) => {
        if (!v || form.id) return true
        return v >= nowDatetime.value || 'No se pueden bloquear fechas pasadas'
    },
])

const endTimeRules = computed(() => [
    (v: string | null) => !!v || 'La fecha de fin es requerida',
    (v: string | null) => {
        if (!v) return true
        if (form.start_time && v <= form.start_time)
            return 'La fecha de fin debe ser posterior a la de inicio'
        if (!form.id && v < nowDatetime.value)
            return 'No se pueden bloquear fechas pasadas'
        return true
    },
])

const formatDateForInput = (val:string|null)=>{
    if(!val) return null;
    return val.replace(" ","T").slice(0,16);
};
const formatDateTable = (val: string | null) => {
    if (!val) return "-";

    const [datePart, timePart] = val.split('T');
    const [y, m, d] = datePart.split('-');
    const [h, min] = timePart.split(':');

    return `${d}/${m}/${y} ${h}:${min}`;
};

const headers = [
    {title:"Recurso",key:"resource"},
    {title:"Motivo",key:"reason"},
    {title:"Inicio",key:"start_time"},
    {title:"Fin",key:"end_time"},
    {title:"Acciones",key:"actions"}
];
const items = ref([]);
const total = ref(0);
const loading = ref(false);
const options = ref({
    page:1,
    itemsPerPage:10,
    sortBy:[{
        key:"id",
        order:"desc"
    }]
});
const search = ref("");
const fetchItems = ()=>{
    loading.value=true;
    router.get(
        route("blockedPeriods.index"),

        {
            page: options.value.page,
            per_page: options.value.itemsPerPage,
            search: search.value
        },

        {
            preserveState:true,
            replace:true,
            only:["blockedPeriods"]
        }
    );
};
watch(
    ()=>props.blockedPeriods,
    (val)=>{
        items.value = val?.data ?? [];
        total.value = val?.total ?? 0;
        loading.value=false;
    },
    { immediate:true }
);
watch(
    [options,search],
    debounce(fetchItems,400),
    { deep:true }

);
console.log('BlockedPeriods:', props.blockedPeriods)
</script>
<template>
    <Head title="Bloqueos" />
    <AppLayout>
        <template #options>
            <BaseButton v-if="can.includes('blockedPeriods.store')" :text="'Nuevo bloqueo'" action="add" @click="create" :icon-only="false" variant="elevated" />
        </template>
        <template #header>
            Bloqueo de horarios
        </template>
        <v-data-table-server :headers="headers" :items="items" :items-length="total" :loading="loading"
             loading-text="Cargando bloqueos..." v-model:options="options" class="elevation-1" no-data-text="No hay bloqueos registrados">
            <template #top>
                <v-text-field v-model="search" label="Buscar bloqueo" />
            </template>
            <template #item.resource="{ item }">
                {{ item.resource?.amenity?.name }}
                -
                {{ item.resource?.name }}
            </template>
            <template #item.start_time="{ item }">
                {{ formatDateTable(item.start_time) }}
            </template>
            <template #item.end_time="{ item }">
                {{ formatDateTable(item.end_time) }}
            </template>
            <template #item.actions="{ item }">
                <BaseButton v-if="can.includes('blockedPeriods.update')" action="edit" @click="edit(item)" />
                <BaseButton v-if="can.includes('blockedPeriods.destroy')" action="delete" @click="destroy(item)" />
            </template>
        </v-data-table-server>
        <v-dialog v-model="showModal" max-width="600">
            <v-form ref="formSendRef" @submit.prevent="save">
                <v-card title="Bloqueo de horario">
                    <v-card-text>
                        <v-row>
                            <v-col cols="12">
                                <v-select v-model="form.resource_id"
                                    label="Recurso"
                                    :items="resourcesGrouped"
                                    item-title="title"
                                    item-value="value"
                                    :rules="[required]">
                                    <template #item="{ props, item }">
                                        <!-- header de amenidad -->
                                        <v-list-subheader
                                            v-if="item.raw.type === 'header'"
                                        >
                                            {{ item.raw.title }}
                                        </v-list-subheader>
                                        <!-- recurso -->
                                        <v-list-item
                                            v-else
                                            v-bind="props"
                                        />
                                    </template>
                                </v-select>
                            </v-col>
                            <v-col cols="12">
                                <FormDescripcion v-model="form.reason" label="Motivo" :rules="[required]" />
                            </v-col>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="form.start_time"
                                    type="datetime-local"
                                    label="Inicio"
                                    :min="!form.id ? nowDatetime : undefined"
                                    :rules="startTimeRules"
                                />
                            </v-col>
                            <v-col cols="6">
                                <v-text-field
                                    v-model="form.end_time"
                                    type="datetime-local"
                                    label="Fin"
                                    :min="form.start_time ?? nowDatetime"
                                    :rules="endTimeRules"
                                />
                            </v-col>
                        </v-row>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer />
                        <BaseButton :text="'Cancelar'" :icon-only="false" action="cancel" @click="showModal = false" variant="elevated" />
                        <BaseButton :text="form.id ? 'Actualizar' : 'Guardar'" :icon-only="false" action="save" type="submit" variant="elevated" />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>