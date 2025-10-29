<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import * as yup from 'yup'
import { Form, Field, ErrorMessage } from 'vee-validate'
import axios from 'axios'

const toast = useToast()
const router = useRouter()
const isLoading = ref(false)
const isSuccess = ref(false)

const schema = yup.object({
  nominativo: yup.string().required('Il nominativo è obbligatorio').max(255),
  nome_studio: yup.string().required('Il nome dello studio è obbligatorio').max(255),
  email: yup.string().required("L'email è obbligatoria").email("Inserisci un'email valida"),
  telefono: yup.string().required('Il telefono è obbligatorio').min(9, 'Il telefono deve essere di almeno 9 caratteri').max(20),
})

const handleSubmit = async (values, { resetForm }) => {
  isLoading.value = true
  try {
    const response = await axios.post(`${import.meta.env.VITE_API_URL}/api/contatto-medico`, values)

    if (response.data.success) {
      isSuccess.value = true
      toast.success(response.data.message)
      resetForm()

      // Reindirizza alla home dopo 3 secondi
      setTimeout(() => {
        router.push('/')
      }, 3000)
    }
  } catch (error) {
    console.error('Errore durante l\'invio della richiesta:', error)
    toast.error(error.response?.data?.message || 'Si è verificato un errore. Riprova più tardi.')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="card border-0">
    <div class="card-body p-4">
      <h1 class="card-title text-center mb-3">Unisciti al nostro Network</h1>
      <p class="text-center text-muted mb-4">
        Compila il modulo qui sotto e ti contatteremo per discutere della tua iscrizione alla piattaforma.
      </p>

      <div v-if="isSuccess" class="alert alert-success text-center" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        Richiesta inviata con successo! Verrai reindirizzato alla home...
      </div>

      <Form v-else @submit="handleSubmit" :validation-schema="schema" v-slot="{ errors }">
        <div class="mb-3">
          <label for="nominativo" class="form-label">Nominativo *</label>
          <Field
            name="nominativo"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': errors.nominativo }"
            id="nominativo"
            placeholder="Es: Dr. Mario Rossi"
            required
            :disabled="isLoading"
          />
          <ErrorMessage name="nominativo" class="text-danger small" />
        </div>

        <div class="mb-3">
          <label for="nome_studio" class="form-label">Nome Studio *</label>
          <Field
            name="nome_studio"
            type="text"
            class="form-control"
            :class="{ 'is-invalid': errors.nome_studio }"
            id="nome_studio"
            placeholder="Es: Studio Dentistico Rossi"
            required
            :disabled="isLoading"
          />
          <ErrorMessage name="nome_studio" class="text-danger small" />
        </div>

        <div class="mb-3">
          <label for="email" class="form-label">Email *</label>
          <Field
            name="email"
            type="email"
            class="form-control"
            :class="{ 'is-invalid': errors.email }"
            id="email"
            placeholder="studio@esempio.it"
            required
            :disabled="isLoading"
          />
          <ErrorMessage name="email" class="text-danger small" />
        </div>

        <div class="mb-4">
          <label for="telefono" class="form-label">Telefono *</label>
          <Field
            name="telefono"
            type="tel"
            class="form-control"
            :class="{ 'is-invalid': errors.telefono }"
            id="telefono"
            placeholder="Es: 3331234567"
            required
            :disabled="isLoading"
          />
          <ErrorMessage name="telefono" class="text-danger small" />
        </div>

        <button type="submit" class="btn btn-primary w-100" :disabled="isLoading">
          <span v-if="isLoading" class="spinner-border spinner-border-sm me-2"></span>
          <span>{{ isLoading ? 'Invio in corso...' : 'Invia Richiesta' }}</span>
        </button>

        <div class="text-center mt-4">
          <p class="text-muted small">
            <RouterLink to="/" class="text-decoration-none">
              <i class="fas fa-arrow-left me-1"></i>
              Torna alla Home
            </RouterLink>
          </p>
        </div>
      </Form>
    </div>
  </div>
</template>

<style scoped>
.card {
  box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
}

.form-label {
  font-weight: 500;
}
</style>
