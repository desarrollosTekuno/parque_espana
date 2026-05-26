<script setup lang="ts">
import axios from "axios";
import '@/../css/amenities.css';
import BaseButton from "@/Components/BaseButton.vue";
import FormDescripcion from "@/Components/Form/FormDescripcion.vue";
import AmenityCalendar from "@/Components/Amenities/AmenityCalendar.vue";
import FormIcon from "@/Components/Form/FormIcon.vue";
import FormImage from "@/Components/Form/FormImage.vue";
import FormName from "@/Components/Form/FormName.vue";
import FormNumber from "@/Components/Form/FormNumber.vue";
import TimePicker from "@/Components/TimePicker.vue";
import { required, maxLength, fileMaxSizeRule, fileTypeRule } from "@/constants/validationRules";
import AppLayout from "@/Layouts/AppLayout.vue";
import { customConfirmSwal, customToastSwal } from "@/utils/swal";
import { Form, Head, router, useForm, usePage } from "@inertiajs/vue3";
import { debounce } from "lodash";
import Swal from "sweetalert2";
import { ref, watch, computed, reactive, onMounted  } from "vue";
import { Temporal } from '@js-temporal/polyfill';   

const page = usePage();
const can = usePage().props.auth.permissions;
const imageRef = ref<any>(null);
const iconRef = ref<any>(null);
const iconInputRef = ref<HTMLInputElement | null>(null);
const imageInputRef = ref<HTMLInputElement | null>(null);

const onIconFileChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.icon = file;
    form.remove_icon = !file;
};

const onImageFileChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.background_image = file;
};

const triggerIconInput = () => iconInputRef.value?.click();
const triggerImageInput = () => imageInputRef.value?.click();
const tab = ref('amenities')
const props = withDefaults(defineProps<Props>(), {
    amenities: null,
    events: Array
});

// Modal calendario
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
  }))
}

const calendarEvents = ref<any[]>([])

const openCalendar = async (resource: any) => {
    selectedAmenityCalendar.value = resource 
  const { data } = await axios.get(
    route('amenityResource.calendar', resource.id)
  )
  calendarEvents.value = formatEvents(data)
  showCalendarModal.value = true
}

const showCalendarModal = ref(false)
const selectedAmenityCalendar = ref<any>(null)

// Cancelar reservación desde el modal del calendario
const cancelReservation = (event: any) => {
  if (event.calendarId !== 'status-1') {
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
    router.post(route('reservations.cancel', event.id), {}, {
      preserveScroll: true,
      onSuccess: (page) => {
        const flash = page.props.flash || {}
        if (flash.success) {
          customToastSwal({
            title: flash.success,
            icon: 'success'
          })
        }
        openCalendar(selectedAmenityCalendar.value)
      },
      onError: (errors) => {
        customToastSwal({
          title: errors.messageError || 'Error al cancelar',
          icon: 'error'
        })
      }
    })
  })
}


//    Computeds
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_SIZE_BYTES = 2 * 1024 * 1024; // 2 MB

const iconError = computed(() => {
    if (!form.icon) return null;
    if (!ALLOWED_TYPES.includes(form.icon.type)) return 'Formato no permitido';
    if (form.icon.size > MAX_SIZE_BYTES) return 'El archivo supera 2 MB';
    return null;
});

const imageError = computed(() => {
    if (!form.background_image) return null;
    if (!ALLOWED_TYPES.includes(form.background_image.type)) return 'Formato no permitido';
    if (form.background_image.size > MAX_SIZE_BYTES) return 'El archivo supera 2 MB';
    return null;
});

const isSaveDisabled = computed(() => !!iconError.value || !!imageError.value);
/*const isAmenities = computed(() => tab.value === 'amenities')
const handleCreate = () => {
    if (isAmenities.value) {
        create()
    } else {
        createResource()
    }
}*/
const handleCreate = () => {
    tab.value === 'amenities'
        ? create()
        : createResource()
}
const activeDaysCount = computed(() => {
    return formSchedule.days.filter(day => day.active).length
})
const reservationType = computed(() => {
    return selectedAmenity.value?.reservation_type
})
const showSlotDuration = computed(() => {
    return reservationType.value === 'hourly' || reservationType.value === 'capacity'
})
const showCapacity = computed(() => {
    return reservationType.value === 'capacity'
})
const selectedAmenity = computed(() => {
    return items.value.find(a => a.id === resourceForm.amenity_id)
})
const bulkSchedule = reactive({
    open: null as string | null,
    close: null as string | null
})

