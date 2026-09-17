<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { required, minLength, email, helpers, sameAs } from '@vuelidate/validators'
import { useVuelidate } from '@vuelidate/core'
import { useUserStore } from '../../../../stores/user.store'
import { useGlobalStore } from '../../../../stores/global.store'
import { useCompanyStore } from '../../../../stores/company.store'
import { useNotificationStore } from '../../../../stores/notification.store'

interface AvatarFile {
  image: string
}

const LANGUAGE_DEFAULT = 'default'

const userStore = useUserStore()
const globalStore = useGlobalStore()
const companyStore = useCompanyStore()
const notificationStore = useNotificationStore()
const { t } = useI18n()

const isSavingGeneral = ref<boolean>(false)
const isSavingAvatar = ref<boolean>(false)
const isSavingSignature = ref<boolean>(false)
const isSavingPassword = ref<boolean>(false)

// General section
const userForm = computed(() => userStore.userForm)

const selectedLanguage = computed<string>({
  get: () => userForm.value.language || LANGUAGE_DEFAULT,
  set: (v: string) => {
    userForm.value.language = v === LANGUAGE_DEFAULT ? '' : v
  },
})

const languageOptions = computed(() => {
  const languages = (globalStore.config as Record<string, unknown>)?.languages as Array<{ name: string; code: string }> ?? []
  return [
    { name: t('settings.account_settings.default_language'), code: LANGUAGE_DEFAULT },
    ...languages,
  ]
})

const generalRules = computed(() => ({
  name: {
    required: helpers.withMessage(t('validation.required'), required),
    minLength: helpers.withMessage(t('validation.name_min_length'), minLength(3)),
  },
  email: {
    required: helpers.withMessage(t('validation.required'), required),
    email: helpers.withMessage(t('validation.email_incorrect'), email),
  },
}))

const v$General = useVuelidate(generalRules, userForm)

// Profile Photo section
const imgFiles = ref<AvatarFile[]>([])
const avatarFileBlob = ref<File | null>(null)

if (userStore.currentUser?.avatar) {
  imgFiles.value.push({ image: userStore.currentUser.avatar as string })
}

function onFileInputChange(_fileName: string, file: File): void {
  avatarFileBlob.value = file
}

function onFileInputRemove(): void {
  avatarFileBlob.value = null
}

// Signature Photo section
const sigFiles = ref<AvatarFile[]>([])
const signatureFileBlob = ref<File | null>(null)

if (userStore.currentUser?.signature) {
  sigFiles.value.push({ image: userStore.currentUser.signature as string })
}

function onSigFileInputChange(_fileName: string, file: File): void {
  signatureFileBlob.value = file
}

function onSigFileInputRemove(): void {
  signatureFileBlob.value = null
}

// Security section
const passwordForm = reactive({
  password: '',
  confirm_password: '',
})

const passwordRules = computed(() => ({
  password: {
    minLength: helpers.withMessage(
      t('validation.password_min_length', { count: 8 }),
      minLength(8),
    ),
  },
  confirm_password: {
    sameAsPassword: helpers.withMessage(
      t('validation.password_incorrect'),
      sameAs(passwordForm.password),
    ),
  },
}))

const v$Password = useVuelidate(passwordRules, passwordForm)

// Actions
async function updateGeneral(): Promise<void> {
  v$General.value.$touch()
  if (v$General.value.$invalid) return

  isSavingGeneral.value = true
  try {
    const language = userForm.value.language || 'default'

    await userStore.updateUserSettings({ settings: { language } })

    await userStore.updateCurrentUser({
      name: userForm.value.name,
      email: userForm.value.email,
    })

    const effectiveLanguage = (language === 'default' ? '' : language) || companyStore.selectedCompanySettings?.language || 'en'
    await (window as Record<string, unknown>).loadLanguage?.(effectiveLanguage)

    notificationStore.showNotification({
      type: 'success',
      message: 'settings.account_settings.updated_message',
    })
  } finally {
    isSavingGeneral.value = false
  }
}

async function updateAvatar(): Promise<void> {
  if (!avatarFileBlob.value) return

  isSavingAvatar.value = true
  try {
    const formData = new FormData()
    formData.append('admin_avatar', avatarFileBlob.value)

    await userStore.uploadAvatar(formData)

    notificationStore.showNotification({
      type: 'success',
      message: 'settings.account_settings.updated_message',
    })

    imgFiles.value = []
    if (userStore.currentUser?.avatar) {
      imgFiles.value.push({ image: userStore.currentUser.avatar as string })
    }
    avatarFileBlob.value = null
  } finally {
    isSavingAvatar.value = false
  }
}

async function updateSignature(): Promise<void> {
  if (!signatureFileBlob.value) return

  isSavingSignature.value = true
  try {
    const formData = new FormData()
    formData.append('admin_signature', signatureFileBlob.value)

    await userStore.uploadSignature(formData)

    notificationStore.showNotification({
      type: 'success',
      message: 'settings.account_settings.updated_message',
    })

    sigFiles.value = []
    if (userStore.currentUser?.signature) {
      sigFiles.value.push({ image: userStore.currentUser.signature as string })
    }
    signatureFileBlob.value = null
  } finally {
    isSavingSignature.value = false
  }
}

