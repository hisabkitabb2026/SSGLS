<template>
  <div class="w-full">
    <Popover v-slot="{ isOpen }">
      <PopoverButton
        v-if="userStore.hasAbilities(['view-note'])"
        :class="isOpen ? '' : ''"
        class="
          flex
          items-center
          z-10
          font-medium
          text-primary-400
          focus:outline-hidden focus:border-none
        "
        @click="fetchInitialData"
      >
        <BaseIcon
          name="PlusIcon"
          class="w-4 h-4 font-medium text-primary-400"
        />
        {{ $t('general.insert_note') }}
      </PopoverButton>

      <!-- Note Select Popup -->
      <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-1 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-1 opacity-0"
      >
        <PopoverPanel
          v-slot="{ close }"
          class="
            absolute
            z-20
            px-4
            mt-3
            sm:px-0
            w-screen
            max-w-full
            left-0
            top-3
          "
        >
          <div
            class="
              overflow-hidden
              rounded-md
              shadow-lg
              ring-1 ring-black/5
            "
          >
            <div class="relative grid bg-surface">
              <div class="relative p-4">
                <BaseInput
                  v-model="textSearch"
                  :placeholder="$t('general.search')"
                  type="text"
                  class="text-black"
                />
              </div>

              <div
                v-if="filteredNotes.length > 0"
                class="relative flex flex-col overflow-auto list max-h-36"
              >
                <div
                  v-for="(note, index) in filteredNotes"
                  :key="index"
                  tabindex="2"
                  class="
                    px-6
                    py-4
                    border-b border-line-default border-solid
                    cursor-pointer
                    hover:bg-hover hover:cursor-pointer
                    last:border-b-0
                  "
                  @click="selectNote(index, close)"
                >
                  <div class="flex justify-between px-2">
                    <label
                      class="
                        m-0
                        text-base
                        font-semibold
                        leading-tight
                        text-heading
                        cursor-pointer
                      "
                    >
                      {{ note.name }}
                    </label>
                  </div>
                </div>
              </div>
              <div v-else class="flex justify-center p-5 text-subtle">
                <label class="text-base text-muted">
                  {{ $t('general.no_note_found') }}
                </label>
              </div>
            </div>
          </div>
        </PopoverPanel>
      </transition>
    </Popover>
  </div>
</template>

<script setup lang="ts">
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useUserStore } from '@/scripts/stores/user.store'
import { noteService } from '@/scripts/api/services/note.service'
import type { Note } from '@/scripts/types/domain/note'

interface Props {
  type?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  type: null,
})

const emit = defineEmits<{
  select: [note: Note]
}>()

const { t } = useI18n()
const textSearch = ref<string>('')
const notes = ref<Note[]>([])
const userStore = useUserStore()

const filteredNotes = computed(() => {
  if (!textSearch.value) {
    return notes.value
  }
  return notes.value.filter(note =>
    note.name.toLowerCase().includes(textSearch.value.toLowerCase())
  )
})

async function fetchInitialData(): Promise<void> {
  try {
    const response = await noteService.list({
      type: props.type || '',
    })
    notes.value = response.data
  } catch (error) {
    console.error('Failed to fetch notes:', error)
  }
}

function selectNote(index: number, close: () => void): void {
  const selectedNote = filteredNotes.value[index]
  if (selectedNote) {
    emit('select', selectedNote)
  }
  textSearch.value = ''
  close()
}
</script>