interface Props {
    amenities?: any;
    members?: any[];
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
}

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
    is_active: true,
});
const formSchedule = useForm({
    id: null,
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

const amenityName = ref('')
const dayMap = {
        monday: 1,
        tuesday: 2,
        wednesday: 3,
        thursday: 4,
        friday: 5,
        saturday: 6,
        sunday: 0
    };
const create = () => {
    form.reset();
    imagePreview.value = null;
    iconPreview.value = null;
    showModal.value = true;
};
const savingAmenity = ref(false)

const save = async () => {

    const { valid } = await formSendRef.value?.validate()

    if (!valid) return

    const result = await customConfirmSwal({
        title: form.id 
            ? "¿Actualizar amenidad?" 
            : "¿Guardar amenidad?",
        text: form.id
            ? "Se actualizarán los datos de la amenidad"
            : "Se creará una nueva amenidad"
    })

    if (!result.isConfirmed) return

    if (savingAmenity.value) return
    savingAmenity.value = true

    form
        .transform((data) => {

            const payload: any = { ...data }

            if (form.id) {
                payload._method = "PUT"
            }

            if (!data.icon && !data.remove_icon) {
                delete payload.icon
            }

            if (!data.background_image && !data.remove_background_image) {
                delete payload.background_image
            }

            return payload
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
                    })

                    showModal.value = false
                    form.reset()
                    form.transform(data => data)

                    imagePreview.value = null
                    iconPreview.value = null

                    fetchItems()

                    savingAmenity.value = false
                },

                onError: () => {

                    customToastSwal({
                        title: `Error: ${form.errors.messageError}`,
                        text: `${form.errors.exception}`,
                        icon: "error",
                    })

                    savingAmenity.value = false
                },
            }
        )
}