// Combined upload for both profile photo and signature in one section
async function updatePhotos(): Promise<void> {
  // Upload avatar if a new file was selected
  if (avatarFileBlob.value) {
    await updateAvatar()
  }

  // Upload signature if a new file was selected
  if (signatureFileBlob.value) {
    await updateSignature()
  }
}

async function updatePassword(): Promise<void> {
  v$Password.value.$touch()
  if (v$Password.value.$invalid) return

  isSavingPassword.value = true
  try {
    await userStore.updateCurrentUser({
      name: userStore.currentUser?.name ?? '',
      email: userStore.currentUser?.email ?? '',
      password: passwordForm.password,
      confirm_password: passwordForm.confirm_password,
    })

    notificationStore.showNotification({
      type: 'success',
      message: 'settings.account_settings.updated_message',
    })

    passwordForm.password = ''
    passwordForm.confirm_password = ''
    v$Password.value.$reset()
  } finally {
    isSavingPassword.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <!-- General Section -->
    <form @submit.prevent="updateGeneral">
      <BaseSettingCard
        :title="$t('settings.account_settings.general')"
        :description="$t('settings.account_settings.section_description')"
      >
        <BaseInputGrid class="mt-5">
          <BaseInputGroup
            :label="$t('settings.account_settings.name')"
            :error="v$General.name.$error && v$General.name.$errors[0]?.$message"
            required
          >
            <BaseInput
              v-model="userForm.name"
              :invalid="v$General.name.$error"
              @blur="v$General.name.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.account_settings.email')"
            :error="v$General.email.$error && v$General.email.$errors[0]?.$message"
            required
          >
            <BaseInput
              v-model="userForm.email"
              type="email"
              :invalid="v$General.email.$error"
              @blur="v$General.email.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.language')">
            <BaseMultiselect
              v-model="selectedLanguage"
              :options="languageOptions"
              label="name"
              value-prop="code"
              track-by="code"
              :searchable="true"
              :can-deselect="false"
            />
          </BaseInputGroup>
        </BaseInputGrid>

        <BaseButton :loading="isSavingGeneral" :disabled="isSavingGeneral" type="submit" class="mt-6">
          <template #left="slotProps">
            <BaseIcon v-if="!isSavingGeneral" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ $t('settings.company_info.save') }}
        </BaseButton>
      </BaseSettingCard>
    </form>

    <!-- Profile & Signature Photo Section -->
    <form @submit.prevent="updatePhotos">
      <BaseSettingCard
        :title="$t('settings.account_settings.profile_picture')"
        :description="$t('settings.account_settings.profile_picture_description')"
      >
        <BaseInputGrid class="mt-5">
          <BaseInputGroup :label="$t('settings.account_settings.profile_picture')">
            <BaseFileUploader
              v-model="imgFiles"
              :avatar="true"
              accept="image/*"
              @change="onFileInputChange"
              @remove="onFileInputRemove"
            />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.account_settings.signature_picture')">
            <BaseFileUploader
              v-model="sigFiles"
              :avatar="true"
              accept="image/*"
              @change="onSigFileInputChange"
              @remove="onSigFileInputRemove"
            />
          </BaseInputGroup>
        </BaseInputGrid>

        <BaseButton :loading="isSavingAvatar || isSavingSignature" :disabled="isSavingAvatar || isSavingSignature" type="submit" class="mt-6">
          <template #left="slotProps">
            <BaseIcon v-if="!isSavingAvatar && !isSavingSignature" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ $t('settings.company_info.save') }}
        </BaseButton>
      </BaseSettingCard>
    </form>

    <!-- Security Section -->
    <form @submit.prevent="updatePassword">
      <BaseSettingCard
        :title="$t('settings.account_settings.security')"
        :description="$t('settings.account_settings.security_description')"
      >
        <BaseInputGrid class="mt-5">
          <BaseInputGroup
            :label="$t('settings.account_settings.password')"
            :error="v$Password.password.$error && v$Password.password.$errors[0]?.$message"
          >
            <BaseInput
              v-model="passwordForm.password"
              type="password"
              :invalid="v$Password.password.$error"
              @blur="v$Password.password.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.account_settings.confirm_password')"
            :error="v$Password.confirm_password.$error && v$Password.confirm_password.$errors[0]?.$message"
          >
            <BaseInput
              v-model="passwordForm.confirm_password"
              type="password"
              :invalid="v$Password.confirm_password.$error"
              @blur="v$Password.confirm_password.$touch()"
            />
          </BaseInputGroup>
        </BaseInputGrid>

        <BaseButton :loading="isSavingPassword" :disabled="isSavingPassword" type="submit" class="mt-6">
          <template #left="slotProps">
            <BaseIcon v-if="!isSavingPassword" name="ArrowDownOnSquareIcon" :class="slotProps.class" />
          </template>
          {{ $t('settings.company_info.save') }}
        </BaseButton>
      </BaseSettingCard>
    </form>
  </div>
</template>
