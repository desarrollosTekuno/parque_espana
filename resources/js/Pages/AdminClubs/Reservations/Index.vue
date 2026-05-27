<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch, computed, nextTick } from 'vue';
import { debounce, get } from 'lodash';
import { formatDateTimeNoTZ } from '@/constants/formatDates';
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import BaseButton from "@/Components/BaseButton.vue";
import DatePicker from '@/Components/DatePicker.vue';
import AmenityCalendar from "@/Components/Amenities/AmenityCalendar.vue";
import axios from "axios";
import { Temporal } from '@js-temporal/polyfill';

const page = usePage();
const can = usePage().props.auth.permissions;
const canRole = usePage().props.auth.roles;
const showModalCancel = ref(false);
const filterDate = ref("");
const filterStatus = ref(null);
const tab = ref('table');

interface Props {
    reservations?: any;
    activeStatus?: any;
    reservationStatus?: any;
    amenities?: any[];
    resources?: any[];
    members?: any[];
    diasReserva?: number;
}

interface Reservation {
    id: number | null;
    date: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
    cancelled_at: string | null;
    club_id: string,
    amenity_id: string,
    member_id: string
}

const props = withDefaults(defineProps<Props>(), {
    reservations: null,
    activeStatus: null,
    reservationStatus: null,
    members: () => [],
    resources: () => [],
    amenities: () => [] 
});

const { members, amenities, resources } = props

//filtros calendario
const filterAmenity = ref(null)
const filterResource = ref(null)
const calendarRefresh = ref(0)

const filteredCalendarEvents = computed(() => {
  return calendarEvents.value.filter(e => {
    if (
      filterAmenity.value &&
      Number(e.amenity_id) !== Number(filterAmenity.value)
    ) return false
    if (
      filterResource.value &&
      Number(e.resource_id) !== Number(filterResource.value)
    ) return false
    return true
  })
})
const filteredCalendarResources = computed(() => {
  if (!filterAmenity.value) return resources

  return resources.filter(
    (r: any) => Number(r.amenity_id) === Number(filterAmenity.value)
  )
})
watch(filterAmenity, () => {
  filterResource.value = null
})
const calendarKey = computed(() => {
  return `${filterAmenity.value || 'all'}-${filterResource.value || 'all'}-${calendarRefresh.value}`
})
const clearCalendarFilters = () => {
  filterAmenity.value = null
  filterResource.value = null
  calendarRefresh.value++
}
// calendario
const calendarEvents = ref<any[]>([])

const toTZ = (iso: string) =>
  Temporal.Instant
    .from(iso)
    .toZonedDateTimeISO('America/Mexico_City')

const formatEvents = (events: any[]) => {
  return events.map(e => ({
    id: String(e.id),
    title: `${e.title} • ${e.status}`,

    start: toTZ(e.start), 
    end: toTZ(e.end),     

    calendarId: e.calendarId 
      ? e.calendarId 
      : `status-${e.reservation_status_id}`,
    status: e.status,
     amenity_id: e.amenity_id,
    resource_id: e.resource_id,
    amenity_name: e.amenity_name,
    resource_name: e.resource_name,

    _tooltip: `Amenidad: ${e.amenity_name || 'N/A'}\nRecurso: ${e.resource_name || 'N/A'}`
  }))
}
const fetchCalendar = async () => {
  try {
    const { data } = await axios.get(route('reservations.calendar'))
    calendarEvents.value = formatEvents(data)
    console.log(calendarEvents.value)
  } catch (e) {
    console.error(e)
        customToastSwal({
            title: 'Error al cargar el calendario',
            icon: 'error'
        })
  }
}

// cancelar desde calendario
const cancelFromCalendar = (event: any) => {
  if (event.calendarId !== `status-${props.activeStatus}`) {
    customToastSwal({
      title: 'Solo puedes cancelar reservaciones activas',
      icon: 'warning'
    })
    return
  }

  customConfirmSwal({
    title: '¿Cancelar reservación?',
    text: event.title
  }).then((result) => {
    if (!result.isConfirmed) return

    router.put(route("reservations.update", event.id), {}, {
        onSuccess: (page) => {
            const flash = page.props.flash || {}

            if (flash.messageError) {
                customToastSwal({
                    title: flash.messageError,
                    icon: "error"
                })
                return
            }

            customToastSwal({
                title: flash.success || "Reservación cancelada",
                icon: "success"
            })

            fetchCalendar()
            fetchItems()
        }
    })
  })
}

