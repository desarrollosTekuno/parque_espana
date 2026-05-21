<script setup lang="ts">
import { ScheduleXCalendar } from '@schedule-x/vue'
import {
  createCalendar,
  createViewWeek,
  createViewMonthGrid,
  createViewDay
} from '@schedule-x/calendar'
import { translations } from '@schedule-x/translations'
import '@schedule-x/theme-default/dist/index.css'
import { watch, nextTick, computed } from 'vue'

const props = defineProps({
  events: { type: Array, default: () => [] }
})
const emit = defineEmits(['create-reservation', 'cancel-reservation'])
const calendarApp = createCalendar({
  defaultView: 'week',
  locale: 'es-ES',
  views: [
    createViewWeek(),
    createViewMonthGrid(),
    createViewDay(),
  ],
  translations: translations['es-ES'],
  dayBoundaries: {
    start: '07:00',
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
  'blocked': {
    colorName: 'grey',
    lightColors: {
      main: '#9e9e9e',
      container: '#eeeeee',
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
      <div class="overlay-events">
         eventos
      </div>
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
</style>