const edit = (data: any) => {
    form.id = data.id;
    form.name = data.name;
    form.description = data.description;
    form.reservation_type = data.reservation_type;
    form.is_active = data.is_active;
    form.icon = null;
    form.icon_path = data.icon_url || null;
    form.remove_icon = false;
    form.background_image = null;
    form.remove_background_image = false;
    form.background_image_path = data.background_image_url || null;
    iconPreview.value = data.icon_url ?? null;
    imagePreview.value = data.background_image_url ?? null;
    showModal.value = true;
};
const schedule = async () => {
    const current = JSON.stringify(
        normalizeSchedule(formSchedule.days)
    )

    if(current === originalSchedule.value){
        customToastSwal({
            title: "No hubo cambios",
            icon: "info"
        })
        closeScheduleModal()
        return
    }
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
    
    const result = await Swal.fire({
        title: '¿Desea guardar los cambios?',
        text: 'Se actualizará el horario de la amenidad seleccionada',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, cambiar',
        cancelButtonText: 'Cancelar',
        reverseButtons: true,
        allowOutsideClick: false,
        target: document.body
    });

    if (!result.isConfirmed) {
        return;
    }

    const schedules = formSchedule.days
        .filter(day => day.active)
        .map(day => ({
            day_of_week: dayMap[day.day],
            open_time: day.open,
            close_time: day.close,
            amenity_id: formSchedule.amenity_id
        }));
        router.post(route('amenitySchedule.store'), { schedules }, {
        preserveState: true,
        preserveScroll: true,

        onSuccess: (page) => {
            const flash = page.props.flash || {}
            if (flash.messageError) {
                customToastSwal({
                    title: flash.messageError,
                    icon: "error"
                })
                return
            }
            if (flash.success) {
                customToastSwal({
                    title: flash.success,
                    icon: "success"
                })
                originalSchedule.value = JSON.stringify(formSchedule.days)
                closeScheduleModal()
                fetchItems()
            }
        },

        onError: (errors) => {
            customToastSwal({
                title: errors.messageError || "Error al guardar horario",
                icon: "error"
            })
        }
    })
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

// Cierre de modales 
const close = () => {
    form.reset();
    iconPreview.value = null;
    imagePreview.value = null;
    showModal.value = false;
};
const closeScheduleModal = () => {
    showScheduleModal.value = false
    formSchedule.id = null
    Object.keys(scheduleErrors).forEach(key => delete scheduleErrors[key])
};
const closeResourcesModal = () => {
    resourceForm.reset()
    resourceForm.id = null
    showResourceModal.value = false
};
watch(() => form.icon, (file) => {
    if (file instanceof File) {
        iconPreview.value = URL.createObjectURL(file);
    } else if (file && form.icon_path) {
        iconPreview.value = form.icon_path;
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
    { title: "Nombre", key: "name" },
    { title: "Imagen", key: "background_image", sortable: false },
    { title: "Tipo de reserva", key: "reservation_type", sortable: false},
    { title: "Horario", key: "schedule_text", sortable: false },
    { title: "Activo", key: "is_active" },
    { title: "Acciones", key: "actions", sortable: false },
];

const items = ref([]);
const total = ref(0);
const loading = ref(false);
/*const search = ref("");
const options = ref({ page: 1, itemsPerPage: 10, sortBy: [{ key: "id", order: "desc" }] });*/
const search = ref(page.props.ziggy?.query?.amenities_search ?? "")
const options = ref({
    page: Number(page.props.ziggy?.query?.amenities_page ?? 1),
    itemsPerPage: Number(page.props.ziggy?.query?.amenities_per_page ?? 10),
    sortBy: [{
        key: page.props.ziggy?.query?.amenities_sort ?? "id",
        order: page.props.ziggy?.query?.amenities_order ?? "desc"
    }]
});

const prefix = "amenities";

const fetchItems = () => {
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
            const amenities = page.props.amenities;

            items.value = amenities?.data ?? [];
            total.value = amenities?.total ?? 0;

            loading.value = false;
        },
    });
};
const reservationTypeLabel: Record<string,string> = {
    daily: 'Reserva por día',
    hourly: 'Reserva por tiempo',
    capacity: 'Reserva por capacidad'
}

//   Tabla, items y fetch de recursos    
const resourceHeaders = [
    { title: "ID", key: "id" },
    { title: "Amenidad", key: "amenity_name" },
    { title: "Nombre", key: "name" },
    { title: "Capacidad", key: "capacity" },
    { title: "Reserva en minutos", key: "slot_duration_minutes" },
    { title: "Activo", key: "is_active" },
    { title: "Acciones", key: "actions", sortable: false },
];
const resourceItems = ref([]);
const resourceTotal = ref(0);
const resourceLoading = ref(false);
const resourceOptions = ref({
    page: 1,
    itemsPerPage: 10,
    sortBy: [{ key: "id", order: "desc" }]
});
const resourceSearch = ref("");
const fetchResources = async () => {
    resourceLoading.value = true;

    try {
        const response = await axios.get(route('amenityResource.index'), {
            params: {
                club_id: page.props.auth.currentClub,
                page: resourceOptions.value.page,
                per_page: resourceOptions.value.itemsPerPage,
                search: resourceSearch.value,
                sort: resourceOptions.value.sortBy?.[0]?.key ?? "id",
                order: resourceOptions.value.sortBy?.[0]?.order ?? "desc",
            }
        });
        resourceItems.value = response.data.data;
        resourceTotal.value = response.data.total;

    } catch (error) {
        console.error(error);
    } finally {
        resourceLoading.value = false;
    }
};

