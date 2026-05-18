<script setup lang="ts">
import { ScheduleXCalendar } from '@schedule-x/vue'
import {
  createCalendar,
  createViewWeek,
  createViewMonthGrid,
  createViewDay
} from '@schedule-x/calendar'
import { translations, mergeLocales } from '@schedule-x/translations'
import '@schedule-x/theme-default/dist/index.css'
import { watch, nextTick } from 'vue'

const props = defineProps({
  events: { type: Array, default: () => [] }
})
const emit = defineEmits(['create-reservation', 'cancel-reservation'])
const calendarApp = createCalendar({
  defaultView: 'week',
  locale: 'es-MX',
  views: [
    createViewWeek(),
    createViewMonthGrid(),
    createViewDay(),
  ],

  dayBoundaries: {
    start: '08:00',
    end: '22:00',
  },

  calendars: {
  'status-1': {
    colorName: 'blue',
    lightColors: {
      main: '#42a5f5',
      container: '#e3f2fd',
      onContainer: '#000'
    }
  },
  'status-2': {
    colorName: 'red',
    lightColors: {
      main: '#ef5350',
      container: '#fdecea',
      onContainer: '#000'
    }
  },
  'status-3': {
    colorName: 'green',
    lightColors: {
      main: '#66bb6a',
      container: '#e8f5e9',
      onContainer: '#000'
    }
  },
  'status-4': {
    colorName: 'orange',
    lightColors: {
      main: '#ffa726',
      container: '#fff3e0',
      onContainer: '#000'
    }
  },
},

  events: [],

  callbacks: {
    onEventClick(event) {
      handleEventClick(event)
    }
  },
})
const handleEventClick = (event: any) => {
  emit('cancel-reservation', event)
}
watch(
  () => props.events,
  async (events) => {
    if (!events?.length) return
    await nextTick()
    calendarApp.events.set(events)
  },
  { immediate: true, deep: true }
)
</script>

<template>
  <div class="calendar-wrapper">
    <ScheduleXCalendar :calendar-app="calendarApp" />
  </div>
</template>
<style scoped>
.calendar-wrapper {
  flex: 1;
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.calendar-wrapper :deep(.sx__calendar) {
  flex: 1;
  min-height: 0;
}

/* =========================
   TRADUCCIONES TOOLBAR
========================= */

/* VIEW */
:deep(.sx__view-selection-label) {
  font-size: 0;
}
:deep(.sx__view-selection-label::after) {
  content: "Vista";
  font-size: 14px;
}

/* DATE */
:deep(.sx__toolbar-date-label span) {
  display: none;
}
:deep(.sx__toolbar-date-label::after) {
  content: "Fecha";
}

/* TODAY */
:deep(.sx__today-button) {
  font-size: 0;
}
:deep(.sx__today-button::after) {
  content: "Hoy";
  font-size: 14px;
}
:deep(.sx__date-input-label) {
  font-size: 0;
}
:deep(.sx__date-input-label::after) {
  content: "Hoy";
  font-size: 14px;
}
/* =========================
   SELECT DE VISTAS
========================= */
/* ocultar texto original */
:deep(.sx__view-selection-item) {
  font-size: 0!important;
}

/* WEEK */
:deep(.sx__view-selection-item[aria-label*="Week"]::after) {
  content: "Semana";
  font-size: 14px;
}

/* MONTH */
:deep(.sx__view-selection-item[aria-label*="Month"]::after) {
  content: "Mes";
  font-size: 14px;
}

/* DAY */
:deep(.sx__view-selection-item[aria-label*="Day"]::after) {
  content: "Día";
  font-size: 14px;
}
</style>