// Forms
const form = useForm<Reservation>({
    id: null,
    date: "",
    start_datetime: "",
    end_datetime: "",
    status: "",
    cancelled_at: "",
    club_id: "",
    amenity_id: "",
    member_id: ""
});

const clearFilters = () => {

    if (filterDate.value !== "" || filterStatus.value !== null) {
        filterDate.value = "";
        filterStatus.value = null;
        fetchItems();
    }
};

const cancel = (data: any) => {
    customConfirmSwal({
        title: "¿Está segur@ que desea cancelar este registro?",
        confirmButtonText: "Sí, cancelar",
        cancelButtonText: "No",
    }).then((result) => {
        if (result.isConfirmed) {
            form.put(route("reservations.update", data.id), {
                onSuccess: (page) => {
                    const flash = page.props.flash || {}
                    if (flash.messageError) {
                        customToastSwal({
                            title: flash.messageError,
                            icon: "error"
                        })
                        return
                    }
                    customToastSwal({
                        title: flash.success || "Reservación cancelada",
                        icon: "success"
                    })
                    fetchCalendar()
                    fetchItems()
                }
            });
        }
    });
};



//* INICIO DATATABLE SERVER SIDE */
// Aquí se definen los encabezados de la tabla, donde key es el nombre de la columna en la base de datos
const headers = [
    { title: "ID", key: "id" },
    { title: "Fecha Inicio", key: "start_datetime" },
    { title: "Fecha Fin", key: "end_datetime" },
    { title: "Estatus", key: "status.name" },
    { title: "Amenidad", key: "amenity.name" },
    { title: "Recurso", key: "amenity_resource.name" },
    { title: "Acciones", key: "actions", sortable: false },
];

// variables reactivas
const items = ref([]);
const total = ref(0);
const loading = ref(false);
const search = ref("");
const options = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }],
});
const prefix = "reservations";
// función para cargar datos desde Laravel
const fetchItems = async () => {
    loading.value = true;
    const params = {
        club_id: page.props.auth.currentClub,
        [`${prefix}_page`]: options.value.page,
        [`${prefix}_per_page`]: options.value.itemsPerPage,
        [`${prefix}_search`]: search.value,
        [`${prefix}_sort`]: options.value.sortBy?.[0]?.key ?? "id",
        [`${prefix}_order`]: options.value.sortBy?.[0]?.order ?? "desc",
        [`${prefix}_filter_date`]: filterDate.value,
        [`${prefix}_filter_status`]: filterStatus.value
    };

    router.get(route("reservations.index"), params, {
        preserveState: true,
        replace: true,
        onSuccess: (page) => {
            const data = page.props[prefix]?.data ?? [];
            const totalCount = page.props[prefix]?.total ?? 0;

            items.value = data;
            total.value = totalCount;
            loading.value = false;
        },
    });
};

// 🔁 Observadores con debounce para evitar muchas peticiones
watch([options, search], debounce(fetchItems, 400), { deep: true });
/* FIN DATATABLE SERVER SIDE */

watch(() => page.props.auth.currentClub, () => {
    fetchItems();
});

watch(filterDate, () => {
    options.value.page = 1;
    fetchItems();
});

watch(filterStatus, () => {
    options.value.page = 1;
    fetchItems();
    // console.log(filterStatus.value);
});

// Modal para crear una nueva reservación
const showReservationModal = ref(false)
interface Props {
    reservations?: any;
    activeStatus?: any;
    reservationStatus?: any;
    amenities?: any[];
    resources?: any[];
    members?: any[];    
}
watch(showReservationModal, (open) => {
    if (!open) {
        reservationForm.reset()
        selectedSlot.value = null
        slots.value = []
    }
})
const reservationForm = useForm({
    id: null,
    amenity_id: null,
    amenity_resource_id: null,
    member_id: null,
    date: null,
    start_datetime: null,
    end_datetime: null,
})