// Función para agrupar y mostrar los horarios
const formatScheduleSmart = (schedules: any[]) => {
    if (!schedules || !schedules.length) return [];

    const dayNames: any = {
        0: "Dom",
        1: "Lun",
        2: "Mar",
        3: "Mié",
        4: "Jue",
        5: "Vie",
        6: "Sáb",
    };

    const WEEKDAYS = [1,2,3,4,5];
    const WEEKEND = [6,0];
    const groupedByTime: Record<string, number[]> = {};

    schedules.forEach(s => {
        const key = `${s.open_time}-${s.close_time}`;
        if (!groupedByTime[key]) groupedByTime[key] = [];
        groupedByTime[key].push(s.day_of_week);
    });

    const result: string[] = [];

    Object.entries(groupedByTime).forEach(([time, days]) => {
        const sortedDays = [...new Set(days)].sort((a, b) => a - b);

        let label = "";
        const ALL_WEEK = [0,1,2,3,4,5,6];
        const isAllWeek = ALL_WEEK.every(d => sortedDays.includes(d)) && sortedDays.length === 7;
        const isWeekdays = WEEKDAYS.every(d => sortedDays.includes(d)) && sortedDays.length === 5;
        const isWeekend = WEEKEND.every(d => sortedDays.includes(d)) && sortedDays.length === 2;
        if (isAllWeek) {
            label = "Toda la semana";
        }else if (isWeekdays) {
            label = "Lun-Vie";
        } else if (isWeekend) {
            label = "Fin de semana";
        } else {
            const parts: string[] = [];
            let start = sortedDays[0];
            let prev = sortedDays[0];

            for (let i = 1; i <= sortedDays.length; i++) {
                const current = sortedDays[i];

                if (current !== prev + 1) {
                    if (start === prev) {
                        parts.push(dayNames[start]);
                    } else {
                        parts.push(`${dayNames[start]}-${dayNames[prev]}`);
                    }
                    start = current;
                }

                prev = current;
            }

            label = parts.join(", ");
        }

        const [open, close] = time.split("-");
        const formattedTime = `${open.slice(0,5)}–${close.slice(0,5)}`;

        result.push(`${label} ${formattedTime}`);
    });

    return result;
};

// Función para aplicar un horario a varios días
const applyToAllDays = () => {
    if (!bulkSchedule.open || !bulkSchedule.close) {
        customToastSwal({
            title: "Define apertura y cierre primero",
            icon: "warning"
        })
        return
    }

    formSchedule.days.forEach(day => {
        if (day.active) {
            day.open = bulkSchedule.open
            day.close = bulkSchedule.close
        }
    })

    customToastSwal({
        title: "Horario aplicado",
        icon: "success"
    })
    bulkSchedule.open = null
    bulkSchedule.close = null
}
const showScheduleModal = ref(false);
const originalSchedule = ref<string>("")
const normalizeSchedule = (days:any[]) => {
    return days.map(d => ({
        day: d.day,
        open: d.open,
        close: d.close,
        active: d.active
    }))
}
const openScheduleModal = (amenity: any) => {

    amenityName.value = amenity.name;
    formSchedule.amenity_id = amenity.id;
    formSchedule.id = amenity.schedules?.length ? amenity.id : null;
    formSchedule.days.forEach(day => {
        day.active = false
        day.open = null
        day.close = null
    })

  const schedules = amenity.schedules || amenity.amenityResource?.schedules || [];
    schedules.forEach((schedule) => {

            const map = {
                1: 'monday',
                2: 'tuesday',
                3: 'wednesday',
                4: 'thursday',
                5: 'friday',
                6: 'saturday',
                0: 'sunday'
            }

            const dayName = map[schedule.day_of_week]

            const day = formSchedule.days.find(d => d.day === dayName)

            if (day) {
                day.active = true
                day.open = schedule.open_time
                day.close = schedule.close_time
            }

        })

    showScheduleModal.value = true
    originalSchedule.value = JSON.stringify(
        normalizeSchedule(formSchedule.days)
    )
}
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
    slot_duration_minutes: null,
    is_active: true
})
const createResource = () => {
    resourceForm.reset()
    resourceForm.capacity = null
    resourceForm.slot_duration_minutes = null
    showResourceModal.value = true

}
const editResource = (resource: any) => {
    resourceForm.id = resource.id
    resourceForm.amenity_id = resource.amenity_id
    resourceForm.name = resource.name
    resourceForm.capacity = resource.capacity
    resourceForm.slot_duration_minutes = resource.slot_duration_minutes
    resourceForm.is_active = resource.is_active
    showResourceModal.value = true

}
const saveResource = () => {

    customConfirmSwal({
        title: resourceForm.id 
            ? "¿Actualizar recurso?" 
            : "¿Guardar recurso?",
        text: "Confirma para continuar"
    }).then((result) => {

        if (!result.isConfirmed) return

        resourceForm
            .transform((data) => {
                const payload: any = { ...data }

                if (!showSlotDuration.value) {
                    payload.slot_duration_minutes = null
                }

                if (!showCapacity.value) {
                    payload.capacity = 1
                }

                if (resourceForm.id) {
                    payload._method = "PUT"
                }

                return payload
            })
            .post(
                resourceForm.id
                    ? route('amenityResource.update', resourceForm.id)
                    : route('amenityResource.store'),
                {
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
                            title: flash.success || "Recurso guardado",
                            icon: "success"
                        })

                        resourceForm.reset()
                        showResourceModal.value = false
                        fetchResources()
                    },

                    onError: (errors) => {
                        customToastSwal({
                            title: errors.messageError || "Error al guardar recurso",
                            icon: "error"
                        })
                    }
                })
    })
}
const deleteResource = (item: any) => {
    customConfirmSwal({
        title: "¿Eliminar recurso?",
        text: "Esta acción no se puede deshacer",
    }).then(result => {
        if (result.isConfirmed) {
            router.delete(route('amenityResource.destroy', item.id), {
                onSuccess: (page) => {
                    const flash = page.props.flash || {}
                        if(flash.messageError){
                            customToastSwal({
                                title: flash.messageError,
                                icon: "error"
                            })
                            return
                        }
                        customToastSwal({
                            title: flash.success || "Recurso eliminado",
                            icon: "success"
                        })
                        fetchResources()
                    },
                    onError: (errors) => {
                        customToastSwal({
                            title: errors.messageError || "Error al eliminar",
                            icon: "error"
                        })
                    }
            })
        }
    })
}

