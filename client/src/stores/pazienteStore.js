import { defineStore } from 'pinia'
import axios from 'axios'
import { useAuthStore } from './authStore'

export const usePazienteStore = defineStore('paziente', {
  state: () => ({
    isLoading: false,
  }),
  actions: {
    /**
     * Carica un nuovo preventivo, inclusi i dati anagrafici se forniti.
     * @param {Object} data - L'oggetto contenente il file e i campi del form.
     */
    async uploadQuote(data) {
      this.isLoading = true

      const formData = new FormData()
      formData.append('preventivo', data.preventivoFile)

      if (data.cellulare) formData.append('cellulare', data.cellulare)
      if (data.indirizzo) formData.append('indirizzo', data.indirizzo)
      if (data.citta) formData.append('citta', data.citta)
      if (data.cap) formData.append('cap', data.cap)
      if (data.provincia) formData.append('provincia', data.provincia)

      try {
        const response = await axios.post('/api/preventivi', formData, {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        })

        const authStore = useAuthStore()
        authStore.isAuthCheckCompleted = false
        await authStore.getUser()

        return { success: true, message: response.data.message }
      } catch (error) {
        const message =
          error.response?.data?.message || 'Si è verificato un errore durante il caricamento.'
        return { success: false, message: message }
      } finally {
        this.isLoading = false
      }
    },
  },
})