const createReservation = () => {
    reservationForm.reset()
    reservationForm.start_datetime = null
    reservationForm.end_datetime = null
    selectedSlot.value = null
    slots.value = []
    showReservationModal.value = true
}
const isFormValid = computed(() => {
    return (
        reservationForm.member_id &&
        reservationForm.amenity_id &&
        reservationForm.amenity_resource_id &&
        reservationForm.date &&
        reservationForm.start_datetime &&
        reservationForm.end_datetime
    )
})
const saveReservation = () => {

      if (!isFormValid.value) {
        customToastSwal({
            title: "Todos los campos son obligatorios",
            icon: "warning"
        })
        return
    }

    customConfirmSwal({
        title: "¿Crear reservación?",
        text: "Confirma para continuar"
    }).then((result) => {

        if (!result.isConfirmed) return

        reservationForm.post(route('reservations.store'), {
            onSuccess: (page) => {
                const flash = page.props.flash || {}

                if (flash.messageError) {
                    customToastSwal({
                        title: flash.messageError,
                        icon: "error"
                    })
                    return
                }

                customToastSwal({
                    title: flash.success || "Reservación creada",
                    icon: "success"
                })

                showReservationModal.value = false
                reservationForm.reset()
                fetchItems()
                fetchCalendar()
            },
            onError: (errors) => {
                customToastSwal({
                    title: errors.messageError || "Error al guardar",
                    icon: "error"
                })
            }
        })
    })
}
// filtro de recursos
const filteredResources = computed(() => {
    if (!reservationForm.amenity_id) return []
    return props.resources.filter(
        (r: any) => r.amenity_id === reservationForm.amenity_id
    )
})
watch(() => reservationForm.amenity_id, () => {
    reservationForm.amenity_resource_id = null
})

// Horarios disponibles
const slots = ref([])
const loadingSlots = ref(false)
watch(
    () => ({
        resource: reservationForm.amenity_resource_id,
        date: reservationForm.date
    }),
    async ({ resource, date }) => {

        selectedSlot.value = null
        reservationForm.start_datetime = null
        reservationForm.end_datetime = null

        if (!resource || !date) {
            slots.value = []
            return
        }

        loadingSlots.value = true

        try {
            const { data } = await axios.get(
                route('reservations.slots', resource),
                { params: { date } }
            )

            slots.value = data

        } catch (e) {
            console.error(e)
        } finally {
            loadingSlots.value = false
        }
    },
    { immediate: false }
)
const formatHour = (date: string) => {
    return new Date(date).toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    })
}

const getSlotColor = (slot: any) => {
    switch (slot.status) {
        case 'available': return 'green'
        case 'partial': return 'orange'
        case 'full': return 'red'
        case 'blocked': return 'grey'
        default: return 'grey'
    }
}

// mostrar y ocultar slots
const selectedSlot = ref<any>(null)
const selectSlot = (slot: any) => {
    // toggle (para poder deseleccionar)
    if (selectedSlot.value?.start === slot.start) {
        selectedSlot.value = null
        reservationForm.start_datetime = null
        reservationForm.end_datetime = null
        return
    }

    selectedSlot.value = slot
    reservationForm.start_datetime = slot.start
    reservationForm.end_datetime = slot.end
}
// Días permitidos para crear reservación
const today = new Date()
today.setHours(0, 0, 0, 0)
const minDate = computed(() => {
    return new Date(today)
})
const maxDate = computed(() => {
    const days = Number(props.diasReserva) || 1
    const d = new Date(today)
    d.setDate(d.getDate() + (days -1))
    return d
})
</script>