watch(
    () => props.amenities,
    (val) => {
        items.value = val?.data ?? [];
        total.value = val?.total ?? 0;
    },
    { immediate: true }
);
const isFirstLoad = ref(true);
watch([options, search], debounce(() => {
    if (isFirstLoad.value) {
        isFirstLoad.value = false;
        return;
    }
    fetchItems();
}, 400), { deep: true });
//const resourcesLoaded = ref(false);
watch(() => page.props.auth.currentClub, () => {
    fetchItems();
});
watch(
    () => formSchedule.days.map(d => [d.open,d.close]),
    (days)=>{
        days.forEach((_,index)=>{
            const day = formSchedule.days[index]

            if(day.open)
                delete scheduleErrors[`${day.day}_open`]

            if(day.close)
                delete scheduleErrors[`${day.day}_close`]
        })
    }
)
watch([resourceOptions, resourceSearch], debounce(fetchResources, 400), { deep: true });

watch(tab, (value) => {
    if(value === 'resources'){
        fetchResources()
    }
});
watch(
    () => reservationType.value,
    (type) => {
        if (type === 'daily') {
            resourceForm.capacity = null
            resourceForm.slot_duration_minutes = null
        }
        if (type === 'hourly') {
            resourceForm.capacity = null
            if (!resourceForm.slot_duration_minutes) {
                resourceForm.slot_duration_minutes = null
            }
        }
        if (type === 'capacity') {
            if (!resourceForm.capacity) {
                resourceForm.capacity = 1
            }
            if (!resourceForm.slot_duration_minutes) {
                resourceForm.slot_duration_minutes = null
            }
        }
    },
    { immediate: true }
)
/*watch(() => form.reservation_type, () => {
    if (tab.value === 'resources') {
        fetchResources()
    }
})*/
</script>

