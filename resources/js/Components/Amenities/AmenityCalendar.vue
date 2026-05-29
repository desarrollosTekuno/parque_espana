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
import { watch, nextTick, onMounted, onBeforeUnmount  } from 'vue'

const emit = defineEmits(['create-reservation', 'cancel-reservation'])
interface CalendarEvent {
  id: string
  title: string
  start: any
  end: any
  status?: string
  amenity_id?: number
  resource_id?: number
  amenity_name?: string
  resource_name?: string
  _tooltip?: string
}
/*const props = defineProps({
  events: { type: Array, default: () => [] }
})*/
const props = defineProps<{
  events: CalendarEvent[]
}>()
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
const applyTooltips = async () => {
  await nextTick()
  const elements = document.querySelectorAll(
    '.sx__event, .sx__month-grid-event'
  )
  elements.forEach((el: any) => {
    const eventId = el.getAttribute('data-event-id')
    const event = props.events.find(
      e => String(e.id) === String(eventId)
    )
    if (event?._tooltip) {
      el.setAttribute('title', event._tooltip)
    }
  })
}
let observer: MutationObserver | null = null
/*watch(
  () => props.events,
  async (events) => {
    if (!events?.length) return
    calendarApp.events.set(events)
    setTimeout(() => {
      const elements = document.querySelectorAll('.sx__event')

      elements.forEach((el: any) => {
        const eventId = el.getAttribute('data-event-id')

        const event = events.find(e => String(e.id) === String(eventId))

        if (event?._tooltip) {
          el.setAttribute('title', event._tooltip)
        }
      })
    }, 50)
  },
  { immediate: true, deep: true }
)*/

watch(
  () => props.events,
  async (events) => {
    if (!events?.length) return

    calendarApp.events.set(events)

    setTimeout(async () => {
      await applyTooltips()
    }, 50)
  },
  { immediate: true, deep: true }
)
onMounted(async () => {
  await applyTooltips()
  const calendarEl = document.querySelector('.sx__calendar')
  if (!calendarEl) return
  observer = new MutationObserver(async () => {
    await applyTooltips()
  })
  observer.observe(calendarEl, {
    childList: true,
    subtree: true
  })
})
onBeforeUnmount(() => {
  observer?.disconnect()
})
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
:deep(.sx__event) {
  margin-bottom: 4px;
  width: 100% !important;
}
:deep(.sx__time-grid-event) {
  left: 0 !important;
  width: 100% !important;
}
</style>