<template>
    <Head title="Dashboard"/>

    <AppLayout>
        <template #header> Reservaciones </template>
        <template #options>
            <div class="flex justify-end w-full">
                <BaseButton
                    v-if="can.includes('reservations.store')"
                    variant="elevated"
                    action="add"
                    :icon-only="false" 
                    :text="'Nueva reservación'"
                    @click="createReservation()"
                />
            </div>
        </template>

        <div class="pa-4 bg-grey-lighten-4 rounded-xl mt-5">
            <!-- TABS -->
            <v-tabs v-model="tab" grow>
                <v-tab value="table">
                    <v-icon start>mdi-table</v-icon>
                    Tabla
                </v-tab>

                <v-tab value="calendar" @click="fetchCalendar">
                    <v-icon start>mdi-calendar</v-icon>
                    Calendario
                </v-tab>
            </v-tabs>

            <v-window v-model="tab">
                <!-- TAB TABLA -->
                <v-window-item value="table">
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg mt-4 pa-4">
                        <v-row>
                            <v-col cols="12" md="5" class="pb-0">
                                <DatePicker
                                    v-model="filterDate"
                                    :showIcon="false"
                                    class="w-full"
                                />
                            </v-col>
                            <v-col cols="12" md="5" class="pb-0">
                                <v-select
                                    v-model="filterStatus"
                                    :items="reservationStatus"
                                    label="Estatus"
                                    item-title="text"
                                />
                            </v-col>
                            <v-col cols="12" md="2" class="d-flex justify-center align-start pb-0">
                                <BaseButton
                                    variant="elevated"
                                    @click="clearFilters()"
                                    color="blue"
                                    text="Limpiar"
                                    icon="mdi-filter-off"
                                />
                            </v-col>
                        </v-row>
                    </div>
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg mt-4">
                        <v-row>
                            <v-col cols="12">
                                <v-data-table-server
                                    fixed-header
                                    hover
                                    height="500px"
                                    :headers="headers"
                                    :items="items"
                                    :items-length="total"
                                    :loading="loading"
                                    v-model:options="options"
                                    class="elevation-1"
                                    :items-per-page-options="[10, 25, 50, 100]"
                                    items-per-page-text=" Mostrar"
                                    no-data-text="No hay registros para mostrar"
                                >
                                    <template #top>
                                        <v-text-field
                                            v-model="search"
                                            label="Buscar reservación"
                                            class="mx-4 mt-2"
                                            clearable
                                        />
                                    </template>

                                    <template #item.start_datetime="{ item }">
                                        {{ formatDateTimeNoTZ(item.start_datetime)}}
                                    </template>

                                    <template #item.end_datetime="{ item }">
                                        {{ formatDateTimeNoTZ(item.end_datetime)}}
                                    </template>

                                    <template #item.status.name="{ item }">
                                        <v-chip :color="item.status.color" dark>
                                            {{ item.status.name }}
                                        </v-chip>
                                    </template>

                                    <template #item.actions="{ item }">
                                        <BaseButton
                                            @click="cancel(item)"
                                            action="cancel"
                                            :disabled="item.reservation_status_id != activeStatus"
                                            v-if="can.includes('reservations.update')"
                                        />
                                    </template>

                                </v-data-table-server>
                            </v-col>
                        </v-row>
                    </div>
                </v-window-item>

                <!-- TAB CALENDARIO -->
                <v-window-item value="calendar">
                    <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg mt-4 pa-4">
                        <v-row>
                            <v-col cols="12" md="5" class="pb-0">
                                <v-select
                                    v-model="filterAmenity"
                                    :items="amenities"
                                    item-title="name"
                                    item-value="id"
                                    label="Amenidad"
                                    clearable
                                />
                            </v-col>
                            <v-col cols="12" md="5" class="pb-0">
                                  <v-select
                                        v-model="filterResource"
                                        :items="filteredCalendarResources"
                                        item-title="name"
                                        item-value="id"
                                        label="Recurso"
                                        :disabled="!filterAmenity"
                                        clearable
                                        no-data-text="No hay recursos"
                                    />
                            </v-col>
                            <v-col cols="12" md="2" class="d-flex justify-center align-start pb-0">
                                <BaseButton
                                    variant="elevated"
                                    @click="clearCalendarFilters"
                                    color="blue"
                                    text="Limpiar"
                                    icon="mdi-filter-off"
                                />
                            </v-col>
                        </v-row>
                    </div>
                    <v-card class="mt-4 calendar-container d-flex flex-column">
                        <v-card-title>
                            <v-icon>mdi-calendar</v-icon>
                            Todas las reservaciones
                        </v-card-title>

                        <v-card-text class="calendar-content">
                            <AmenityCalendar
                                v-if="tab === 'calendar'"
                                :key="calendarKey"
                                :events="filteredCalendarEvents"
                                @cancel-reservation="cancelFromCalendar"
                            />
                        </v-card-text>
                    </v-card>
                </v-window-item>
            </v-window>
        </div>
        <v-dialog v-model="showReservationModal" max-width="600">
            <v-form @submit.prevent="saveReservation">
                <v-card title="Nueva reservación">

                    <v-card-text>
                        <v-row>
                            <v-col cols="12">
                                <v-select
                                    v-model="reservationForm.member_id"
                                    :items="members"
                                    item-title="full_name"
                                    item-value="id"
                                    label="Miembro"
                                    prepend-inner-icon="mdi-account"
                                    clearable
                                    :return-object="false"
                                    no-data-text="Sin resultados"
                                />
                            </v-col>
                            <!-- AMENIDAD -->
                            <v-col cols="12">
                                <v-select
                                    v-model="reservationForm.amenity_id"
                                    :items="amenities"
                                    item-title="name"
                                    item-value="id"
                                    label="Amenidad"
                                />
                            </v-col>

                            <!-- RECURSO -->
                            <v-col cols="12">
                                <v-select
                                    v-model="reservationForm.amenity_resource_id"
                                    :items="filteredResources"
                                    item-title="name"
                                    item-value="id"
                                    label="Recurso"
                                    :disabled="!reservationForm.amenity_id"
                                />
                            </v-col>

                            <!-- FECHA -->
                            <v-col cols="12">
                                <DatePicker
                                    v-model="reservationForm.date"
                                    label="Fecha"
                                    :min="minDate"
                                    :max="maxDate"
                                />
                            </v-col>
                            <v-col cols="12">
                                <div class="text-subtitle-2 mb-2">
                                    Horarios disponibles
                                </div>

                                <div v-if="loadingSlots">
                                    Cargando horarios...
                                </div>

                                <div v-else class="d-flex flex-wrap ga-2">
                                    <!-- MENSAJE -->
                                    <div v-if="selectedSlot" class="text-caption text-grey mb-2 w-100">
                                        Para seleccionar otro horario, vuelve a dar clic en el horario seleccionado
                                    </div>
                                    <v-chip
                                        v-for="(slot, index) in (selectedSlot ? [selectedSlot] : slots)"
                                        :key="index"
                                        :color="getSlotColor(slot)"
                                        :variant="slot.status === 'available' ? 'elevated' : 'outlined'"
                                        :disabled="slot.status !== 'available'"
                                        @click="selectSlot(slot)"
                                    >
                                        {{ formatHour(slot.start) }} - {{ formatHour(slot.end) }}
                                    </v-chip>
                                    <div v-if="!slots.length">
                                        No hay horarios disponibles
                                    </div>

                                </div>
                            </v-col>
                        </v-row>
                    </v-card-text>

                    <v-card-actions>
                        <v-spacer />
                        
                        <BaseButton
                            :text="'Cancelar'"
                            :icon-only="false"
                            action="cancel"
                            variant="tonal"
                            @click="showReservationModal = false"
                        />

                        <BaseButton
                            :text="'Guardar'"
                            :icon-only="false"
                            action="save"
                            type="submit"
                        />
                    </v-card-actions>

                </v-card>
            </v-form>
        </v-dialog>
    </AppLayout>
</template>
<style scoped>
.calendar-container {
  height: 80vh;             
  display: flex;
  flex-direction: column;
}
.calendar-content {
  flex: 1;
  min-height: 0;             
  overflow-y: auto;         
}
.calendar-content :deep(.sx__calendar) {
  height: 100% !important;
  min-height: 600px;        
}
:deep(.sx__event) {
  cursor: pointer;
}
</style>