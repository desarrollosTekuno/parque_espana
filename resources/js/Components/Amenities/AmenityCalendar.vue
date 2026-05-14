<script setup lang="ts">
import FullCalendar from '@fullcalendar/vue3'

import dayGridPlugin from '@fullcalendar/daygrid'
import timeGridPlugin from '@fullcalendar/timegrid'
import esLocale from '@fullcalendar/core/locales/es'
import interactionPlugin from '@fullcalendar/interaction'

const props = defineProps({
    events: {
        type: Array,
        default: () => []
    }
})

const calendarOptions = {
    plugins: [
        dayGridPlugin,
        timeGridPlugin,
        interactionPlugin
    ],

    initialView: 'timeGridWeek',

    locale: esLocale,
    timeZone: 'local',

    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
    },

    eventTimeFormat: {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    },
    eventContent: function(arg) {

    const props = arg.event.extendedProps
    const viewType = arg.view.type
    const bgColor = arg.event.backgroundColor

    switch (viewType) {
    case 'dayGridMonth':
        return {
                html: `
                    <div style="background:${bgColor}; padding:2px; border-radius:4px; color:white;">
                        ${props.start_time} - ${props.end_time}
                        <small>${props.status}</small>
                    </div>
                `
            }
    case 'timeGridWeek':
         return {
                html: `
                    <div style="background:${bgColor}; padding:2px; border-radius:4px; color:white;">
                        ${arg.event.title}
                    </div>
                `
            }
    case 'timeGridDay':
        return {
                html: `
                    <div style="background:${bgColor}; padding:2px; border-radius:4px; color:white;">
                        <strong>${arg.event.title}</strong>
                        <small>${props.start_time} - ${props.end_time}</small>    
                        <small>${props.status}</small>
                    </div>
                `
            }
        }
    },
    slotMinTime: '06:00:00',
    slotMaxTime: '23:00:00',

    allDaySlot: false,

    events: props.events,

    height: 650
}
</script>

<template>
    <FullCalendar :options="calendarOptions" />
</template>