<template>

    <Head title="Amenidades" />
    <AppLayout>
        <template #options>
            <BaseButton 
               v-if="can.includes(tab === 'amenities' ? 'amenities.store' : 'amenityResource.store')"
                variant="elevated"
                :icon-only="false" 
                @click="handleCreate" 
                action="add" 
                :text="tab === 'amenities' ? 'Agregar Amenidad' : 'Agregar Recurso'" 
            />
        </template>
        <template #header>Amenidades</template>
        <div class="pa-4 bg-grey-lighten-4 rounded-xl mt-5">
            <v-tabs v-model="tab" class="custom-tabs" grow>
                <v-tab value="amenities">
                    <v-icon start>mdi-beach</v-icon>
                    Amenidades
                </v-tab>

                <v-tab value="resources">
                    <v-icon start>mdi-cube-outline</v-icon>
                    Recursos
                </v-tab>
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
                                        <v-text-field
                                            v-model="search" 
                                            label="Buscar amenidad..."
                                            prepend-inner-icon="mdi-magnify"
                                            variant="outlined"
                                            density="comfortable"
                                            class="mx-4 mt-4"
                                        />
                                </template>
                                <!--<template #item.icon="{ item }">
                                    <v-img v-if="item.icon" :src="`/storage/${item.icon}`" max-height="30"
                                        max-width="30" class="rounded-lg" />
                                </template>-->
                                <template #item.background_image="{ item }">
                                    <v-img v-if="item.background_image_url" :src="item.background_image_url"
                                        max-height="80" max-width="80" class="rounded-lg" />
                                </template>
                                <template #item.reservation_type="{ item }">
                                    {{ reservationTypeLabel[item.reservation_type] }}
                                </template>
                                <template #item.is_active="{ item }">
                                    <v-chip :color="item.is_active ? 'green' : 'red'" dark>
                                        {{ item.is_active ? 'Activo' : 'Inactivo' }}
                                    </v-chip>
                                </template>
                                <template #item.schedule_text="{ item }">
                                    <div class="d-flex flex-wrap ga-1">
                                        <v-chip
                                            v-for="(block, index) in formatScheduleSmart(item.schedules)"
                                            :key="index"
                                            size="x-small"
                                            variant="tonal"
                                            :color="index % 2 === 0 ? 'primary' : 'secondary'"
                                        >
                                            <v-icon start size="14">mdi-clock-outline</v-icon>
                                            {{ block }}
                                        </v-chip>

                                        <v-chip
                                            v-if="!formatScheduleSmart(item.schedules).length"
                                            size="x-small"
                                            color="grey"
                                            variant="outlined"
                                        >
                                            Sin horario
                                        </v-chip>
                                    </div>
                                </template>
                                <template #item.actions="{ item }">
                                    <span class="action-slot">
                                        <BaseButton text="Agregar horario" action="add" icon="mdi-calendar-month"
                                            @click="openScheduleModal(item)" v-if="can.includes('amenitySchedule.store')" />
                                    </span>
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
                            <v-data-table-server :headers="resourceHeaders" :items="resourceItems"
                                :items-length="resourceTotal" :loading="resourceLoading" loading-text="Cargando recursos..." class="elevation-1"
                                no-data-text="No hay recursos registrados" v-model:options="resourceOptions">
                                <template #item.capacity="{ item }">
                                    <span v-if="item.amenity?.reservation_type === 'capacity'">
                                        {{ item.capacity }}
                                    </span>
                                    <span v-else class="text-grey">
                                        No aplica
                                    </span>
                                </template>
                                <template #item.slot_duration_minutes="{ item }">
                                    <span v-if="item.amenity?.reservation_type === 'hourly' || item.amenity?.reservation_type === 'capacity'">
                                        {{ item.slot_duration_minutes ? `${item.slot_duration_minutes} min` : 'No definido' }}
                                    </span>
                                    <span v-else class="text-grey">
                                        No aplica
                                    </span> 
                                </template>
                                <template #item.is_active="{ item }">
                                    <v-chip :color="item.is_active ? 'green' : 'red'">
                                        {{ item.is_active ? 'Activo' : 'Inactivo' }}
                                    </v-chip>
                                </template>
                                <template #top>
                                    <v-text-field
                                        v-model="resourceSearch"
                                        label="Buscar recurso"
                                        class="mx-4 mt-2"
                                        clearable
                                    />
                                </template>
                                <template #item.actions="{ item }">
                                    <BaseButton v-if="can.includes('amenityResource.calendar')" text="Calendario" icon="mdi-calendar-month" action="view" @click="openCalendar(item)" />
                                    <BaseButton v-if="can.includes('amenityResource.update')" action="edit" @click="editResource(item)" />
                                    <BaseButton v-if="can.includes('amenityResource.destroy')" action="delete" @click="deleteResource(item)" />
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
                    <v-card-text class="overflow-y-auto h-full" style="max-height:70vh; overflow-y:auto;">
                        <v-row>
                            <v-col cols="12">
                                <FormName v-model="form.name" 
                                    label="Nombre" 
                                    :rules="[required, maxLength(50)]" />
                            </v-col>

                            <!-- ── Icono ── -->
                            <v-col cols="12" sm="5">
                                <div class="text-body-2 font-weight-medium mb-2">Icono</div>

                                <input
                                    ref="iconInputRef"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="d-none"
                                    @change="onIconFileChange"
                                />

                                <div
                                    class="upload-zone upload-zone--square"
                                    :class="{ 'upload-zone--filled': iconPreview }"
                                    @click="triggerIconInput"
                                >
                                    <v-img
                                        v-if="iconPreview"
                                        :src="iconPreview"
                                        cover
                                        class="upload-zone__img"
                                    />
                                    <div v-else class="upload-zone__placeholder">
                                        <v-icon size="38" color="grey-lighten-1">mdi-image-plus</v-icon>
                                        <span class="text-caption text-medium-emphasis mt-1">Subir icono</span>
                                    </div>
                                    <div v-if="iconPreview" class="upload-zone__overlay">
                                        <v-icon color="white" size="28">mdi-pencil</v-icon>
                                    </div>
                                </div>

                                <div class="d-flex align-center justify-space-between mt-1">
                                    <span
                                        class="text-caption"
                                        :class="iconError ? 'text-error' : 'text-medium-emphasis'"
                                    >
                                        {{ iconError ?? 'JPG, PNG, WEBP · máx. 2 MB' }}
                                    </span>
                                    <v-btn
                                        v-if="iconPreview"
                                        size="x-small"
                                        color="error"
                                        variant="text"
                                        @click.stop="removeIcon"
                                    >
                                        <v-icon size="14" start>mdi-close</v-icon>Quitar
                                    </v-btn>
                                </div>
                            </v-col>

                            <!-- ── Imagen de fondo ── -->
                            <v-col cols="12" sm="7">
                                <div class="text-body-2 font-weight-medium mb-2">Imagen de fondo</div>

                                <input
                                    ref="imageInputRef"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    class="d-none"
                                    @change="onImageFileChange"
                                />

                                <div
                                    class="upload-zone upload-zone--wide"
                                    :class="{ 'upload-zone--filled': imagePreview }"
                                    @click="triggerImageInput"
                                >
                                    <v-img
                                        v-if="imagePreview"
                                        :src="imagePreview"
                                        cover
                                        class="upload-zone__img"
                                    />
                                    <div v-else class="upload-zone__placeholder">
                                        <v-icon size="38" color="grey-lighten-1">mdi-image-plus</v-icon>
                                        <span class="text-caption text-medium-emphasis mt-1">Subir imagen de fondo</span>
                                    </div>
                                    <div v-if="imagePreview" class="upload-zone__overlay">
                                        <v-icon color="white" size="28">mdi-pencil</v-icon>
                                    </div>
                                </div>

                                <div class="d-flex align-center justify-space-between mt-1">
                                    <span
                                        class="text-caption"
                                        :class="imageError ? 'text-error' : 'text-medium-emphasis'"
                                    >
                                        {{ imageError ?? 'JPG, PNG, WEBP · máx. 2 MB' }}
                                    </span>
                                    <v-btn
                                        v-if="imagePreview"
                                        size="x-small"
                                        color="error"
                                        variant="text"
                                        @click.stop="removeBackgroundImage"
                                    >
                                        <v-icon size="14" start>mdi-close</v-icon>Quitar
                                    </v-btn>
                                </div>
                            </v-col>

                            <v-col cols="12">
                                <FormDescripcion v-model="form.description" label="Descripción" rows="3"
                                    :required="false" :min-length="0" auto-grow />
                            </v-col>
                            <v-col cols="12">
                                <v-select v-model="form.reservation_type" prepend-inner-icon="mdi-calendar-check"
                                    label="Tipo de reserva" placeholder=" " :items="[
                                        { title: 'Reserva por día', value: 'daily' },
                                        { title: 'Reserva por tiempo', value: 'hourly' },
                                        { title: 'Reserva por capacidad', value: 'capacity' }
                                    ]" item-title="title" item-value="value" :rules="[required]" 
                                    />
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
                    <v-card-title>
                        <div class="title-container">
                            <span class="title"><v-icon size="25">mdi-calendar-clock</v-icon> Horarios de la
                                amenidad</span>
                            <span class="subtitle">
                                {{ amenityName }} · {{ activeDaysCount }} días activos
                            </span>
                        </div>
                    </v-card-title>
                    <v-card-text class="schedule-container overflow-y-auto">
                        <v-card class="mb-4 pa-3 bg-whitesmoke" variant="flat">
                            <div class="d-flex align-center ga-2 flex-nowrap">

                                <v-icon size="18">mdi-lightning-bolt</v-icon>

                                <span class="text-body-2 font-weight-medium">
                                    Aplicar horario rápido
                                </span>

                                <TimePicker
                                    v-model="bulkSchedule.open"
                                    density="compact"
                                    class="time-input custom-time"
                                    label-menu="Apertura"
                                />
                                <span>-</span>

                                <TimePicker
                                    v-model="bulkSchedule.close"
                                    density="compact"
                                    class="time-input custom-time"
                                     label-menu="Cierre"
                                />

                                <BaseButton
                                    :text="'Aplicar'"
                                    variant="flat"
                                    size="small"
                                    :icon-only="false"
                                    icon="mdi-clock"
                                    action="edit"
                                    @click="applyToAllDays"
                                />

                            </div>
                        </v-card>   
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
                        <BaseButton :text="formSchedule.id ? 'Actualizar horario' : 'Guardar horario'" variant="flat" :icon-only="false" type="submit"
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
                                <v-text-field v-model="resourceForm.name" label="Nombre" :required="true" :min-length="2" />
                            </v-col>
                            <v-col cols="12" v-if="showSlotDuration">
                                <FormNumber v-model="resourceForm.slot_duration_minutes" label="Espacio de reserva en minutos"
                                    :min="0" :rules="[required]" />
                            </v-col>
                            <v-col cols="12" v-if="showCapacity">
                                <FormNumber v-model="resourceForm.capacity" label="Capacidad" :required="true"/>
                            </v-col>
                        </v-row>
                    </v-card-text>
                    <v-card-actions>
                        <v-spacer />
                        <BaseButton :text="'Cancelar'" variant="tonal" action="cancel" :icon-only="false" @click="closeResourcesModal" />
                        <BaseButton :text="resourceForm.id ? 'Actualizar' : 'Guardar'" variant="flat" :icon-only="false"
                            type="submit" action="save" />
                    </v-card-actions>
                </v-card>
            </v-form>
        </v-dialog>
        <v-dialog v-model="showCalendarModal" max-width="1200">
            <v-card class="calendar-modal">

                <v-card-title class="d-flex align-center ga-2">
                <v-icon>mdi-calendar</v-icon>
                Reservaciones · {{ selectedAmenityCalendar?.name }}
                </v-card-title>
                <v-card-text class="calendar-content">
                 <AmenityCalendar
                    v-if="showCalendarModal"
                    :events="calendarEvents"
                    @cancel-reservation="cancelReservation"
                    />
                </v-card-text>
                <v-card-actions class="calendar-footer">
                <v-spacer />
                <BaseButton
                    text="Cerrar"
                    action="cancel"
                    variant="tonal"
                    :icon-only="false"
                    @click="showCalendarModal = false"
                />
                </v-card-actions>

            </v-card>
        </v-dialog>
    </AppLayout>
</template>
<style>
.swal2-container {
    z-index: 9999 !important;
}
.calendar-content {
  flex: 1;
  min-height: 0;
  overflow-y: auto; 
}
.calendar-wrapper :deep(.sx__calendar) {
  height: 100% !important;
}
.calendar-modal {
  display: flex;
  flex-direction: column;
  height: 90vh;
}
.calendar-footer {
  border-top: 1px solid #eee;
  padding: 10px;
}
</style>